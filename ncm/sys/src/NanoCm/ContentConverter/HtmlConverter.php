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

namespace Ubergeek\NanoCm\ContentConverter;

/**
 * Konvertiert den mit Auszeichnungselementen versehenen Eingabe-String nach
 * HTML
 * @author André Gewert <agewert@ubergeek.de>
 * @created 2017-11-04
 */
class HtmlConverter extends DecoratedContentConverter {
    
    public function convertFormattedText(\Ubergeek\NanoCm\NanoCm $nanocm, string $input): string {
        if ($this->decoratedConverter !== null) {
            $input = $this->decoratedConverter->convertFormattedText($nanocm, $input);
        }
        return '<p>' . $nanocm->htmlEncode($input) . '</p>';
    }

}