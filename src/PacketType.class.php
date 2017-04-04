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
class PacketType {
    private $action;
    private $family;
    private $name;

    function __construct($action, $family) {
        $this->action = $action;
        $this->family = $family;

        $action_name = array_search($this->action, Protocol::A);
        $family_name = array_search($this->family, Protocol::F);
        $this->name = $action_name.'_'.$family_name;
    }

    public function __get($prop) {
        return isset($this->$prop)? $this->$prop : null;
    }
}
