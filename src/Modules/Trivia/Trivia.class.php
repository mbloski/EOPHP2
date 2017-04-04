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
class Trivia extends EOPHPModule {
    private $question_db;
    private $stats_db;

    private $last_question_time;
    private $last_top5_call;

    private $in_progress;
    private $question_interval;
    private $question_timeout;
    private $hint;
    private $maxpoints;

    private $current_question;

    private $questions_file;
    private $stats_file;

    function Initialize($args) {
        $this->questions_file = __DIR__.'/Questions.txt';
        $this->stats_file = __DIR__.'/Stats.txt';

        if (!is_file($this->questions_file)) {
            throw new Exception($this->questions_file.' not found');
        }

        $this->question_db = array();
        $questions = file($this->questions_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($questions as &$question) {
            $question_ar = explode('|', $question);
            $question = array_shift($question_ar);
            $this->question_db[] = array(
                'question' => mb_convert_encoding($question, 'ISO-8859-2'),
                'answers' => array_map(function($q) {
                    return strtolower($q);
                }, $question_ar));
        }

        $this->Output('Loaded '.count($this->question_db).' '.(count($this->question_db) == 1? 'question' : 'questions'));

        $this->stats_db = array();
        $stats = file($this->stats_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($stats !== false) {
            foreach ($stats as &$stat) {
                $stat_ar = explode('|', strtolower($stat));
                $name = $stat_ar[0];
                $points = intval($stat_ar[1]);
                $this->stats_db[$name] = $points;
            }
        }

        $this->last_top5_call = 0;

        $this->last_question_time = time();
        $this->in_progress = false;
        $this->question_interval = $args['interval'] ?? 300;
        $this->question_timeout = $args['timeout'] ?? 60;
        $this->maxpoints = $args['maxpoints'] ?? 10;
    }

    function Uninitialize() {
        /* Update stats file */
        $this->Output('Updating stats file');
        $fp = fopen($this->stats_file, 'w');
        foreach ($this->stats_db as $name => $points) {
            fwrite($fp, $name.'|'.$points."\n");
        }
        fclose($fp);
        $this->Output('Wrote '.count($this->stats_db).' records');
    }

    private function get_random_question() {
        return $this->question_db[mt_rand(0, count($this->question_db) - 1)];
    }

    private function AddPoints($name, $points) {
        $name = strtolower($name);
        if (isset($this->stats_db[$name])) {
            $this->stats_db[$name] += $points;
        } else {
            $this->stats_db[$name] = $points;
        }
    }

    public function GetPoints($name) {
        if (isset($this->stats_db[$name])) {
            return $this->stats_db[$name];
        }

        return null;
    }

    public function NewQuestion() {
        if ($this->in_progress) {
            $this->in_progress = false;
        }

        $this->last_question_time = time() - $this->question_interval - 1;
    }

    private function hint_str($str) {
        if (ctype_digit($str)) {
            $min = Util::Clamp(rand($str - 50, $str - 1), 0, $str - 1);
            $max = $min < 50? $min + rand($min + 1, $min + 10) : $str + rand(0, 50);
            return 'between '.$min.' and '.$max;
        }

        $len = strlen($str);
        $hints = floor($len / 4);
        if($hints != 0) {
            $hint = array_rand(array_fill(1, $len, true), $hints);
        } else {
            $hint = array();
        }

        if(is_numeric($hint)) settype($hint, 'array');

        $newstr = '';
        for($i = 0; $i < strlen($str); ++$i) {
            if($str[$i] == ' ') {
                $newstr .= ' ';
            } elseif(!in_array($i+1, $hint)) {
                $newstr .= '*';
            } else {
                $newstr .= $str[$i];
            }
        }
        return $newstr;
    }

    function Tick() {
        if (time() - $this->last_question_time >= $this->question_interval && !$this->in_progress) {
            $this->in_progress = true;
            $this->hint = false;
            $this->last_question_time = time();
            $this->current_question = $this->get_random_question();
            $this->Say($this->current_question['question']);
            if ($this->verbose) $this->Output('Event started! "'.$this->current_question['question'].'"');
        }

        if ($this->in_progress) {
            if (time() - $this->last_question_time >= $this->question_timeout) {
                $this->Say('Time\'s up! Correct answer: '.$this->current_question['answers'][0]);
                $this->in_progress = false;
                if ($this->verbose) $this->Output('Time\'s up! Noone has won');
            }

            if ($this->question_timeout - (time() - $this->last_question_time) <= (floor($this->question_timeout / 3.0)) && !$this->hint) {
                $time_left = ($this->question_timeout - (time() - $this->last_question_time));
                $this->hint = true;
                $hint_str = $this->hint_str($this->current_question['answers'][0]);
                $this->Say(Util::TimeDiff(time(), time() + $time_left).' left! Hint: '.$hint_str);
            }
        }
    }

    function Player_Talk($packet) {
        $id = $packet->get_int(2);
        $message = strtolower($packet->get_string());
        $ex = explode(' ', $message);

        if ($ex[0] == '#question') {
            if ($this->in_progress) {
                $this->Say($this->current_question['question']);
            } else {
                $this->Say('NO');
            }
        }

        if ($ex[0] == '#stats') {
            $name = substr($ex[1], 0, 12);

            if (strlen($name) < 4 || !ctype_alpha($name)) {
                $this->Emote(Protocol::EMOTE_CONFUSED);
                return;
            }

            $points = $this->GetPoints($name);
            if ($points !== null) {
                $this->Say(ucfirst($name).' has '.$points.' '.($points == 1? 'point' : 'points'));
            } else {
                $this->Say('Who is '.ucfirst($name).'?...');
            }
        }

        if ($ex[0] == '#leader') {
            arsort($this->stats_db);
            $leader = array_keys($this->stats_db)[0] ?? null;
            if ($leader)
                $this->Say('1. '.ucfirst($leader).' - Points: '.$this->stats_db[$leader]);
        }

        if ($ex[0] == '#top5') {
            if(time() - $this->last_top5_call >= 3) {
                $this->last_top5_call = time();
                arsort($this->stats_db);
                $top5 = array_keys(array_slice($this->stats_db, 0, 5));
                $counter = 0;
                foreach($top5 as $nickname) {
                    $points = $this->stats_db[$nickname];
                    $this->PrivateMessage($this->bot->characters[$id]->name, (++$counter).'. '.ucfirst($nickname) . ' - Points: ' . $points);
                }
            } else {
                $this->PrivateMessage($this->bot->characters[$id]->name, 'busy');
            }
        }

        if ($this->in_progress) {
            foreach($this->current_question['answers'] as $answer) {
                similar_text($message, $answer, $p);
                if($p >= 90) { /* Winner */
                    $winner_name = ucfirst($this->bot->characters[$id]->name);
                    $points = ceil(($this->question_timeout - (time() - $this->last_question_time)) / $this->question_timeout * $this->maxpoints);
                    /* Penalty if not accurate */
                    if($p != 100) ($points > 5)? ($points = $points - 5) : ($points = 1);
                    $this->Say($winner_name.' wins! ('.$points.' '.($points == 1? 'point' : 'points').')');
                    $this->AddPoints($winner_name, $points);
                    $this->in_progress = false;
                    if ($this->verbose) $this->Output($winner_name.' has won the event!');
                    break;
                }
            }
        }
    }
}
