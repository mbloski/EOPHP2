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
include __DIR__.'/vendor/autoload.php';

use Discord\Discord;
class DiscordBot extends EOPHPModule {
    const BOT_TOKEN = '';

    private $discord;
    private $loop;
    private $eoguild;
    private $onlinelist;

    function Initialize($args) {
        $this->loop = React\EventLoop\Factory::create();

        $this->discord = new Discord([
            'token' => $this::BOT_TOKEN,
            'loop' => $this->loop
        ]);

        $this->discord->on('ready', function ($discord) {
            // TODO: this lacks a lot of error handling
            $this->Output('Discord ready!');

            $this->eoguild = $this->discord->guilds[257677554977210369]; // EOClone discord

            $discord->on('message', function ($message, $discord) {
                if (strtolower($message->author->username) === $this->bot->GetModule('CoreModule')->GetCharacterName()) return;

                if ($message->channel_id === $this->eoguild->channels->get('name', 'aeven')->id) {
                    $this->Say('<' . $message->author->username . '> ' . $message->content);
                }

                if ($message->channel_id === $this->eoguild->channels->get('name', 'lounge')->id) {
                    if ($message->content === '!players') {
                        $this->RequestOnlineNicknames();
                    }
                }

                $this->Output("{$message->author->username}: {$message->content}");
            });
        });
    }

    function Init_Init($packet) {
        $sub_id = $packet->get_int(1);
        if ($sub_id !== 10) return;

        $player_count = $packet->get_int(2);
        $player_list = [];
        $packet->get_bytes(1);
        for ($i = 0; $i < $player_count; ++$i) {
            $packet->get_string(); // TODO: tell sausage this is wrong
            $name = $packet->get_string();
            $player_list[] = ucfirst($name);
        }
        asort($player_list);

        $this->GetChannel('lounge')->sendMessage(strval($player_count).' player'.($player_count == 1? '' : 's').' online on EOClone: '.implode(', ', $player_list));
    }

    function Player_Talk($packet) {
        $id = $packet->get_int(2);
        $name = ucfirst($this->bot->characters[$id]->name);
        $message = str_replace('```', '', $packet->get_string());

        $this->GetChannel('aeven')->sendMessage('```['.date('h:i:s A').'] [SCR] <'.$name.'> '.$message.'```');
    }

    function Message_Talk($packet) {
        $name = ucfirst($packet->get_string());
        $message = str_replace('```', '', $packet->get_string());

        $this->GetChannel('aeven')->sendMessage("```CSS\n[".date('h:i:s A').'] [GLB] <'.$name.'> '.$message."\n```");
    }

    function Tick() {
        $this->loop->tick();
    }

    public function getDiscord() {
        return $this->discord;
    }

    public function GetChannel($name) {
        if (!isset($this->eoguild)) return;
        return $this->eoguild->channels->get('name', $name);
    }
}
