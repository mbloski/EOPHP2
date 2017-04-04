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
class Packet {
    private $action;
    private $family;
    private $data;

    private $pos;

    function __construct($data) {
        if (strlen($data) < 3) {
            throw new Exception('Received empty packet');
        }

        $this->action = ord($data[0]);
        $this->family = ord($data[1]);

        $this->data = substr($data, 2);
        $pos = 0;
    }

    public function get_type() {
        return new PacketType($this->action, $this->family);
    }

    public function get_length() {
        return strlen($this->data);
    }

    public function get_int($size) {
        if ($size > 4) {
            return -1;
        }

        $bytes = substr($this->data, $this->pos, $size);
        $this->pos += $size;

        if ($this->pos > strlen($this->data)) {
            $this->pos = strlen($this->data);
            return -1;
        }

        return Protocol::DecodeInteger($bytes);
    }

    public function ignore($len) {
        if (strlen($this->data) < $this->pos + $len) {
            $this->pos = strlen($this->data);
            return;
        }

        $this->pos += $len;
    }

    public function get_bytes($size = 1, $setpos = true) {
        $b = substr($this->data, $this->pos, $size);

        if ($setpos) {
            $this->pos += $size;
            if ($this->pos > strlen($this->data)) {
                $this->pos = strlen($this->data);
            }
        }

        return $b;
    }

    public function get_string() {
        $b = explode(Protocol::COMMA, substr($this->data, $this->pos));
        $this->pos += strlen($b[0]) + 1;
        return $b[0];
    }

    public function bytes_left() {
        return strlen($this->data) - $this->pos;
    }

    public function get_data() {
        return $this->data;
    }

    public function set_pos($pos) {
        $this->pos = $pos;
    }

    public function pretty_data() {
        return implode('_', array_map(function($c) { return strval(ord($c)); }, str_split($this->data)));
    }
}
