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
class Bot {
    private $conn;
    private $packet_processor;
    private $player_id;

    public $characters;

    private $host;
    private $port;

    private $modules;

    private $cluster;

    function __construct($host, $port, $core_args) {
        $this->modules = array();

        $this->host = $host;
        $this->port = $port;
        $this->cluster = $core_args['cluster'] ?? null;
        Output::Info('Spawning Bot instance ('.$host.':'.$port.')');

        try {
            $this->conn = new SocketClient($host, $port);
        } catch (Exception $e) {
            Output::Error('Couldn\'t connect to '.$host.':'.$port.' - '.$e->getMessage());
            exit(1);
        }

        $this->packet_processor = new PacketProcessor($this->conn);
        $this->characters = array();

        $this->LoadModule('CoreModule', $core_args);
    }

    public function LoadModule($module, $args = array()) {
        $file = __DIR__.'/Modules/'.$module.'/'.$module.'.class.php';

        if (is_file($file)) {
            require_once($file);
        }

        try {
            $this->modules[] = new $module($this, $args);
            return true;
        } catch (Exception $e) {
            Output::Error('Couldn\'t load module "' . $module . '": ' . $e->getMessage());
        }

        return false;
    }

    public function UnloadModule($module) {
        foreach ($this->modules as $k => $mod) {
            if (is_a($mod, $module)) {
                unset($this->modules[$k]);
                return true;
            }
        }

        Output::Error('Couldn\'t unload module "'.$module.'": module not loaded');
        return false;
    }

    public function GetModule($module_name) {
        foreach ($this->modules as $module) {
            if ($module->get_name() == $module_name) {
                return $module;
            }
        }

        return null;
    }

    public function GetModuleList() {
        $ret = array();
        foreach ($this->modules as $module) {
            $ret[] = $module->get_name();
        }

        return $ret;
    }

    public function IsLoaded($module_name) {
        foreach ($this->modules as $module) {
            if (strtolower($module->get_name()) == strtolower($module_name)) {
                return true;
            }
        }

        return false;
    }

    public function set_init($d_multi, $e_multi, $seq, $player_id) {
        $this->packet_processor->set_encoding($d_multi, $e_multi, $seq);
        $this->player_id = $player_id;
    }

    public function set_seq($new_seq) {
        $this->packet_processor->set_seq($new_seq);
    }

    public function send_packet($family, $action, $payload) {
        return $this->conn->send($this->packet_processor->s_process(new PacketType($family, $action), $payload));
    }

    public function get_player_id() {
        return $this->player_id;
    }

    public function get_characters() {
        return $this->characters;
    }

    public function get_host() {
        $ret = '';
        if ($this->IsLoaded('CoreModule')) $ret .= $this->GetModule('CoreModule')->GetCharacterName().'@';
        $ret .= $this->host.':'.strval($this->port);

        return $ret;
    }

    private function route_packet($packet) {
        foreach ($this->modules as $module) {
            $callback = array($module, $packet->get_type()->name);
            if (is_callable($callback)) {
                $callback($packet);
                $packet->set_pos(0);
            }
        }
    }

    public function tick() {
        $bytes = $this->conn->recv();
        if (!empty($bytes)) {
            $packet = $this->packet_processor->r_process($bytes);
            $this->route_packet($packet);
        }

        if (!$this->IsLoaded('CoreModule')) {
            /* Panic! */
            return;
        }

        if ($this->GetModule('CoreModule')->GetPlayerState() == CoreModule::STATE_IN_GAME) {
            foreach ($this->modules as $module) {
                $module->Tick();
            }
        }

        usleep(50000);
    }

    public function GetCluster() {
        return $this->cluster;
    }

    public function Disconnect() {
        return $this->conn->close();
    }
}
