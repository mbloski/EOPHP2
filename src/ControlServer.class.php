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

    const RESPONSE_OK = 100;
    const RESPONSE_ARG = 101;
    const RESPONSE_WARNING = 200;
    const RESPONSE_NOINSTANCE = 102;
    const RESPONSE_UNKNOWN = 300;
    const RESPONSE_MODULEFAIL = 501;
    const RESPONSE_NOMODULE = 502;
    const RESPONSE_COREMODULE = 503;

    const DefaultResponses = array(
        self::RESPONSE_OK => 'OK',
        self::RESPONSE_ARG => 'Argument Expected',
        self::RESPONSE_WARNING => 'Warning',
        self::RESPONSE_NOINSTANCE => 'No such instance',
        self::RESPONSE_UNKNOWN => 'Unknown Command',
        self::RESPONSE_MODULEFAIL => 'Failed to load/unload module',
        self::RESPONSE_NOMODULE => 'Module not loaded',
        self::RESPONSE_COREMODULE => 'Cannot load/unload CoreModule'
    );

    function __construct($bindhost, $bindport, $botcluster) {
        $this->server = new SocketServer($bindhost, $bindport);
        $this->botcluster = $botcluster;

        Output::Info('Serving Bot Control Protocol at '.$bindhost.':'.$bindport);
        $this->server->on_accept(function($client) {
            $this->Respond($client, self::RESPONSE_OK, 'EOPHP2 Controller at your service');
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
                    $this->Respond($client, self::RESPONSE_UNKNOWN);
                }
            }
        });

        $this->server->on_close(function($client) {
            $this->Respond($client, self::RESPONSE_OK, 'Bye');
        });
    }

    private function command_list($client, $command) {
        foreach ($this->botcluster->GetBots() as $bot) {
            $res = $bot->get_host().' ';
            $res .= CoreModule::STATE_NAMES[$bot->GetModule('CoreModule')->GetPlayerState()];
            $res .= ' INIT_TIME:'.$bot->GetModule('CoreModule')->get_init_time();
            $this->Respond($client, self::RESPONSE_OK, $res);
        }
    }

    private function command_modules($client, $command) {
        $tok = explode(' ', $command);

        if (!isset($tok[1])) {
            $this->Respond($client, self::RESPONSE_ARG);
            return;
        }

        $bot = $this->botcluster->Get($tok[1]);
        if ($bot === null) {
            $this->Respond($client, self::RESPONSE_NOINSTANCE);
            return;
        }

        $this->Respond($client, self::RESPONSE_OK, implode(' ', $bot->GetModuleList()));
    }

    private function command_eval($client, $command) {
        $doeval = function($cmd) use ($client) {
            try {
                eval($cmd);
                $this->Respond($client, self::RESPONSE_OK);
                return 0;
            } catch (\Error $e) {
                $this->Respond($client, self::RESPONSE_WARNING, ucfirst($e->getMessage()));
                return -1;
            }
        };

        return $doeval(substr($command, 5));
    }

    private function command_loadmodule($client, $command) {
        $tok = explode(' ', $command);

        if (!isset($tok[1]) || !isset($tok[2])) {
            $this->Respond($client, self::RESPONSE_ARG);
            return;
        }

        if (strtolower($tok[2]) == 'coremodule') {
            $this->Respond($client, self::RESPONSE_COREMODULE);
            return;
        }

        $bot = $this->botcluster->Get($tok[1]);
        if ($bot === null) {
            $this->Respond($client, self::RESPONSE_NOINSTANCE);
            return;
        }

        if ($bot->IsLoaded($tok[2])) {
            $this->Respond($client, self::RESPONSE_WARNING, 'Module already loaded. Reloading.');
            $bot->UnloadModule($tok[2]);
        }

        $r = $bot->LoadModule($tok[2]);
        if ($r) {
            $this->Respond($client, self::RESPONSE_OK);
        } else {
            $this->Respond($client, self::RESPONSE_MODULEFAIL);
        }
    }

    private function command_unloadmodule($client, $command) {
        $tok = explode(' ', $command);

        if (!isset($tok[1]) || !isset($tok[2])) {
            $this->Respond($client, self::RESPONSE_ARG);
            return;
        }

        if (strtolower($tok[2]) == 'coremodule') {
            $this->Respond($client, self::RESPONSE_COREMODULE);
            return;
        }

        $bot = $this->botcluster->Get($tok[1]);
        if ($bot === null) {
            $this->Respond($client, self::RESPONSE_NOINSTANCE);
            return;
        }

        $r = $bot->UnloadModule($tok[2]);
        if ($r) {
            $this->Respond($client, self::RESPONSE_OK);
        } else {
            $this->Respond($client, self::RESPONSE_NOMODULE);
        }
    }

    private function command_disconnect($client, $command) {
        $tok = explode(' ', $command);

        if (!isset($tok[1])) {
            $this->Respond($client, self::RESPONSE_ARG);
            return;
        }

        $bot = $this->botcluster->Get($tok[1]);
        if ($bot === null) {
            $this->Respond($client, self::RESPONSE_NOINSTANCE);
            return;
        }

        if ($bot->UnloadModule('CoreModule')) {
            $this->Respond($client, self::RESPONSE_OK);
        } else {
            $this->Respond($client, self::RESPONSE_MODULEFAIL);
        }
    }

    private function command_help($client, $command) {
        $methods = array_filter(get_class_methods($this), function($c){ return strpos($c, 'command_') === 0; });
        $methods = array_map(function($c){ return strtoupper(substr($c, 8)); }, $methods);
        $commands = implode(' ', $methods);
        $this->Respond($client, self::RESPONSE_OK, $commands);
    }

    private function command_quit($client, $command) {
        $this->server->close($client);
    }

    private function Respond($client, $code, $message = '') {
        $res = strval($code).' ';
        $res .= empty($message)? self::DefaultResponses[$code] : $message;
        $res .= "\r\n";
        $client->Write($res);
    }

    public function tick() {
        $this->server->event_dispatch();
    }
}
