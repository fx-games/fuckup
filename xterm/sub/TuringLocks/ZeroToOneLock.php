<?php
require_once('TuringLock.php');

class ZeroToOneLock extends TuringLock
{
    public function createRandomTestPair()
    {
        $inputstring = $this->createRandomBitString();
        $outputstring = str_replace('0', '1', $inputstring);
        return array($inputstring, $outputstring);
    }
}
