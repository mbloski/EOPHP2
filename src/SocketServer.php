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
class SocketConnection {
    private $fh;
    private $rbuf = '';
    private $wbuf = '';

    function __construct($fh) {
        $this->fh = $fh;
    }

    function GetResource() {
        return $this->fh;
    }

    function HasWBuf() {
        return !empty($this->wbuf);
    }

    function GetRBuf() {
        return $this->rbuf;
    }

    function GetLine() {
        $offset = strpos($this->rbuf, "\n");
        if ($offset === false) {
            /* no line to read */
            return '';
        }
        ++$offset;

        $ret = substr($this->rbuf, 0, $offset);
        $this->rbuf = substr($this->rbuf, $offset);
        return $ret;
    }

    function Write($str) {
        $this->wbuf .= $str;
    }

    function DoSend() {
        if (empty($this->wbuf)) {
            return null;
        }

        $len = @socket_write($this->fh, $this->wbuf, strlen($this->wbuf));

        if ($len) {
            $this->wbuf = substr($this->wbuf, $len);
        }
        return $len;
    }

    function DoRecv() {
        $len = @socket_recv($this->fh, $buf, 2048, MSG_DONTWAIT);

        if ($buf) {
            $this->rbuf .= $buf;
        }
        return $len;
    }

    function DiscardRBuf() {
        $this->rbuf = '';
    }

    function Close() {
        socket_close($this->fh);
    }
}

class SocketServer {
    private $socket;
    private $clients;
    private $on_accept_func;
    private $on_read_func;
    private $on_close_func;

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

        $this->on_read(function($c) {
            $c->DiscardRBuf();
            Output::Info('WARNING: default on_read handler called DiscardRBuf()');
        });

        $this->on_accept(function($c) {});
        $this->on_close(function($c) {});
    }

    function on_accept(Closure $f) {
        $this->on_accept_func = $f;
    }

    function on_read(Closure $f) {
        $this->on_read_func = $f;
    }

    function on_close(Closure $f) {
        $this->on_close_func = $f;
    }

    function accept() {
        $fh = (socket_accept($this->socket));
        if ($fh === false) {
            return null;
        }

        $this->clients[] = $ret = new SocketConnection($fh);
        $this->on_accept_func->__invoke($ret);
        return $ret;
    }

    function event_dispatch() {
        $r = array($this->socket);
        $w = array();

        foreach ($this->clients as $c) {
            $res = $c->GetResource();
            array_push($r, $res);
            if ($c->HasWBuf()) {
                array_push($w, $res);
            }
        }

        $e = array();

        $s = socket_select($r, $w, $e, 0);
        if ($s === false) {
            throw new Exception('Socket error: '.socket_strerror(socket_last_error()));
        }

        foreach ($r as $c) {
            if ($c === $this->socket) {
                $this->accept();
                continue;
            }

            foreach ($this->clients as $cc) {
                if ($cc->GetResource() === $c) {
                    $recv = $cc->DoRecv();
                    if ($recv === 0) {
                        $this->Close($cc);
                        continue;
                    }

                    if ($recv === false) {
                        $err = socket_last_error();
                        switch ($err) {
                            case SOCKET_ECONNRESET:
                                $this->RemoveClient($cc);
                                break;
                            case SOCKET_EAGAIN:
                                break;
                            default:
                                throw new Exception('Socket error: '.socket_strerror($err));
                                break;
                        }
                        continue;
                    }

                    $this->on_read_func->__invoke($cc);
                }
            }
        }

        foreach ($w as $c) {
            foreach ($this->clients as $cc) {
                if ($cc->GetResource() === $c) {
                    $sent = $cc->DoSend();

                    if ($sent === false) {
                        $err = socket_last_error();
                        switch ($err) {
                            case SOCKET_EPIPE:
                                $this->RemoveClient($cc);
                                break;
                            case SOCKET_EAGAIN:
                                break;
                            default:
                                throw new Exception('Socket error: '.socket_strerror($err));
                                break;
                        }
                    }
                }
            }
        }

        return $r;
    }

    function RemoveClient($client, $close = false) {
        if ($close) {
            $this->on_close_func->__invoke($client);
            /* last chance to write remaining data */
            $client->DoSend();
        }

        foreach ($this->clients as $k => $c) {
            if ($client === $c) {
                if ($close) {
                    $c->close();
                }
                unset($this->clients[$k]);
                return true;
            }
        }

        return false;
    }

    function Close($client) {
        return $this->RemoveClient($client, true);
    }
}
