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
class EvalCommand extends EOPHPModule {
    private $command;
    private $whitelist;
    private $nickname;
    private $buffer;

    function Initialize($args) {
        if (!isset($args['whitelist']) || !is_array($args['whitelist'])) {
            throw new Exception('"whitelist" not set or is not an array');
        }

        if (!isset($args['command']) || !is_string($args['command'])) {
            throw new Exception('"command" not set or is not a string');
        }

        $this->command = $args['command'];
        $this->whitelist = $args['whitelist'];
        $this->buffer = array();
    }

    function Uninitialize() {

    }

    private function DoEval($cmd) {
        $this->Output($this->nickname.' eval\'d "'.$cmd.'"');

        try {
            eval($cmd);
        } catch (\Error $e) {
            $this->Say('#e: '.ucfirst($e->getMessage()));
        }
    }

    function Player_Talk($packet) {
        $id = $packet->get_int(2);
        $message = $packet->get_string();

        $ex = explode(' ', $message);

        if (substr($message, 0, strlen($ex[0]) + 1) != $this->command.' ') {
            return;
        }

        $this->nickname = $this->bot->characters[$id]->name;
        if (!in_array($this->nickname, $this->whitelist)) {
            return;
        }

        $command = $ex[1];
        $expr = substr($message, strlen($ex[0]) + 1 + strlen($ex[1]) + 1);

        switch ($command) {
            case 'clear':
                $this->buffer = [];
                $this->emote(Protocol::EMOTE_HAPPY);
                break;
            case 'append':
                $this->buffer[] = $expr;
                $this->emote(Protocol::EMOTE_HAPPY);
                break;
            case 'quick':
                $this->DoEval($expr);
                break;
            case 'rmline':
                if (!isset($ex[2])) {
                    $this->emote(Protocol::EMOTE_CONFUSED);
                    return;
                }

                $line = intval($ex[2]);
                if (isset($this->buffer[$line - 1])) {
                    unset($this->buffer[$line - 1]);
                    $this->emote(Protocol::EMOTE_HAPPY);
                } else {
                    $this->emote(Protocol::EMOTE_SUICIDAL);
                }
                break;
            case 'eval':
                if (empty($this->buffer)) {
                    $this->Say($this->command.': buffer is empty');
                } else {
                    $this->DoEval(implode('', $this->buffer));
                }
                break;
            case 'show':
                if (empty($this->buffer)) {
                    $this->Say($this->command.': EMPTY');
                }
                foreach ($this->buffer as $k => $line) {
                    $this->Say(($k+1).': '.$line);
                }
                break;
        }
    }
}
