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

// Zentrale Instanz des Content Managers instanziieren

// Standard-Controller ausführen

require 'ncm/sys/src/autoload.php';
//$var = new Ubergeek\NanoCm\FrontController(substr(__DIR__, strlen('/data')));
$var = new Ubergeek\NanoCm\FrontController(__DIR__);
$var->execute();
