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
use Ubergeek\Feed\AtomWriter;
use Ubergeek\NanoCm\Article;
use Ubergeek\NanoCm\Captcha;
use Ubergeek\NanoCm\Comment;
use Ubergeek\NanoCm\Constants;
use Ubergeek\NanoCm\EbookGenerator;
use Ubergeek\NanoCm\FeedGenerator;
use Ubergeek\NanoCm\Medium;
use Ubergeek\NanoCm\Page;
use Ubergeek\NanoCm\Setting;
use Ubergeek\NanoCm\StatusCode;
use Ubergeek\NanoCm\Tag;
use Ubergeek\NanoCm\Util;

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

    /** @var Page Gegebenenfalls anzuzeigende Seite */
    public $page = null;

    /** @var Article Gegebenenfalls anzuzeigender Weblog-Artikel */
    public $article = null;

    /** @var bool Gibt an, ob der abgerufene Artikel noch nicht freigeschaltet ist, sondern als Preview angezeigt wird. */
    public $isPreview = false;

    /** @var Comment[] Gegebenfalls anzuzeigende Kommentare zu einem Artikel */
    public $comments = null;

    /** @var Captcha Gegenenfalls zu lösendes Captcha */
    public $captcha = null;

    /** @var Article[] Gegebenenfalls anzuzeigende Weblog-Artikel */
    public $articles = null;

    /** @var string Gesuchte Tags */
    public $searchTags = null;

    /** @var bool Gibt an, ob die Kommentarfunktion grundsätzlich eingeschaltet ist */
    public $commentsEnabled = false;

    /** @var bool Gibt an, ob die Trackback-Funktion grundsätzlich eingeschaltet ist */
    public $trackbacksEnabled = false;

    /** @var string Benutzereingabe "Name" für Kommentar */
    public $commentName;

    /** @var string Benutzereingabe "E-Mail" für Kommentar */
    public $commentMail;

    /** @var string Benutzereingabe "Website" für Kommentar */
    public $commentSite;

    /** @var string Benutzereingabe "Ort" für Kommentar */
    public $commentLocation;

    /** @var string Benutzereingabe "Überschrift" für Kommentar */
    public $commentHeadline;

    /** @var string Benutzereingabe "Text" für Kommentar */
    public $commentText;

    /** @var bool Benutzereingabe füe "Gravatar verwenden" */
    public $commentUseGravatar;

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

        // Kommentare und Trackback
        $this->commentsEnabled = $this->orm->getSettingValue(Setting::SYSTEM_ENABLECOMMENTS) == '1';
        $this->trackbacksEnabled = $this->orm->getSettingValue(Setting::SYSTEM_ENABLETRACKBACKS) == '1';
        if ($this->commentsEnabled) {
            $this->commentName = $this->getOrOverrideSessionVarWithParam('_n');
            $this->commentMail = $this->getOrOverrideSessionVarWithParam('_e');
            $this->commentSite = $this->getOrOverrideSessionVarWithParam('_u');
            $this->commentLocation = $this->getOrOverrideSessionVarWithParam('_c');
            $this->commentHeadline = $this->getOrOverrideSessionVarWithParam('_h');
            $this->commentText = $this->getOrOverrideSessionVarWithParam('_t');
            $this->commentUseGravatar = $this->getOrOverrideSessionVarWithParam('_g');
        }

        switch ($parts[0]) {
            
            // Integrierte Weblog-Funktionen
            case 'weblog':

                // Artikel-Ansicht
                if ($parts[1] == 'article') {

                    $this->article = $this->orm->getArticleById(
                        intval($parts[2]),
                        !$this->ncm->isUserLoggedIn()
                    );

                    if ($this->article !== null) {

                        // Bestimmung Preview-Modus bei noch nicht freigeschalteten Artiklen
                        $now = new \DateTime('now');
                        $this->isPreview = $this->article->status_code != StatusCode::ACTIVE
                            || ($this->article->start_timestamp != null && $this->article->start_timestamp > $now)
                            || ($this->article->stop_timestamp != null && $this->article->stop_timestamp < $now);

                        // Kommentar abgeben
                        if ($this->commentsEnabled && $this->article->enable_comments && $this->getParam('ac') == 's') {
                            $comment = $this->tryToSaveComment($this->article->id);
                            if ($comment instanceof Comment) {

                                // TODO Zugriff auf Request muss schöner gehen ...
                                $uri = $this->frontController->getHttpRequest()->requestUri->getRequestUrl();
                                if ($comment->status_code == StatusCode::MODERATION_REQUIRED) {
                                    $uri .= (stristr($uri, '?') === false)? '?s=1' : '&s=1';
                                }
                                $this->ncm->session->setVar('_h', '');
                                $this->ncm->session->setVar('_t', '');
                                $this->replaceMeta('location', $uri);
                                $this->setPageTemplate(self::PAGE_NONE);
                                $this->content = 'Redirect';
                                break;
                            }
                        }

                        // Darstellung des Artikels
                        if ($this->getParam('s') == '1') {
                            $this->addUserMessage('Dein Kommentar wird vor Freischaltung geprüft. Bitte habe etwas Geduld.', 'Geduld, bitte');
                        }
                        $this->captcha = $this->ncm->createCaptcha();
                        $this->comments = $this->orm->getCommentsByArticleId($this->article->id);
                        $this->setTitle($this->getSiteTitle() . ' - ' . $this->article->headline);
                        $this->content = $this->renderUserTemplate('content-weblog-article.phtml');
                    }
                }

                // E-Book-Export
                elseif ($parts[1] == 'ebook') {
                    switch ($parts[2]) {
                        case 'article':
                            $this->setPageTemplate(self::PAGE_NONE);
                            $this->outputFormat = Constants::FORMAT_XHTML;

                            $epub = new EbookGenerator($this);
                            $c = $epub->createEpubForArticleWithId(
                                intval($parts[3])
                            );
                            $this->content = $c;
                            break;
                        case 'series':
                            break;
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

                // Verschiedene Atom-Feeds
                elseif ($parts[1] == 'feed') {

                    // Neueste Artikel
                    if (count($parts) >= 2 && $parts[2] == 'index.php') {
                        $this->setPageTemplate(self::PAGE_NONE);
                        $this->setContentType('text/xml');
                        $generator = new FeedGenerator($this);
                        $feed = $generator->createFeedForArticles(
                            $this->ncm->orm->getLatestArticles(10),
                            $this->frontController->getHttpRequest()->requestUri->getRequestUrl(),
                            $this->ncm->orm->getSiteTitle() . ': Neueste Artikel'
                        );
                        $writer = new AtomWriter();
                        $this->content = $writer->writeFeed($feed);
                    }

                    // Neueste Kommentare
                    elseif (count($parts) >= 3 && $parts[2] == 'comments') {
                        $this->setPageTemplate(self::PAGE_NONE);
                        $this->setContentType('text/xml');

                        $this->setPageTemplate(self::PAGE_NONE);
                        $this->setContentType('text/xml');

                        $generator = new FeedGenerator($this);
                        $feed = $generator->createFeedForComments(
                            $this->orm->getLatestComments(10),
                            $this->frontController->getHttpRequest()->requestUri->getRequestUrl(),
                            $this->ncm->orm->getSiteTitle() . ': Neueste Kommentare'
                        );
                        $writer = new AtomWriter();
                        $this->content = $writer->writeFeed($feed);
                    }

                    // Artikel zu bestimmten Schlagworten
                    elseif (count($parts) >= 4 && $parts[2] == 'tags') {
                        $this->setPageTemplate(self::PAGE_NONE);
                        $this->setContentType('text/xml');

                        $tags = Tag::splitTagsString(urldecode($parts[3]));
                        $filter = new Article();
                        $filter->tags = $tags;
                        $articles = $this->orm->searchArticles($filter);

                        $generator = new FeedGenerator($this);
                        $feed = $generator->createFeedForArticles(
                            $articles,
                            $this->frontController->getHttpRequest()->requestUri->getRequestUrl(),
                            $this->ncm->orm->getSiteTitle() . ': Artikel mit Stichworten'
                        );
                        $writer = new AtomWriter();
                        $this->content = $writer->writeFeed($feed);
                    }
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

                        // Gravatar
                        case 'gravatar':
                            $hash = $parts[1];
                            $size = (count($parts) >= 3)? intval($parts[3]) : 50;
                            $imgData = $this->ncm->mediaManager->getGravatar($hash, $size);

                            if ($imgData != null) {
                                $this->setPageTemplate(self::PAGE_NONE);
                                $this->setContentType('image/png');
                                $this->replaceMeta('content-length', strlen($imgData));
                                $this->content = $imgData;
                            }
                            break;

                        // Youtube-Vorschau
                        case 'yt':
                            $youtubeId = $parts[1];
                            $formatKey = $parts[3];
                            $format = $this->orm->getImageFormatByKey($formatKey);

                            if ($format != null) {
                                $imgData = $this->ncm->mediaManager->createImageForYoutubeVideoWithFormat($youtubeId, $format);
                                $this->setPageTemplate(self::PAGE_NONE);
                                $this->setContentType('image/jpeg');
                                $this->replaceMeta('content-length', strlen($imgData));
                                $this->content = $imgData;
                            }
                            break;

                        // Ein Bild in einem bestimmten Format ausgeben
                        case 'image':
                            $mediumHash = $parts[1];
                            $formatKey = $parts[3];
                            $format = $this->orm->getImageFormatByKey($formatKey);
                            $medium = $this->orm->getMediumByHash($mediumHash, Medium::TYPE_FILE, true);

                            if ($medium != null) {
                                $this->setPageTemplate(self::PAGE_NONE);
                                $this->setContentType('image/jpeg');
                                $data = $this->orm->getMediumFileContents($medium->id);
                                $c = $this->ncm->mediaManager->createImageForMediumWithImageFormat($medium, $data, $format, 'jpeg');
                                $this->replaceMeta('content-length', strlen($c));
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
                        $this->replaceMeta('location', $this->convUrl('index.php'));
                    } else {
                        $this->addUserMessage('Die Anmeldung war nicht erfolgreich. Bitte überprüfen Sie Ihre Anmeldedaten.', "Anmeldung fehlgeschlagen");
                    }
                }
                $this->content = $this->renderUserTemplate('content-login.phtml');
                break;
            
            // Abmeldung
            case 'logout.html':
            case 'logout.php':
                $this->ncm->logoutUser();
                $this->content = 'success';
                $this->replaceMeta('location', $this->convUrl('index.php'));
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


    // <editor-fold desc="Internal methods">

    private function tryToSaveComment($articleId) {
        $captchaId = $this->getParam('cpsid', '');
        $captchaInput = $this->getParam('sc', '');

        $comment = new Comment();
        $comment->article_id = $articleId;
        $comment->status_code = StatusCode::ACTIVE;
        $comment->spam_status = 0;
        $comment->username = $this->commentName;
        $comment->email = $this->commentMail;
        $comment->headline = $this->commentHeadline;
        $comment->content = $this->commentText;
        $comment->use_gravatar = $this->commentUseGravatar == '1';

        $this->log->debug($comment);

        // Eingabe auf Vollständigkeit überprüfen
        if (strlen($comment->username) < 1
            || strlen($comment->content) < 1
            || strlen($comment->email) < 1) {
            $this->addUserMessage("Bitte gib mindestens einen Namen, eine E-Mail-Adresse und einen Text ein, um zu kommentieren", "Unvollständige Eingaben");
            return null;
        }

        // Captcha überprüfen
        if (!$this->ncm->isCaptchaSolved($captchaId, $captchaInput)) {
            $this->addUserMessage('Du hast die Sicherheitsfrage nicht korrekt beantwortet', 'Fehler');
            return null;
        }

        // E-Mail überprüfen
        if (!Util::isValidEmail($comment->email)) {
            $this->addUserMessage('Du hast eine ungültige E-Mail-Adresse eingegeben', 'Fehler');
            return null;
        }

        // Spam-Prüfung und zeitliche Einschränkungen nur, wenn kein Benutzer angemeldet ist
        if (!$this->ncm->isUserLoggedIn()) {
            // IP-Adresse auf Sperre testen
            if ($this->ncm->isIpBlockedForComments($_SERVER['REMOTE_ADDR'])) {
                $this->addUserMessage('Dein Kommentar wurde nicht übernommen. Bitte habe ein paar Minuten Geduld, bevor Du einen neuen Kommentar schreibst.', 'Fehler');
                return null;
            }

            // Spam-Begriffe prüfen
            if (Util::checkTextAgainstWordsList($comment->content, Util::getJunkWords())) {
                $comment->status_code = StatusCode::MODERATION_REQUIRED;
            }
        }

        // Kommentar speichern
        $this->ncm->blockIpForComments($_SERVER['REMOTE_ADDR']);
        $this->orm->saveComment($comment);
        $this->ncm->session->setVar('commentText', '');
        $this->ncm->session->setVar('commentHeadline', '');
        $this->commentText = '';
        $this->commentHeadline = '';

        // TODO E-Mail-Notification senden
        // ...

        return $comment;
    }

    // </editor-fold>
}