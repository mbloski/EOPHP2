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
class PacketProcessor {
    private $d_multi;
    private $e_multi;
    private $seq;
    private $seq_counter;

    private $encoded;

    function __construct() {
        $this->encoded = false;
    }

    public function set_encoding($d_multi, $e_multi, $seq) {
        $this->d_multi = $d_multi;
        $this->e_multi = $e_multi;
        $this->set_seq($seq);
        $this->seq_counter = 0;
    }

    public function set_seq($new_seq) {
        $this->seq = $new_seq;
    }

    private function next_sequence() {
        if (++$this->seq_counter >= 10) {
            $this->seq_counter = 0;
        }

        return $this->seq + $this->seq_counter;
    }

    public function s_process(PacketType $type, Array $ar) {
        $bytes = implode('', $ar);

        $packet = chr($type->family).chr($type->action);
        if ($type->action != Protocol::A['Init'] || $type->family != Protocol::F['Init']) {
            $this->encoded = true;
            $packet .= Protocol::EncodeInteger($this->next_sequence());
        }

        $packet .= $bytes;

        if ($type->action != Protocol::A['Init'] || $type->family != Protocol::F['Init']) {
            if (!isset($this->e_multi)) {
                throw new Exception('Tried to write encoded packet before setting encoding');
            }

            $packet = Protocol::Encode(Protocol::Dwind($packet, $this->e_multi));
        }

        $packet = Protocol::EncodeInteger(strlen($packet), 2).$packet;

        return $packet;
    }

    public function r_process($bytes) {
        if (strlen($bytes) < 3) {
            return null;
        }

        $action = ord($bytes[0]);
        $family = ord($bytes[1]);

        if ($action == Protocol::A['Init'] && $family == Protocol::F['Init']) {
            $this->encoded = false;
            return new Packet($bytes);
        } else {
            $this->encoded = true;
            if (!isset($this->d_multi)) {
                throw new Exception('Tried to read encoded packet before setting encoding');
            }

            $packet = new Packet(Protocol::Dwind(Protocol::Decode($bytes), $this->d_multi));

            return $packet;
        }
    }
}
