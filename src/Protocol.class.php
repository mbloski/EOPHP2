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
class Protocol {
    const COMMA = "\xFF";
    const NULL = "\xFE";

    const F = array(
        "Connection" => 1,
        "Account" => 2,
        "Character" => 3,
        "Login" => 4,
        "Welcome" => 5,
        "Walk" => 6,
        "Face" => 7,
        "Chair" => 8,
        "Emote" => 9,
        "Attack" => 11,
        "Spell" => 12,
        "Shop" => 13,
        "Item" => 14,
        "StatSkill" => 16,
        "Global" => 17,
        "Talk" => 18,
        "Warp" => 19,
        "JukeBox" => 21,
        "Players" => 22,
        "Avatar" => 23,
        "Party" => 24,
        "Refresh" => 25,
        "NPC" => 26,
        "AutoRefresh" => 27,
        "NPCAutoRefresh" => 28,
        "Appear" => 29,
        "PaperDoll" => 30,
        "Effect" => 31,
        "Trade" => 32,
        "Chest" => 33,
        "Door" => 34,
        "Message" => 35,
        "Bank" => 36,
        "Locker" => 37,
        "Barber" => 38,
        "Guild" => 39,
        "Sit" => 41,
        "Recover" => 42,
        "Board" => 43,
        "Arena" => 45,
        "Priest" => 46,
        "Marriage" => 47,
        "AdminInteract" => 48,
        "Citizen" => 49,
        "Quest" => 50,
        "Book" => 51,
        "Init" => 255
    );

    
    const A = array(
        "Request" => 1,
        "Accept" => 2,
        "Reply" => 3,
        "Remove" => 4,
        "Agree" => 5,
        "Create" => 6,
        "Add" => 7,
        "Player" => 8,
        "Take" => 9,
        "Use" => 10,
        "Buy" => 11,
        "Sell" => 12,
        "Open" => 13,
        "Close" => 14,
        "Message" => 15,
        "Spec" => 16,
        "Admin" => 17,
        "List" => 18,
        "Tell" => 20,
        "Report" => 21,
        "Announce" => 22,
        "Server" => 23,
        "Drop" => 24,
        "Junk" => 25,
        "Get" => 27,
        "Kick" => 28,
        "TargetNPC" => 31,
        "Exp" => 33,
        "Ping" => 240,
        "Pong" => 241,
        "Net3" => 242,
        "Init" => 255
    );

    const INIT_OUTDATED = 0;
    const INIT_OK = 1;
    const INIT_BANNED = 2;

    const LOGIN_WRONG_USER = 1;
    const LOGIN_WRONG_PASSWORD = 2;
    const LOGIN_OK = 3;
    const LOGIN_BANNED = 4;
    const LOGIN_LOGGED_IN = 5;
    const LOGIN_SERVER_BUSY = 6;

    const DIRECTION_DOWN = 0;
    const DIRECTION_LEFT = 1;
    const DIRECTION_UP = 2;
    const DIRECTION_RIGHT = 3;

    const EMOTE_HAPPY = 1;
    const EMOTE_DEPRESSED = 2;
    const EMOTE_SAD = 3;
    const EMOTE_ANGRY = 4;
    const EMOTE_CONFUSED = 5;
    const EMOTE_SURPRISED = 6;
    const EMOTE_HEARTS = 7;
    const EMOTE_MOON = 8;
    const EMOTE_SUICIDAL = 9;
    const EMOTE_EMBARASSED = 10;
    const EMOTE_DRUNK = 11;
    const EMOTE_TRADE = 12;
    const EMOTE_LEVELUP = 13;
    const EMOTE_PLAYFUL = 14;

    public static function DecodeInteger($bytes) {
        if (strlen($bytes) > 4) {
            return -1;
        }

        $b1 = ord($bytes[0] ?? Protocol::NULL);
        $b2 = ord($bytes[1] ?? Protocol::NULL);
        $b3 = ord($bytes[2] ?? Protocol::NULL);
        $b4 = ord($bytes[3] ?? Protocol::NULL);

        if ($b1 == 254) $b1 = 1;
        if ($b2 == 254) $b2 = 1;
        if ($b3 == 254) $b3 = 1;
        if ($b4 == 254) $b4 = 1;

        if ($b1 == 0) $b1 = 128;
        if ($b2 == 0) $b2 = 128;
        if ($b3 == 0) $b3 = 128;
        if ($b4 == 0) $b4 = 128;

        --$b1;
        --$b2;
        --$b3;
        --$b4;

        return ($b4 * 16194277 + $b3 * 64009 + $b2 * 253 + $b1);
    }

    public static function EncodeInteger($number, $size = 1) {
        $bytes = '';

        for($i = 1; $i <= $size; $i++) {
            $bytes .= Protocol::NULL;
        }

        $onumber = $number;

        if ($onumber >= 16194277) {
            $bytes[3] = chr($number / 16194277 + 1);
            $number = $number % 16194277;
        }

        if ($onumber >= 64009) {
            $bytes[2] = chr($number / 64009 + 1);
            $number = $number % 64009;
        }

        if ($onumber >= 253) {
            $bytes[1] = chr($number / 253 + 1);
            $number = $number % 253;
        }

        $bytes[0] = chr($number + 1);

        return $bytes;
    }

    public static function Dwind($str, $multi) {
        $newstr = '';
        $length = strlen($str);

        $buffer = '';

        if ($multi == 0) return $str;

        for ($i = 0; $i < $length; ++$i) {
            $c = ord($str[$i]);

            if ($c % $multi == 0) {
                $buffer .= chr($c);
            } else {
                if (strlen($buffer) > 0) {
                    $newstr .= strrev($buffer);
                    $buffer = '';
                }

                $newstr .= chr($c);
            }
        }

        if (strlen($buffer) > 0) {
            $newstr .= strrev($buffer);
        }

        return $newstr;
    }

    public static function Encode($packet) {
        if (strlen($packet) < 1)
            return false;

        $packet = str_split($packet);
        foreach ($packet as &$char){
            $char = ord($char);
            $char -= 128;
            if ($char < 0)
                $char = 256 + $char;
        }
        $newpacket = array();
        $i = 0;
        $j = 0;
        while ($i < count($packet)){
            $newpacket[$i] = $packet[$j];
            $i += 2;
            $j++;
        }
        $i -= 3;
        if(!(count($packet) % 2))
            $i = count($packet)-1;
        while ($i > 0){
            $newpacket[$i] = $packet[$j];
            $i -= 2;
            $j++;
        }
        ksort($newpacket);
        foreach ($newpacket as &$char) {
            if ($char == 128) {
                $char = 0;
            } elseif ($char == 0) {
                $char = 128;
            }

            $char = chr($char);
        }

        return implode('',$newpacket);
    }

    public static function Decode($packet) {
        if (strlen($packet) < 1)
            return false;
        $packet = str_split($packet);
        foreach ($packet as &$char){
            $char = ord($char);
            $char -= 128;
            if ($char < 0)
                $char = 256 + $char;
        }
        $newpacket = array();
        $i = 0;
        while ($i < count($packet)){
            $newpacket[] = $packet[$i];
            $i += 2;
        }
        $i -= 1;
        if(count($packet) % 2)
            $i = count($packet)-2;
        while ($i > 0){
            $newpacket[] = $packet[$i];
            $i -= 2;
        }
        foreach ($newpacket as &$char) {
            if ($char == 128) {
                $char = 0;
            } elseif ($char == 0) {
                $char = 128;
            }

            $char = chr($char);
        }

        $finalpacket = implode('',$newpacket);

        return $finalpacket;
    }

    public static function timestamp() {
        return (date('H') * 3600) + (date('i') * 60) + (date('s') * 100) + (intval(explode(' ', microtime())[0] * 100));
    }
}
