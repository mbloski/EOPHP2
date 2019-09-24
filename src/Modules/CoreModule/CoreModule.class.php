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
class CoreModule extends EOPHPModule {
    const STATE_UNINITIALIZED = 0;
    const STATE_INIT = 1;
    const STATE_LOGGED_IN = 2;
    const STATE_IN_GAME = 3;
    const STATE_DEAD = 4;

    const STATE_NAMES = array(
        0 => 'UNINITIALIZED',
        1 => 'INIT',
        2 => 'LOGGED_IN',
        3 => 'IN_GAME',
        4 => 'DEAD'
    );

    private $username;
    private $password;
    private $character;
    private $eo_version;
    private $hdid;

    private $player_id;
    private $my_characters;
    private $motd;

    private $state;

    function Initialize($args) {
        $this->state = CoreModule::STATE_UNINITIALIZED;
        $this->username = $args['username'] ?? '';
        $this->password = $args['password'] ?? '';
        $this->character = $args['character'] ?? '';
        $this->eo_version = $args['eo_version'] ?? 28;
        $this->hdid = $args['hdid'] ?? '12345678';

        $payload = array();
        $payload[] = Protocol::NULL;
        $payload[] = Protocol::NULL;
        $payload[] = Protocol::NULL;
        $payload[] = chr(1);
        $payload[] = chr(1);
        $payload[] = Protocol::EncodeInteger($this->eo_version);
        $payload[] = chr(113);
        $payload[] = chr(10);
        $payload[] = $this->hdid;

        $this->bot->send_packet(Protocol::F['Init'], Protocol::A['Init'], $payload);
    }

    function Uninitialize() {
        $this->state = CoreModule::STATE_DEAD;
        if ($this->bot->Disconnect()) {
            $this->Output('Disconnected');
        }
    }

    public function GetPlayerState() {
        return $this->state;
    }

    public function GetCharacterName() {
        return $this->character;
    }

    public function GetPid() {
        return $this->player_id;
    }

    function Init_Init($packet) {
        $sub_id = $packet->get_int(1);
        switch ($sub_id) {
            case Protocol::INIT_OUTDATED:
                $this->Output('Protocol not compatible (wrong version)');
                exit(1);
                break;
            case Protocol::INIT_OK:
                $this->state = CoreModule::STATE_INIT;
                $start_seq = $packet->get_int(1) * 7 + $packet->get_int(1) - 5;
                $d_multi = ord($packet->get_bytes(1));
                $e_multi = ord($packet->get_bytes(1));
                $this->player_id = $packet->get_int(2);
                $this->bot->set_init($d_multi, $e_multi, $start_seq, $this->player_id);
                $this->Output('Multi: [R:'.$d_multi.', S:'.$e_multi.'] Seq: ['.$start_seq.']');
                $payload = array();
                $payload[] = Protocol::EncodeInteger($e_multi, 2);
                $payload[] = Protocol::EncodeInteger($d_multi, 2);
                $payload[] = Protocol::EncodeInteger($this->player_id, 2);
                $this->bot->send_packet(Protocol::F['Connection'], Protocol::A['Accept'], $payload);
                $this->Login($this->username, $this->password);
                break;
            case Protocol::INIT_BANNED:
                $this->Output('IP ban');
                // TODO: permanent? how long
                exit(1);
                break;
            default:
                break;
        }
    }

    function Player_Connection($packet) {
        $s1 = $packet->get_int(2);
        $s2 = $packet->get_int(1);

        $new_seq = $s1 - $s2;
        $this->bot->set_seq($new_seq);
        $this->bot->send_packet(Protocol::F['Connection'], Protocol::A['Ping'], ['k']);
    }

    function Reply_Login($packet) {
        $sub_id = $packet->get_int(2);
        switch ($sub_id) {
            case Protocol::LOGIN_WRONG_USER:
                $this->Output('Account does not exist');
                exit(1);
                break;
            case Protocol::LOGIN_WRONG_PASSWORD:
                $this->Output('Wrong password');
                exit(1);
                break;
            case Protocol::LOGIN_OK:
                $this->state = CoreModule::STATE_LOGGED_IN;
                $this->characters = array();
                $character_count = $packet->get_int(1);

                if ($character_count == 0) {
                    $this->Output('Account is empty!');
                } else {
                    //$this->Output('Characters on this account: ');
                }

                $found_character = false;

                $packet->ignore(2);
                for ($i = 1; $i <= $character_count; ++$i) {
                    $name = $packet->get_string();
                    $id = $packet->get_int(4);
                    $level = $packet->get_int(1);
                    $gender = $packet->get_int(1);
                    $hairstyle = $packet->get_int(1);
                    $haircolor = $packet->get_int(1);
                    $race = $packet->get_int(1);
                    $admin = $packet->get_int(1);
                    $paperdoll = new EOPaperdoll();
                    $paperdoll->boots = $packet->get_int(2);
                    $paperdoll->armor = $packet->get_int(2);
                    $paperdoll->hat = $packet->get_int(2);
                    $paperdoll->shield = $packet->get_int(2);
                    $paperdoll->weapon = $packet->get_int(2);
                    $packet->ignore(1);

                    $this->my_characters[$name] = $id;
                    //$this->Output($i.'. '.ucfirst($name));

                    if (isset($this->my_characters[$name])) {
                        $found_character = true;
                        $this->SelectCharacter($id);
                    }
                }

                if (!$found_character) {
                    $this->Output('Character "'.$this->character.'"" could not be found on the account');
                    exit(1);
                }
                break;
            case Protocol::LOGIN_BANNED:
                $this->Output('Account is banned');
                exit(1);
                break;
            case Protocol::LOGIN_LOGGED_IN:
                $this->Output('Account already logged in');
                exit(1);
                break;
            case Protocol::LOGIN_SERVER_BUSY:
                $this->Output('Server is busy. Please try again later');
                exit(1);
                break;
        }
    }

    private function AddCharacter($packet) {
        $character = new EOCharacter();
        $character->name = $packet->get_string();
        $character->id = $packet->get_int(2);
        $character->map_id = $packet->get_int(2);
        $character->map_x = $packet->get_int(2);
        $character->map_y = $packet->get_int(2);
        $character->direction = $packet->get_int(1);
        $packet->ignore(1); // unknown
        $character->guild = $packet->get_bytes(3);
        $character->level = $packet->get_int(1);
        $character->gender = $packet->get_int(1);
        $character->hairstyle = $packet->get_int(1);
        $character->haircolor = $packet->get_int(1);
        $character->race = $packet->get_int(1);
        $character->maxhp = $packet->get_int(2);
        $character->hp = $packet->get_int(2);
        $character->maxtp = $packet->get_int(2);
        $character->tp = $packet->get_int(2);

        /* Paperdoll data */
        // TODO: store this
        $packet->get_int(2);
        $packet->get_int(2);
        $packet->get_int(2);
        $packet->get_int(2);
        $packet->get_int(2);
        $packet->get_int(2);
        $packet->get_int(2);
        $packet->get_int(2);
        $packet->get_int(2);

        $character->sitting = $packet->get_int(1);
        $character->hidden = $packet->get_int(1);
        $packet->ignore(1);

        $this->bot->characters[$character->id] = $character;
    }

    function Reply_Welcome($packet) {
        $sub_id = $packet->get_int(2);

        switch ($sub_id) {
            case 1:
                $player_id = $packet->get_int(2);
                $character_id = $this->my_characters[$this->character];

                $this->WorldLogin($player_id, $character_id);
                break;
            case 2: // We're in the game woo
                $this->state = CoreModule::STATE_IN_GAME;
                $this->Output('Logged in');
                $packet->ignore(1);
                $this->motd = array();
                for ($i = 0; $i < 9; ++$i) {
                    /* MoTD */
                    $this->motd[] = $packet->get_string();
                }

                $weight = $packet->get_int(1);
                $max_weight = $packet->get_int(1);

                $inventory = new EOInventory();

                /* Inventory */
                while ($packet->get_bytes(1, false) != Protocol::COMMA) {
                    $item = $packet->get_int(2);
                    $amount = $packet->get_int(4);
                    $inventory->add($item, $amount);
                }
                $packet->ignore(1);

                /* Spells */
                while ($packet->get_bytes(1, false) != Protocol::COMMA) {
                    $spell_id = $packet->get_int(2);
                    $spell_level = $packet->get_int(2);
                    // TODO: store them somewhere? Pretty pointless though.
                }
                $packet->ignore(1);

                $characters_in_range = $packet->get_int(1);
                $packet->ignore(1);
                for ($i = 0; $i < $characters_in_range; ++$i) {
                    $this->AddCharacter($packet);
                }

                if (isset($this->bot->characters[$this->player_id]->inventory)) {
                    $this->bot->characters[$this->player_id]->inventory = $inventory;
                }

                break;
        }
    }

    function Remove_Avatar($packet) {
        $id = $packet->get_int(2);
        if (isset($this->bot->characters[$id])) {
            unset($this->bot->characters[$id]);
        }
    }

    function Agree_Players($packet) {
        $packet->ignore(1);
        $this->AddCharacter($packet);
    }
}
