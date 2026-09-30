<?php
require_once('TuringLock.php');

class CopyLock extends TuringLock
{
    public function createRandomTestPair()
    {
        $inputstring = $this->createRandomBitString();
        $outputstring = $inputstring.'x'.$inputstring;
        return array($inputstring, $outputstring);
    }
}
