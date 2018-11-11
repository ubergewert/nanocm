<?php

/**
 * NanoCM
 * Copyright (C) 2018 André Gewert <agewert@ubergeek.de>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

namespace Ubergeek\NanoCm\Module;
use Ubergeek\NanoCm\Article;
use Ubergeek\NanoCm\Media\ImageResizer;
use Ubergeek\NanoCm\Medium;
use Ubergeek\NanoCm\Page;
use Ubergeek\NanoCm\Setting;
use Ubergeek\NanoCm\Tag;

/**
 * Kern(-Ausgabe-)modul des NanoCM.
 * 
 * Dieses Modul implementiert die Kern-Ausgabe-Funktionen des Content Managers.
 * Dazu gehören insbesondere die Startseite, die Darstellung einzelner Artikel
 * sowie das CMS-Archiv.
 * 
 * @author André Gewert <agewert@ubergeek.de>
 * @package Ubergeek\NanoCm
 * @created 2017-11-12
 */
class CoreModule extends AbstractModule {

    // <editor-fold desc="Properties">

    /** @var string Generierter Content */
    private $content = '';

    /** @var bool Gibt an, ob es beim letzten Login-Versuch einen Fehler gegeben hat */
    public $loginError = false;

    /** @var Page Gegebenenfalls anzuzeigende Seite */
    public $page = null;

    /** @var Article Gegebenenfalls anzuzeigender Weblog-Artikel */
    public $article = null;

    /** @var Article[] Gegebenenfalls anzuzeigende Weblog-Artikel */
    public $articles = null;

    /** @var string Gesuchte Tags */
    public $searchTags = null;

    // </editor-fold>


    public function run() {
        $parts = $this->getRelativeUrlParts();

        // Das CoreModule protokolliert Seitenzugriffe, wenn die Funktion aktiviert ist
        if ($this->orm->getSettingValue(Setting::SYSTEM_STATS_ENABLELOGGING) == '1') {
            $geolocation = $this->orm->getSettingValue(Setting::SYSTEM_STATS_ENABLEGEOLOCATION) == '1';
            $accesslog = $this->orm->getSettingValue(Setting::SYSTEM_STATS_ENABLEACCESSLOG) == '1';
            $entry = $this->ncm->createAccessLogEntry($this->frontController->getHttpRequest());

            if ($accesslog) {
                $this->orm->logHttpRequest($entry);
            }
            $this->orm->logSimplifiedStats($entry, $geolocation);
        }

        switch ($parts[0]) {
            
            // Integrierte Weblog-Funktionen
            case 'weblog':
                // Artikel-Ansicht
                if ($parts[1] == 'article') {
                    $this->log->debug($parts[2]);
                    $this->article = $this->orm->getArticleById(intval($parts[2]));
                    if ($this->article !== null) {
                        $this->setTitle($this->getSiteTitle() . ' - ' . $this->article->headline);
                        $this->content = $this->renderUserTemplate('content-weblog-article.phtml');
                    }
                }

                // Archiv
                elseif ($parts[1] == 'archive') {
                    $this->setTitle($this->getSiteTitle() . ' - Archiv');
                    $this->articles = $this->orm->getArticleArchive();
                    $this->content = $this->renderUserTemplate('content-weblog-archive.phtml');
                }

                // Suche nach Artikeln mit bestimmten Tags
                elseif ($parts[1] == 'tags') {
                    $this->setTitle($this->getSiteTitle() . ' - Aritkelsuche nach Stichworten');
                    $this->searchTags = Tag::splitTagsString(urldecode($parts[2]));
                    $filter = new Article();
                    $filter->tags = $this->searchTags;
                    // TODO Paginierung!
                    $this->articles = $this->orm->searchArticles($filter);
                    $this->content = $this->renderUserTemplate('content-weblog-tags.phtml');
                }
                break;

            // Mediendateien
            case 'media':
                if (count($parts) >= 3) {
                    switch ($parts[2]) {

                        // Eine Datei herunterladen
                        case 'download':
                            $mediumHash = $parts[1];
                            $this->setPageTemplate(self::PAGE_NONE);
                            $this->setContentType('binary/octet-stream');
                            $file = $this->orm->getMediumByHash($mediumHash, Medium::TYPE_FILE, true);
                            if ($file != null) {
                                if (strlen($file->type) > 0) {
                                    $this->setContentType($file->type);
                                    $this->replaceMeta('Content-Disposition', "attachment; filename=\"" . urlencode($file->filename) . "\"");
                                    $this->replaceMeta('Content-Length', $file->filesize);
                                    $this->content = $this->orm->getMediumFileContents($file->id);
                                }
                            }
                            break;

                        // Ein Bild in einem bestimmten Format ausgeben
                        case 'image':
                            $imageResizer = new ImageResizer($this->ncm->mediacache);
                            $mediumHash = $parts[1];
                            $formatKey = $parts[3];
                            $format = $this->orm->getImageFormatByKey($formatKey);
                            $medium = $this->orm->getMediumByHash($mediumHash, Medium::TYPE_FILE, true);

                            if ($medium != null) {
                                $this->setPageTemplate(self::PAGE_NONE);
                                $this->setContentType('image/jpeg');
                                $data = $this->orm->getMediumFileContents($medium->id);
                                $c = $imageResizer->createImageForMediumWithImageFormat($medium, $data, $format, 'jpeg');
                                $this->content = $c;
                            }
                            break;
                    }
                }
                break;
            
            // Anmeldung
            case 'login.html':
            case 'login.php':
                $this->setTitle($this->getSiteTitle() . ' - Anmelden');
                if ($this->getAction() == 'login') {
                    $success = $this->ncm->tryToLoginUser(
                        $this->getParam('username', ''),
                        $this->getParam('password', '')
                    );
                    if ($success) {
                        $this->replaceMeta('location', 'index.php');
                    } else {
                        $this->loginError = true;
                    }
                }
                $this->content = $this->renderUserTemplate('content-login.phtml');
                break;
            
            // Abmeldung
            case 'logout.html':
            case 'logout.php':
                $this->ncm->logoutUser();
                $this->replaceMeta('location', 'index.php');
                break;
            
            // Startseite
            case 'index.html':
            case 'index.php';
                $this->setTitle($this->getSiteTitle());
                $this->content = $this->renderUserTemplate('content-start.phtml');
                break;

            // Frei definierbare Pages
            default:
                $this->page = $this->orm->getPageByUrl($this->getRelativeUrl());
                if ($this->page !== null) {
                    $this->setTitle($this->getSiteTitle() . ' - ' . $this->page->headline);
                    $this->content = $this->renderUserTemplate('content-page.phtml');
                }
        }

        $this->setContent($this->content);
    }
}