<?php
/*
 *  EOPHP2 - A modular bot for EO
 *  Copyright (C) 2017  bloski
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
class Util {
    public static function TimeDiff($start, $s) {
        $string = '';
        $t = array(
            //'y' => 31536000,
            'w' => 604800,
            'd' => 86400,
            'h' => 3600,
            'm' => 60,
        );

        $s = abs($s - $start);
        foreach($t as $key => &$val) {
            $$key = floor($s / $val);
            $s -= ($$key*$val);
            $string .= ($$key == 0) ? '' : $$key."$key ";
        }

        return $string.$s.'s';
    }

    public static function Clamp($num, $min, $max) {
        return max($min, min($max, $num));
    }
};
?>
