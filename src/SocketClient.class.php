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
class SocketClient {
    private $socket;

    function __construct($host, $port) {
        $this->socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->socket === false) {
            throw new Exception('Socket error: '.socket_strerror(socket_last_error($this->socket)));
        }

        $result = @socket_connect($this->socket, $host, $port);
        if ($result === false) {
            throw new Exception(socket_strerror(socket_last_error($this->socket)));
        }

        socket_set_nonblock($this->socket);
    }

    public function send($str) {
        return socket_write($this->socket, $str, strlen($str));
    }

    private function do_recv() {
        $lenbytes = socket_read($this->socket, 1);
        $lenbytes .= socket_read($this->socket, 1);

        $len = Protocol::DecodeInteger($lenbytes);

        $packet = socket_read($this->socket, $len);

        if ($len == 0) {
            throw new Exception('Connection lost');
        }

        return $packet;
    }

    public function recv() {
        $r = array($this->socket);
        $w = NULL;
        $e = NULL;
        $select = socket_select($r, $w, $e, 0);

        if ($select === false) {
            throw new Exception(socket_strerror(socket_last_error($this->socket)));
        } else if ($select > 0) {
            return $this->do_recv();
        }

        return null;
    }

    public function close() {
        if ($this->socket) {
            socket_close($this->socket);
            return true;
        }

        return false;
    }
}
