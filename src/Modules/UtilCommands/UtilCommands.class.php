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
class UtilCommands extends EOPHPModule {
    private $prefix;
    private $whitelist;

    private $latency_check;

    function Initialize($args) {
        $this->prefix = '#';

        if (!isset($args['whitelist']) || !is_array($args['whitelist'])) {
            $args['whitelist'] = array();
        }

        $this->whitelist = $args['whitelist'];
    }

    function Uninitialize() {

    }

    private function command_uptime($issuer, $args) {
        $this->Say('I\'ve been up for '.Util::TimeDiff(time(), $this->init_time));
    }

    private function command_ping($issuer, $args) {
        $this->Latency();
        $this->latency_check = microtime(true);
    }

    private function command_lvl($issuer, $args) {
        if (!isset($args[1]) || !ctype_alnum($args[1]) || strlen($args[1]) < 4) {
            $this->Emote(Protocol::EMOTE_CONFUSED);
            return;
        }

        $name = strtolower(substr($args[1], 0, 12));

        foreach ($this->bot->characters as $char) {
            if ($char->name == $name) {
                $this->PrivateMessage($issuer, ucfirst($name).' is level '.$char->level);
                return;
            }
        }

        $this->PrivateMessage($issuer, 'I don\'t know');
    }

    private function command_about($issuer, $args) {
        $this->Say('EOPHP2 codename Xhonteb. michael@blo.ski 2017; Running PHP '.PHP_VERSION);
    }

    private function vipcommand_terminate($issuer, $args) {
        $this->bot->UnloadModule('CoreModule');
    }

    function Player_Talk($packet) {
        $id = $packet->get_int(2);
        $message = $packet->get_string();
        $message_split = explode(' ', $message);

        if (substr($message_split[0], 0, strlen($this->prefix)) == $this->prefix) {
            $cmd = strtolower(substr($message_split[0], strlen($this->prefix)));
            $callback_normal = array($this, 'command_'.$cmd);
            $callback_vip = array($this, 'vipcommand_'.$cmd);
            if (is_callable($callback_normal)) {
                $callback_normal($this->bot->characters[$id]->name, $message_split);
            }

            if (is_callable($callback_vip) && in_array($this->bot->characters[$id]->name, $this->whitelist)) {
                $callback_vip($this->bot->characters[$id]->name, $message_split);
            }
        }
    }

    function Pong_Message() {
        $ms = round((microtime(true) - $this->latency_check) * 1000);
        $this->Say('Current ping to server: '.$ms.' ms');
    }
}
