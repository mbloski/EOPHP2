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
class BotCluster {
    private $bots = array();
    private $controlserver;

    function __construct() {

    }

    public function AttachControlServer($bindhost, $port) {
        $this->controlserver = new ControlServer($bindhost, $port, $this);
    }

    public function Add($host, $port, $user, $pass, $char, $eoversion, $hdid) {
        $this->bots[] = new Bot($host, $port, [
            'username' => $user,
            'password' => $pass,
            'character' => $char,
            'eo_version' => $eoversion,
            'hdid' => $hdid
        ]);
    }

    public function Get($host) {
        foreach ($this->bots as $bot) {
            if ($host === $bot->get_host()) {
                return $bot;
            }
        }

        return null;
    }

    public function Remove($name) {
        if (isset($this->bots[$name]) && is_a($this->bots[$name], 'Bot')) {
            $this->bots[$name]->Disconnect();
            unset($this->bots[$name]);
            return true;
        }

        return false;
    }

    public function GetCount() {
        return count($this->bots);
    }

    public function GetBots() {
        return $this->bots;
    }

    public function Tick() {
        foreach ($this->bots as $k => $bot) {
            if (!$bot->IsLoaded('CoreModule') || $bot->GetModule('CoreModule')->GetPlayerState() == CoreModule::STATE_DEAD) {
                unset($this->bots[$k]);
                continue;
            }

            try {
                $bot->tick();
            } catch (Exception $e) {
                Output::Error($e->getMessage());
                unset($this->bots[$k]);
            }

            if (is_a($this->controlserver, 'ControlServer'))
                $this->controlserver->tick();
        }
    }
}
