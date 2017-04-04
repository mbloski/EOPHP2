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

/* TODO: refactor all this */

class EOCharacter {
    public $name;
    public $id;
    public $level;
    public $gender;
    public $hairstyle;
    public $haircolor;
    public $race;
    public $admin;
    public $paperdoll;
    public $inventory;

    public $map_id;
    public $map_x;
    public $map_y;
    public $direction;
    public $guild;
    public $maxhp;
    public $hp;
    public $maxtp;
    public $tp;

    function __construct() {
        $this->paperdoll = new EOPaperdoll();
        $this->inventory = new EOInventory();
    }
}

class EOInventory {
    private $items;

    function __construct() {
        $this->items = array();
    }

    public function add($id, $amount) {
        if (isset($this->items[$id])) {
            $this->items[$id] += $amount;
        } else {
            $this->items[$id] = $amount;
        }
    }

    public function get_amount($id) {
        return $this->items[$id] ?? 0;
    }
}

class EOPaperdoll
{
    public $boots;
    public $gloves;
    public $belt;
    public $armor;
    public $necklace;
    public $hat;
    public $shield;
    public $weapon;
    public $ring1;
    public $ring2;
    public $armlet1;
    public $armlet2;
    public $bracer1;
    public $bracer2;

    function __construct($boots = 0, $gloves = 0, $belt = 0, $armor = 0, $necklace = 0, $hat = 0, $shield = 0, $weapon = 0, $ring1 = 0, $ring2 = 0, $armlet1 = 0, $armlet2 = 0, $bracer1 = 0, $bracer2 = 0)
    {
        $this->boots = $boots;
        $this->gloves = $gloves;
        $this->belt = $belt;
        $this->armor = $armor;
        $this->necklace = $necklace;
        $this->hat = $hat;
        $this->shield = $shield;
        $this->weapon = $weapon;
        $this->ring1 = $ring1;
        $this->ring2 = $ring2;
        $this->armlet1 = $armlet1;
        $this->armlet2 = $armlet1;
        $this->bracer1 = $bracer1;
        $this->bracer2 = $bracer2;
    }
}
