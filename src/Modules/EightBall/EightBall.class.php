<?php
class EightBall extends EOPHPModule {
    private $answers;

    public function Initialize($args) {
        $this->answers = [
            'It is certain.',
            'It is decidedly so.',
            'Without a doubt.',
            'Yes - definitely.',
            'You may rely on it.',
            'As I see it, yes.',
            'Most likely.',
            'Outlook good.',
            'Yes.',
            'Signs point to yes.',
            'Reply hazy, try again',
            'Ask again later.',
            'Better not tell you now.',
            'Cannot predict now.',
            'Concentrate and ask again.',
            'Don\'t count on it.',
            'My reply is no.',
            'My sources say no',
            'Outlook not so good.',
            'Very doubtful.'
        ];
    }

    private function get_random_answer() {
        return $this->answers[mt_rand(0, count($this->answers) - 1)];
    }

    public function Player_Talk($packet) {
        $id = $packet->get_int(2);
        $message = strtolower($packet->get_string());
        $ex = explode(' ', $message);
        if ($ex[0] === '#8ball') {
            $name = ucfirst($this->bot->characters[$id]->name);
            if (substr(end($ex), '-1') === '?') {
                $this->Say($name.', '.$this->get_random_answer());
            } else {
                $this->Say($name.', ask a question!');
            }
        }
    }
}
