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

class Commands extends EOPHPModule
{
    private $database;
    private $operators;

    public function Initialize($args) {
        $this->Output('Commands!');

        $dbfile = $args['dbfile'] ?? '/var/databases/commands.sqlite';
        $this->operators = $args['operators'] ?? [];

        $this->database = new PDO('sqlite:' . $dbfile);
        if (!$this->database->query('SELECT 1 FROM commands LIMIT 1')) {
            $this->InitDb();
        }
    }

    private function InitDb() {
        $query = 'CREATE TABLE commands (id integer PRIMARY KEY, registrar varchar(16) NOT NULL, command varchar(32) NOT NULL, value varchar(120) NOT NULL, deleted tinyint DEFAULT 0, created real DEFAULT (datetime(\'now\', \'localtime\')))';
        $this->database->exec($query);
    }

    private function commandExists($command) {
        $query = 'SELECT COUNT(*) FROM commands WHERE command = :command AND deleted = 0';
        $s = $this->database->prepare($query);
        $s->execute([':command' => $command]);
        $ret = $s->fetch();
        return $ret['COUNT(*)'] > 0;
    }

    private function commandBanned($command) {
        $query = 'SELECT COUNT(*) FROM commands WHERE command = :command AND value = :value';
        $s = $this->database->prepare($query);
        $s->execute([':command' => $command, ':value' => '#BAN']);
        $ret = $s->fetch();
        return $ret['COUNT(*)'] > 0;
    }

    private function commandCount() {
        $query = 'SELECT COUNT(*) FROM commands WHERE deleted = 0';
        $s = $this->database->prepare($query);
        $s->execute();
        $ret = $s->fetch();
        return $ret['COUNT(*)'];
    }

    private function deleteAll($registrar) {
        $query = 'UPDATE commands SET deleted = 1 WHERE registrar = :registrar AND deleted = 0';
        $s = $this->database->prepare($query);
        $s->execute([':registrar' => $registrar]);
        return $s->rowCount();
    }

    private function unregisterCommand($cmd) {
        $query = 'UPDATE commands SET deleted = 1 WHERE command = :command';
        $s = $this->database->prepare($query);
        $s->execute([':command' => $cmd]);
        return $s->rowCount() > 0;
    }

    private function registerCommand($nickname, $command, $value, $deleted = false) {
        $query = 'INSERT INTO commands(registrar, command, value, deleted) VALUES(:registrar, :command, :value, :deleted)';
        $s = $this->database->prepare($query);
        $ret = $s->execute([':registrar' => $nickname, ':command' => $command, ':value' => $value, ':deleted' => (int)$deleted]);
	return (bool)$ret;
    }

    private function getCommand($cmd) {
        $query = 'SELECT * FROM commands WHERE command = :cmd AND deleted = 0';
        $s = $this->database->prepare($query);
        $s->execute([':cmd' => $cmd]);

        $ret = $s->fetch();

        if (empty($ret)) {
            return null;
        }

        return $ret;
    }

    public function Player_Talk($packet) {
        $id = $packet->get_int(2);
        $message = strtolower($packet->get_string());
        $ex = explode(' ', $message);
        $name = strtolower($this->bot->characters[$id]->name);

        if ($ex[0][0] === '#') {
           $cmd = strtolower(substr($ex[0], 1));
           $valcmd = $this->getCommand($cmd);
           if ($valcmd) {
               $this->Say('[#'.$cmd.'] '.$valcmd['value']);
           }
        }

        if ($ex[0] === '#register') {
            if (!isset($ex[1]) || strlen($ex[1]) < 3) {
                $this->Emote(9);
                return;
            }

            $cmd = strtolower($ex[1]);

            if ($this->commandExists($cmd) || $this->commandBanned($cmd)) {
                $this->Emote(9);
                return;
            }

            $tokval = $ex;
            array_shift($tokval);
            array_shift($tokval);
            $valcmd = implode(' ', $tokval);

            if (strlen($valcmd) < 6) {
                $this->Emote(9);
                return;
            }

            if ($this->registerCommand($name, $cmd, $valcmd)) {
                $this->Emote(1);
            } else {
                $this->Emote(9);
            }

        }

        if ($ex[0] === '#unregister') {
            if (!isset($ex[1])) {
                $this->Emote(9);
                return;
            }

            $cmd = $this->getCommand(strtolower($ex[1]));
            if ($cmd && $cmd['registrar'] == $name || $cmd['command'] == $name || in_array($name, $this->operators)) {
                if ($this->unregisterCommand($cmd['command'])) {
                    $this->Emote(1);
                } else {
                    $this->Emote(9);
                }
            }
        }

        if ($ex[0] === '#bancmd') {
            if (!isset($ex[1]) || strlen($ex[1]) < 3) {
                $this->Emote(9);
                return;
            }

            $cmd = strtolower($ex[1]);

            $this->unregisterCommand($cmd);
            if ($this->registerCommand($name, $cmd, '#BAN', true)) {
                $this->Emote(1);
            } else {
                $this->Emote(9);
            }

        }

        if ($ex[0] === '#registrar' && in_array($name, $this->operators)) {
            $cmd = $this->getCommand(strtolower($ex[1]));
            if ($cmd) {
                $this->Say('[I] [#'.strtolower($ex[1]).'] registered by '.ucfirst($cmd['registrar']).' on '.$cmd['created'].'.');
            } else {
                $this->Say('[I] [#'.strtolower($ex[1]).'] not registered');
            }
        }

        if ($ex[0] === '#delall' && in_array($name, $this->operators)) {
            $deleted = $this->deleteAll(strtolower($ex[1]));
            $this->Say('Deleted '.$deleted.' '.(($deleted == 1)? 'command' : 'commands').'.');
        }

        if ($ex[0] === '#commands') {
            $count = $this->commandCount();
            $this->Say('There '.(($count == 1)? 'is' : 'are').' '.$count.' '.(($count == 1)? 'command' : 'commands').' registered.');
        }
    }

}
