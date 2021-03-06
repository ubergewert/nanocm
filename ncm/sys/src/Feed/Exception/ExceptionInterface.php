<?php
/**
 * NanoCM
 * Copyright (C) 2017 - 2018 André Gewert <agewert@ubergeek.de>
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

namespace Ubergeek\Feed\Exception;

/**
 * Gemeinsames Markierungs-Interface für Exception innerhalb des Feed-Namespaces.
 *
 * Basisklasse bleiben für die konkreten Exception-Ableitungen weiterhin die SPL-Exceptions.
 * Zusätzlich für die Unterscheidbarkeit dann das passende Interface implementiert.
 *
 * @package Ubergeek\Feed\Exception
 * @author André Gewert <agewert@ubergeek.de>
 * @created 2018-11-09
 */
interface ExceptionInterface {

}