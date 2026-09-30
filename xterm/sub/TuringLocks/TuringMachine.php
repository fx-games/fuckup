<?php
class TuringMachine
{
    public $state;
    private $tape;
    private $headposition;
    private $table;
    private $lastcharread;
    private $lastinput;

    public function __construct($code)
    {
        $this->table = array();
        $codelist = preg_split ('/$\R?^/m', $code);
        foreach ($codelist as $line)
        {
            $rule = explode(',', $line);
            if (count($rule) < 5) continue; // skip malformed player input
            $this->table[$rule[0]][$rule[1]] = array($rule[2], $rule[3], $rule[4]);
        }
        $this->clearTape();
        $this->reset();
        $this->lastcharread = '';
    }

    private function clearTape()
    {
        $this->tape = array();
    }

    private function reset()
    {
        $this->headposition = 0;
        $this->state = 0;
    }

    public function getDebugString()
    {
        return "TM halted with:\nState: ".$this->state."\nChar: ".$this->lastcharread."\nInput: ".$this->lastinput."\nTape : ".$this->getTapeContents()."\nHeadpos: ".$this->headposition;
    }

    public function calculate($input)
    {
        $this->initTapeWithInput($input);
        $halt = false;
        $steps = 0;
        while (!$halt)
        {
            // players submit these machines: cap runtime so a loop can't hang the server
            if (++$steps > 10000)
            {
                $this->lastinput = $input;
                return $this->getTapeContents();
            }
            $readchar = $this->readCharacterOnTape();
            if (!array_key_exists($this->state, $this->table) || !array_key_exists($readchar, $this->table[$this->state]))
            {
                /*
                echo('State: '.$this->state."\n");
                echo('Char: '.$readchar."\n");
                */
                $this->lastinput = $input;
                $this->lastcharread = $readchar;
                $halt = true;
            }
            else
            {
                $result = $this->table[$this->state][$readchar];
                $this->writeCharacterOnTape($result[0]);
                $this->moveHead($result[1]);
                $this->state = intval($result[2]);
            }
        }
        return $this->getTapeContents();
    }

    private function getTapeContents()
    {
        return implode('', $this->tape);
    }

    private function initTapeWithInput($input)
    {
        $this->clearTape();
        $this->reset();
        for ($i = 0; $i < strlen($input); $i++)
        {
            $character = substr($input, $i,1);
            $this->writeCharacterOnTape($character);
            $this->moveHead('r');
        }
        $this->reset();
    }

    private function moveHead($dir)
    {
        if (strcmp(strtolower($dir),'r') == 0)
        {
            $this->headposition++;
        }
        else if (strcmp(strtolower($dir),'l') == 0)
        {
            $this->headposition--;
        }
    }

    private function readCharacterOnTape()
    {
        if (array_key_exists($this->headposition,$this->tape))
        {
            return $this->tape[$this->headposition];
        }
        return '?';
    }

    private function writeCharacterOnTape($character)
    {
        $this->tape[$this->headposition] = $character;
    }
}
