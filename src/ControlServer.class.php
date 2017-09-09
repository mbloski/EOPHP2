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
class ControlServer {
    private $server;
    private $botcluster;

    const ResponseCodes = array(
        100 => 'OK',
        101 => 'Argument Expected',
        200 => 'Warning',
        102 => 'No such instance',
        300 => 'Unknown Command',
        501 => 'Failed to load module',
        502 => 'Module not loaded',
        503 => 'Cannot load/unload CoreModule'
    );

    function __construct($bindhost, $bindport, $botcluster) {
        $this->server = new SocketServer($bindhost, $bindport);
        $this->botcluster = $botcluster;

        Output::Info('Serving Bot Control Protocol at '.$bindhost.':'.$bindport);
        $this->server->on_accept(function($client) {
            $this->Respond($client, 100, 'EOPHP2 Controller at your service');
        });

        $this->server->on_read(function($client) {
            $commands = [];
            while (!empty($line = $client->GetLine())) {
                $commands[] = trim($line);
            }

            foreach ($commands as $command) {
                $tok = explode(' ', $command);

                $callback = array($this, 'command_' . strtolower($tok[0]));
                if (is_callable($callback)) {
                    $callback($client, $command);
                } else {
                    $this->Respond($client, 300);
                }
            }
        });

        $this->server->on_close(function($client) {
            $this->Respond($client, 100, 'Bye');
        });
    }

    private function command_list($client, $command) {
        foreach ($this->botcluster->GetBots() as $bot) {
            $res = $bot->get_host().' ';
            $res .= CoreModule::STATE_NAMES[$bot->GetModule('CoreModule')->GetPlayerState()];
            $res .= ' INIT_TIME:'.$bot->GetModule('CoreModule')->get_init_time();
            $this->Respond($client, 100, $res);
        }
    }

    private function command_modules($client, $command) {
        $tok = explode(' ', $command);

        if (!isset($tok[1])) {
            $this->Respond($client, 101);
            return;
        }

        $bot = $this->botcluster->Get($tok[1]);
        if ($bot === null) {
            $this->Respond($client, 102);
            return;
        }

        $this->Respond($client, 100, implode(' ', $bot->GetModuleList()));
    }

    private function command_eval($client, $command) {
        $doeval = function($cmd) use ($client) {
            try {
                eval($cmd);
                $this->Respond($client, 100);
                return 0;
            } catch (\Error $e) {
                $this->Respond($client, 200, ucfirst($e->getMessage()));
                return -1;
            }
        };

        return $doeval(substr($command, 5));
    }

    private function command_loadmodule($client, $command) {
        $tok = explode(' ', $command);

        if (!isset($tok[1]) || !isset($tok[2])) {
            $this->Respond($client, 101);
            return;
        }

        if (strtolower($tok[2]) == 'coremodule') {
            $this->Respond($client, 503);
            return;
        }

        $bot = $this->botcluster->Get($tok[1]);
        if ($bot === null) {
            $this->Respond($client, 102);
            return;
        }

        if ($bot->IsLoaded($tok[2])) {
            $this->Respond($client, 200, 'Warning: Module already loaded. Reloading.');
            $bot->UnloadModule($tok[2]);
        }

        $r = $bot->LoadModule($tok[2]);
        if ($r) {
            $this->Respond($client, 100);
        } else {
            $this->Respond($client, 501);
        }
    }

    private function command_unloadmodule($client, $command) {
        $tok = explode(' ', $command);

        if (!isset($tok[1]) || !isset($tok[2])) {
            $this->Respond($client, 101);
            return;
        }

        if (strtolower($tok[2]) == 'coremodule') {
            $this->Respond($client, 503);
            return;
        }

        $bot = $this->botcluster->Get($tok[1]);
        if ($bot === null) {
            $this->Respond($client, 102);
            return;
        }

        $r = $bot->UnloadModule($tok[2]);
        if ($r) {
            $this->Respond($client, 100);
        } else {
            $this->Respond($client, 502);
        }
    }

    private function command_disconnect($client, $command) {
        $tok = explode(' ', $command);

        if (!isset($tok[1])) {
            $this->Respond($client, 101);
            return;
        }

        $bot = $this->botcluster->Get($tok[1]);
        if ($bot === null) {
            $this->Respond($client, 102);
            return;
        }

        if ($bot->UnloadModule('CoreModule')) {
            $this->Respond($client, 100);
        } else {
            $this->Respond($client, 200);
        }
    }

    private function command_help($client, $command) {
        $methods = array_filter(get_class_methods($this), function($c){ return strpos($c, 'command_') === 0; });
        $methods = array_map(function($c){ return strtoupper(substr($c, 8)); }, $methods);
        $commands = implode(' ', $methods);
        $this->Respond($client, 100, $commands);
    }

    private function command_quit($client, $command) {
        $this->server->close($client);
    }

    private function Respond($client, $code, $message = '') {
        $res = strval($code).' ';
        $res .= empty($message)? self::ResponseCodes[$code] : $message;
        $res .= "\r\n";
        $client->Write($res);
    }

    public function tick() {
        $this->server->event_dispatch();
    }
}
