<?php
class Emotewalk extends EOPHPModule {
    private $whitelist;
    private $enabled = false;

    function Initialize($args) {
        if (!isset($args['whitelist']) || !is_array($args['whitelist'])) {
            throw new Exception('"whitelist" not set or is not an array');
        }

        $this->whitelist = $args['whitelist'];
    }

    function Player_Talk($packet) {
        $id = $packet->get_int(2);
        $message = $packet->get_string();

        $ex = explode(' ', $message);

        $nickname = $this->bot->characters[$id]->name;
        if (!in_array($nickname, $this->whitelist)) {
            return;
        }

        if ($ex[0] == '#emotewalk') {
            $this->enabled = !$this->enabled;
            if ($this->enabled) {
                $this->Say('ON');
            } else {
                $this->Say('OFF');
            }
        }
    }

    function Player_Emote($packet) {
        $id = $packet->get_int(2);
        $emote = $packet->get_int(1);

        $nickname = $this->bot->characters[$id]->name;
        if (!in_array($nickname, $this->whitelist)) {
            return;
        }

        if ($this->enabled) {
            switch ($emote) {
                case Protocol::EMOTE_HAPPY:
                    $this->Walk(Protocol::DIRECTION_LEFT);
                break;
                case Protocol::EMOTE_DEPRESSED:
                    $this->Walk(Protocol::DIRECTION_DOWN);
                break;
                case Protocol::EMOTE_SAD:
                    $this->Walk(Protocol::DIRECTION_RIGHT);
                break;
                case Protocol::EMOTE_CONFUSED:
                    $this->Walk(Protocol::DIRECTION_UP);
                break;
                case Protocol::EMOTE_EMBARASSED:
                    /* TODO attack */
                break;
                case Protocol::EMOTE_SURPRISED:
                    $this->Sit();
                break;
                case Protocol::EMOTE_SUICIDAL:
                    $this->Sit(false);
                break;
            }
        }
    }
}
