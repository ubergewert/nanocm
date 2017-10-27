<?php

/* 
 * Copyright (C) 2017 André Gewert <agewert@ubergeek.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Ubergeek\NanoCm;

/**
 * Kapselt alle system-internen Datenbank-Funktionen in einer Klasse.
 * 
 * Alle Object-Relation-Mapping-Methoden für integrale Bestandteile des NanoCM
 * werden in dieser Klasse zur Verfügung gestellt. Eine Instanz dieses ORM ist
 * über den jeweiligen Controller in jedem Template zugänglich.
 * 
 * Optionale Zusatzmodule können ihre eigene Datenbank-Funktionalitäten über
 * eigene Klassen-Instanzen bereitstellen.
 * 
 * Zu den Grundfunktionen des NanoCM gehören:
 * 
 * - Artikelverwaltung
 * - Benutzerverwaltung
 * - Verwaltung von Kommentaren und Trackbacks
 * - Grundlegende Statistiken
 * - Systemeinstellungen
 * 
 * Zu den optionalen Modulen gehört beispielsweise die Medienverwaltung.
 * 
 * @author agewert@ubergeek.de
 */
class Orm {
    
    /**
     * Handle für die Basis-Datenbank
     * @var \PDO
     */
    private $basedb;
    
    /**
     * Dem Konstruktor muss das Datenbank-Handle für die Basis-Systemdatenbank
     * übergeben werden.
     * @param \PDO $dbhandle
     */
    public function __construct(\PDO $dbhandle) {
        $this->basedb = $dbhandle;
    }
    
    
    // <editor-fold desc="Settings">

    /**
     * Liest einen Systemeinstellungs-Datensatz aus
     * @param string $name Name der gesuchten Einstellung
     * @return \Ubergeek\NanoCm\Setting Die gesuchte Einstellung oder null
     */
    public function getSetting(string $name) {
        $sql = 'SELECT * FROM setting WHERE name = :name ';
        $stmt = $this->basedb->prepare($sql);
        $stmt->bindValue('name', $name);
        $stmt->execute();
        
        if (($row = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
            $setting = new Setting();
            $setting->key = $row['name'];
            $setting->value = $row['setting'];
            $setting->params = $row['params'];
            return $setting;
        }
        
        return null;
    }
    
    /**
     * Gibt (nur) den String-Wert einer Systemeinstellung zurück.
     * Ist die angeforderte Einstellung nicht definiert, kann über den optionalen
     * zweiten Parameter bestimmt werden, welcher Wert in diesem Fall zurück
     * gegeben werden soll.
     * @param string $name Name der gesuchten Einstellung
     * @param type $default Optionaler Standard-Rückgabewert
     * @return mixed Der gesuchte Wert oder der vorgegebene Standard-Wert
     */
    public function getSettingValue(string $name, $default = null) {
        $setting = $this->getSetting($name);
        if ($setting == null) return $default;
        return $setting->value;
    }
    
    /**
     * Gibt (nur) die Parameter einer Systemeinstellung zurück.
     * Ist die angeforderte Einstellung nicht definiert, kann über den optionalen
     * zweiten Parameter bestimmt werden, welcher Wert in diesem Fall zurück
     * gegeben werden soll.
     * @param string $name Name der gesuchten Einstellung
     * @param type $default Optionaler Standard-Rückgabewert
     * @return mixed Der gesuchte Wert oder der vorgegebene Standard-Wert
     */
    public function getSettingParams(string $name, $default = null) {
        $setting = $this->getSetting($name);
        if ($setting == null) return $default;
        return $setting->params;
    }
    
    // </editor-fold>
    
    
    // <editor-fold desc="User">
    
    /**
     * Durchsucht die Benutzerdatenbank nach flexiblen Filterkriterien
     * @param array $filter = null
     * @return array Liste der gefundenen Benutzerdatensätze
     */
    public function searchUsers(array $filter = null) {
        // TODO Implementieren
    }

    /**
     * Gibt - falls vorhanden - den Benutzer-Datensatz mit der angegebenen ID
     * zurück
     * 
     * Kann der angefordrte Benutzer-Datensatz nicht gefunden werden, so wird
     * NULL zurück gegeben.
     * @param int $id
     * @return User Gesuchter Benutzer-Datensatz oder NULL
     */
    public function getUserById(int $id) {
        $stmt = $this->basedb->prepare('
            SELECT * FROM User WHERE id = :userid
        ');
        $stmt->bindValue('userid', $id);
        $stmt->execute();
        
        return User::fetchFromPdoStmt($stmt);
    }
    
    /**
     * Gibt - falls vorhanden - den Benutzer-Datensatz mit dem angegebenen
     * Benutzernamen zurück
     * 
     * Kann der angeforderte Benutzer-Datensatz nicht gefunden werden, so wird
     * NULL zurück gegeben.
     * @param string $username
     * @return User Gesuchter Benutzer-Datensatz oder NULL
     */
    public function getUserByUsername(string $username) {
        $stmt = $this->basedb->prepare('
            SELECT * FROM User WHERE username = :username
        ');
        $stmt->bindValue('userid', $username);
        $stmt->execute();
        
        return User::fetchFromPdoStmt($stmt);
    }

    /**
     * Gibt - sofern Benutzername und Passwort mit den Werten in der Datenbank
     * übereinstimmen - den gesuchten Benutzer-Datensatz zurück
     * 
     * Kann der Datensatz nicht gefunden werden oder stimmen übergebenes und
     * gespeichertes Passwort nicht überein, so wird NULL zurück gegeben. Der
     * Grund für eine nicht erfolgreiche Abfrage wird nicht mitgeteilt.
     * 
     * @param string $username Gesuchter Benutzername
     * @param string $passwd Eingegebenes bzw. bekanntes Passwort
     * @return User Gesuchter Benutzer-Datensatz oder NULL
     */
    public function getUserByCredentials(string $username, string $passwd) {
        // TODO Implementieren
    }
    
    /**
     * Speichert den übergebenen Benutzer-Datensatz in der Datenbank
     * @param \Ubergeek\NanoCm\User $user
     */
    public function saveUser(User $user) {
        // TODO Implementieren
    }
    
    // </editor-fold>
    
    
    // <editor-fold desc="Article">
    
    public function searchArticles(array $filter = null) {
        // TODO Implementieren
    }
    
    public function getArticleById(int $id) {
        // TODO Implementieren
    }
    
    public function saveArticle(Article $article) {
        // TODO Implementieren
    }
    
    // </editor-fold>
    
    
    // <editor-fold desc="Shortcut methods">
    
    /**
     * Gibt den Copyright-Hinweis / die Footer-Notiz für die Website zurück
     * @return string
     */
    public function getCopyrightNotice() {
        return $this->getSettingValue(Constants::SETTING_SYSTEM_COPYRIGHTNOTICE, '');
    }
    
    /**
     * Gibt den Standard-Seitentitel zurück
     */
    public function getPageTitle() {
        return $this->getSettingValue(Constants::SETTING_SYSTEM_PAGETITLE);
    }
    
    // </editor-fold>
}