<?php
/**
 * NanoCM
 * Copyright (C) 2018 André Gewert <agewert@ubergeek.de>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace Ubergeek\MarkupParser;

/**
 * Stellt einen Parser dar für Plaintext-Eingaben, die mit einfachem Formatierungs-Markup versehen sind.
 *
 * Die Syntax ist angelehnt an die Markdown-Syntax, realisiert einige Dinge jedoch etwas anders.
 * Vor allem die einfachen Elemente (Betonung, starke Betonung, Unterstrich, Durchstrich) unterscheiden sich.
 * Die Ersetzung von Inline-Images funktioniert nach Markdown-Syntax. Für komplexere Image-Einbettungen inklusive
 * Bild-Unterschrift u. ä. sollen andere ContentConverter-Klassen verwendet werden.
 *
 * @author agewert@ubergeek.de
 * @package Ubergeek\MarkupParser
 * @created 2018-05-15
 */
class MarkupParser {

    // <editor-fold desc="Public properties / Settings">

    /**
     * Bestimmt, ob Markdown-kompatible Zeilenumbrüche verwendet werden sollen.
     * Bei eingeschalteter Markdown-Kompatibilität müssen Zeilenumbrüche innerhalb eines Absatzes mit zwei Leerzeichen
     * am Ende einer Zeile markiert werden. Ist diese Option ausgeschaltet, bewirkt jedes Newline innerhalb eines
     * Absatzes einen Zeilenumbruch.
     * @var bool
     */
    public $useMarkdownStyleLinebreaks = false;

    /**
     * @var bool Gibt an, ob Links ersetzt werden sollen
     */
    public $enableLinks = true;

    /**
     * @var bool Gibt an, ob (Pseudo-)Anführungszeichen durch typografisch korrekte Anführungszeichen ersetzt werden
     * sollen
     */
    public $enableSmartQuotes = true;

    // </editor-fold>


    // <editor-fold desc="Internal properties">

    protected $abbreviations = array();

    // </editor-fold>


    // <editor-fold desc="Public methods">

    /**
     * Interpretiert einen mit Markup versehenen Plaintext und erstellt daraus HTML-Code, der direkt in die Website
     * eingebettet werden kann.
     * @param string $input Ausgangstext mit Formatierungs-Markup
     * @return string HTML-Code
     */
    public function parse(string $input) : string {
        $output = "";

        // Abkürzungen extrahieren
        $input = $this->extractAbbreviations($input);

        // Basis-Formatierung
        $input = trim($input);
        $input = str_replace(array("\r\n", "\r"), "\n", $input);

        // HTML-Sonderzeichen kodieren
        $input = htmlspecialchars($input, ENT_HTML5 | ENT_COMPAT);

        // Einzelne Blöcke auftrennen und parsen
        $blocks = $this->splitBlocks($input);
        foreach ($blocks as $block) {
            $output .= $this->parseBlock($block);
        }

        // Nicht markierte Links etc. ersetzen
        // ...

        // Abkürzungen ersetzen
        $output = $this->replaceAbbreviations($output);

        return $output;
    }

    /**
     * Fügt der Abkürzungsliste eine neue Definition hinzu oder ersetzt eine bereits bestehende Definition.
     * Diese Methode kann genutzt werden, um zu ersetzende Abkürzungen festzulegen, die nicht im zu parsenden Text
     * selbst definiert sind, sondern außerhalb (etwa in einer Website-übergreifenden Konfiguration).
     * @param $key Abkürzung
     * @param $description Beschreibung
     */
    public function addAbbreviation($key, $description) {
        if (!is_array($this->abbreviations)) {
            $this->abbreviations = array();
        }
        $this->abbreviations[trim($key)] = $description;
    }

    /**
     * Fügt dem Converter eine Liste von zu ersetzenden Abkürzungen hinzu
     * @param array $abbreviations
     * @see addAbbreviation
     */
    public function addAbbreviations(array $abbreviations) {
        foreach ($abbreviations as $key => $value) {
            $this->addAbbreviation($key, $value);
        }
    }

    // </editor-fold>


    // <editor-fold desc="Internal methods">

    /**
     * Extrahiert aus dem Ausgangstext Abkürzungs-Deklarationen.
     * Die gefundenen Abkürzungen werden aus dem Markup-Text gestrichen (sie sollen nicht direkt sichtbar sein) und in
     * einer internen Property gesammelt. Durch Aufruf der Methode replaceAbbreviations() können die gesammelten
     * Definitionen durch ABBR-Tags ersetzt werden.
     * Der Aufruf dieser Methode sollte nicht direkt erfolgen, sondern nur indirekt über die Methode parse().
     * @param string $input Der ungeparste Ausgangstext
     * @return string Der Markup-Text OHNE die Abkürzungs-Deklarationen
     */
    protected function extractAbbreviations(string $input) : string {
        $output = preg_replace_callback('/^\*\[([^\]]+)\]\:\s+(.+?)$/ims', function($matches) {
            $this->abbreviations[$matches[1]] = htmlspecialchars(trim($matches[2]));
            return '';
        }, $input);
        return $output;
    }

    /**
     * Ersetzt in dem übergebenen teilweise HTML-kodierten Zwischentext gefundene Abkürzungen durch entsprechendes
     * HTML-Markup.
     * @param string $input Der teilweise nach HTML konvertierte Ausgangstext
     * @return string Der durch ABBR-Tags ersetzte Ausgangstext
     */
    protected function replaceAbbreviations(string $input) : string {
        $output = preg_replace_callback('/\>([^\>]+)\</i', function ($matches) {
            $o = $matches[1];
            foreach ($this->abbreviations as $key => $description) {
                $o = str_replace($key, "<abbr title=\"$description\">" . $key . "</abbr>", $o);
            }
            return '>' . $o . '<';
        }, $input);
        return $output;
    }

    /**
     * Zerlegt den übergebenen Ausgangstext in einzelne zu parsende Block-Elemente
     * @param string $input Ungeparster Ausgangstext
     * @return string[] Ein Array der gefundenen Block-Elemente
     */
    protected function splitBlocks(string $input) : array {
        $blocks = preg_split('/\n\n+/is', $input);
        return $blocks;
    }

    /**
     * Parst einen einzelnen Inhaltsblock und erstellt daraus HTML-Code
     * @param string $input Ursprünglicher (ungeparster) Inhaltsblock
     * @return string Der geparste und mit HTML-Markup versehene Inhaltsblock
     */
    protected function parseBlock(string $input) : string {
        // Überschriften
        if (preg_match('/^(#{1,6})\s*(.*)$/', $input, $matches) === 1) {
            $level = strlen($matches[1]);
            $output = "<h$level>" . htmlentities($matches[2]) . "</h$level>\n";
        }

        // Horizontal Rule
        elseif (preg_match("/^[\-\*\_]{3,}$/im", $input) === 1) {
            $output = "<hr>\n";
        }

        // Fenced Code
        elseif (preg_match("/^```(.*?)\n(.*)```$/is", $input, $matches) === 1) {
            if (strlen($matches[1]) > 0) {
                $output = "<pre><code class=\"$matches[1]\">$matches[2]</code></pre>\n";
            } else {
                $output = "<pre><code>$matches[2]</code></pre>\n";
            }
        }

        // Block quotes
        elseif (mb_substr($input, 0, 5) == '&gt; ') {
            $output = "<blockquote class=\"blockquote\"><p>";
            $output .= $this->parseInlineElements(
                preg_replace("/^(&gt; )/ims", "", $input)
            );
            $output .= "</p></blockquote>";
        }

        // TODO Tables

        // Einfache Listen
        elseif (preg_match('/^\s*(\-|#)/i', $input, $matches) === 1) {
            $output = "<ul>\n";
            foreach (preg_split('/^\s*(\-|#)\s*/ims', $input) as $item) {
                if (mb_strlen(trim($item)) > 0) {
                    $output .= '<li>' . $this->parseInlineElements($item) . "</li>\n";
                }
            }
            $output .= "</ul>\n";
        }

        // Text-Absätze
        else {
            $output = '<p>' . $this->parseInlineElements($input) . "</p>\n";
        }

        return $output;
    }

    /**
     * Parst Inline-Elemente innerhalb eines Inhaltsblocks und fügt entsprechenden HTML-Code ein.
     * Diese Methode sollte nicht direkt genutzt werden, sondern immer durch parseBlock() aufgerufen werden.
     * @param string $input Der bereits vorgeparste Inhaltsblock
     * @return string Inhaltsblock mit durch HTML-Code ersetztes Markup
     */
    protected function parseInlineElements(string $input) : string {
        $input = preg_replace('/\*\*(.+?)\*\*/i', "<strong>$1</strong>", $input);
        $input = preg_replace('/\*(.+?)\*/i', "<em>$1</em>", $input);
        $input = preg_replace('/\_(.+?)\_/i', "<u>$1</u>", $input);
        $input = preg_replace('/~(.+?)~/i', "<del>$1</del>", $input);
        $input = preg_replace('/\`(.+?)\`/i', "<code>$1</code>", $input);
        $input = preg_replace('/\$(.+?)\$/i', "<var>$1</var>", $input);
        $input = preg_replace('/\^(.+?)\^/i', "<sup>$1</sup>", $input);
        $input = preg_replace('/\|(.+?)\|/i', "<span class=\"smallcaps\">$1</span>", $input);
        //$input = preg_replace('/\\\(.+?)\//i', "<sub>$1</sub>", $input);

        // TODO Subscript

        $input = preg_replace_callback('/\{(.*?)\}/', function($matches) {
            $codes = array();
            foreach (preg_split('/\s+/', $matches[1]) as $part) {
                array_push($codes, "<kbd>$part</kbd>");
            }
            return "<kbd>" . join('&nbsp;+ ', $codes) . "</kbd>";
        }, $input);

        // Art der Zeilenumbruch-Markierung ist konfigurierbar
        if ($this->useMarkdownStyleLinebreaks) {
            $input = preg_replace('/  $/ims', "<br>", $input);
        } else {
            $input = preg_replace('/\n/i', "<br>", $input);

        }

        // Bestimmte typografische Zeichen ersetzen
        $input = preg_replace('/(\w)\s(-){1,2}\s(\w)/', "$1&nbsp;&ndash; $3", $input);
        $input = preg_replace('/(\w)\s\/\s(\w)/', "$1&nbsp;/ $2", $input);
        $input = preg_replace('/ \.\.\./i', "&nbsp;&hellip;", $input);
        $input = preg_replace('/\.\.\. /i', "&hellip;&nbsp;", $input);
        $input = preg_replace('/\.\.\./i', "&hellip;", $input);
        $input = preg_replace('/\\\ /i', "&nbsp;", $input);

        // Inline images
        $input = preg_replace('/\!\[([^\]]*)\]\(([^\) ]*)\s*(\&quot;(.*)\&quot;)?\)/i', "<img src=\"$2\" alt=\"$1\" title=\"$4\">", $input);

        // Links ersetzen
        $input = $this->parseInlineLinks($input);

        // TODO Footnotes
        // ...

        return $input;
    }

    /**
     * Ersetzt in einem Inhaltsblock enthaltene Links durch entsprechendes HTML-Markup.
     *
     * Der Aufruf dieser Methode sollte immer indirekt bzw. parseBlock() erfolgen. Ob tatsächlich HTML-Links generiert
     * werden oder nur (bei Nutzung von Markdown-Syntax) die URLs durch Labels ersetzt werden, ist konfigurierbar.
     * @param string $input Der bereits vorgeparste Inhaltsblock
     * @return string Inhaltsblock mit durch HTML-Code ersetzten Links
     * @see enableLinks
     */
    protected function parseInlineLinks(string $input) : string {
        // TODO Link-Ersetzung implementieren
        if ($this->enableLinks) {
            // ...
        } else {
            // Wenn Verlinkung deaktiviert ist, müssen dennoch benannte Links durch das Label ersetzt werden
        }
        return $input;
    }

    // </editor-fold>
}
