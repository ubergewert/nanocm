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

namespace Ubergeek\Log;

/**
 * Einfache Implementierung des Log-Interfaces
 */
class Logger implements LoggerInterface {
    
    const EMERG  = 0;
    const ALERT  = 1;
    const CRIT   = 2;
    const ERR    = 3;
    const WARN   = 4;
    const NOTICE = 5;
    const INFO   = 6;
    const DEBUG  = 7;

    private $writers = array();
    
    /**
     * Dem Konstruktor können optional beliebig viele Writer-Instanzen
     * übergeben werden
     * @param \Ubergeek\Log\Writer\WriterInterface $writers
     */
    public function __construct($writers = null) {
        if (is_array($writers)) {
            foreach ($writers as $writer) {
                $this->addWriter($writer);
            }
        } elseif ($writers instanceof Writer\WriterInterface) {
            $this->addWriter($writers);
        }
    }
    
    /**
     * Fügt dieser Logger-Instanz die übergebenen Writer hinzu
     * @param \Ubergeek\Log\Writer\WriterInterface $writer
     */
    public function addWriter(Writer\WriterInterface $writer) {
        array_push($this->writers, $writer);
    }
    
    public function closeWriters() {
        if (is_array($this->writers)) {
            foreach ($this->writers as $writer) {
                $writer->close();
            }
        }
    }
    
    public function flushWriters() {
        if (is_array($this->writers)) {
            foreach ($this->writers as $writer) {
                $writer->flush();
            }
        }
    }
    
    public function log(int $priority, $data, \Exception $ex = null, array $backtrace = null, string $line = '') {
        if (!is_array($this->writers) || count($this->writers) == 0) return;
        
        if ($backtrace == null) {
            $backtrace = debug_backtrace();
        }
        if ($line == null) {
            $line = "unbekannt";
        }
        
        $event = new Event($priority, $data, $ex, $backtrace, $line);
        $event->priority = $priority;
        
        foreach ($this->writers as $writer) {
            $writer->write($event);
        }
    }

    public function emerg($data, \Exception $ex = null, array $backtrace = null, string $line = '') {
        $this->log(self::EMERG, $data, $ex, $backtrace, $line);
    }
    
    public function alert($data, \Exception $ex = null, array $backtrace = null, string $line = '') {
        $this->log(self::ALERT, $data, $ex, $backtrace, $line);
    }
    
    public function crit($data, \Exception $ex = null, array $backtrace = null, string $line = '') {
        $this->log(self::CRIT, $data, $ex, $backtrace, $line);
    }
    
    public function err($data, \Exception $ex = null, array $backtrace = null, string $line = '') {
        $this->log(self::ERR, $data, $ex, $backtrace, $line);
    }
    
    public function warn($data, \Exception $ex = null, array $backtrace = null, string $line = '') {
        $this->log(self::WARN, $data, $ex, $backtrace, $line);
    }
    
    public function notice($data, \Exception $ex = null, array $backtrace = null, string $line = '') {
        $this->log(self::NOTICE, $data, $ex, $backtrace, $line);
    }
    
    public function info($data, \Exception $ex = null, array $backtrace = null, string $line = '') {
        $this->log(self::INFO, $data, $ex, $backtrace, $line);
    }
    
    public function debug($data, \Exception $ex = null, array $backtrace = null, string $line = '') {
        $this->log(self::DEBUG, $data, $ex, $backtrace, $line);
    }
}