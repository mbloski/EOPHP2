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
class SocketServer {
    private $socket;
    private $clients;

    function __construct($bindhost, $port) {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        if ($this->socket === false) {
            throw new Exception('Socket error: '.socket_strerror(socket_last_error()));
        }

        if (socket_bind($this->socket, $bindhost, $port) === false) {
            throw new Exception('Socket error: '.socket_strerror(socket_last_error()));
        }

        if (socket_listen($this->socket, 5) === false) {
            throw new Exception('Socket error: '.socket_strerror(socket_last_error()));
        }

        $this->clients = array();
        socket_set_nonblock($this->socket);
    }

    function accept() {
        $client = (socket_accept($this->socket));
        if ($client === false) {
            return null;
        }

        $this->clients[] = $client;
        return $client;
    }

    function send($c, $str) {
        $write = @socket_write($c, $str, strlen($str));

        if ($write === false) {
            // TODO: check for error codes
            $this->close($c);
        }
    }

    function recv($c) {
        $read = @socket_read($c, 2048);

        $error = socket_last_error();
        if ($read === false) {
            // TODO: check for error codes
            $this->close($c);
        }

        return $read;
    }

    function select() {
        $r = array_merge([$this->socket], $this->clients);
        $w = null;
        $e = null;

        $s = socket_select($r, $w, $e, 0);

        return $r;
    }

    function close($c) {
        foreach ($this->clients as $k => $client) {
            if ($c === $client) {
                unset($this->clients[$k]);
            }
        }

        if (is_resource($c))
            socket_close($c);
    }
}
