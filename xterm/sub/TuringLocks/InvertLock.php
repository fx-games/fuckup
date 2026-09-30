<?php
require_once('TuringLock.php');

class InvertLock extends TuringLock
{
    public function createRandomTestPair()
    {
        $inputstring = $this->createRandomBitString();
        $outputstring = str_replace('0', 'a', $inputstring);
        $outputstring = str_replace('1', '0', $outputstring);
        $outputstring = str_replace('a', '1', $outputstring);
        return array($inputstring, $outputstring);
    }
}
