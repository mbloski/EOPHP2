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

class Logger extends EOPHPModule {
    private $fh;
    private $logfile;

    function Initialize($args) {
        $this->logfile = $args['logfile'] ?? 'CHATLOG.txt';
        $this->fh = fopen($this->logfile, 'a');
        if (!$this->fh) {
            throw new Exception('couldn\'t open log file');
        }
    }

    function Uninitialize() {
        fclose($this->fh);
    }

    private function DoLog($type, $nickname, $message) {
        $line = '[' . date('Y-m-d H:i:s') . '] [' . $type . '] <' . ucfirst($nickname) . '> ' . $message . "\r\n";
        return fwrite($this->fh, $line);
    }

    function Player_Talk($packet) {
        $id = $packet->get_int(2);
        $message = $packet->get_string();
        $nickname = $this->bot->characters[$id]->name;
        $this->DoLog('SCR', $nickname, $message);
    }

    function Message_Talk($packet) {
        $nickname = $packet->get_string();
        $message = $packet->get_string();
        $this->DoLog('GLB', $nickname, $message);
    }

    /* TODO other message types */
}
