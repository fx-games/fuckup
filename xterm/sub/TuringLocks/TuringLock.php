<?php
require_once('TuringMachine.php');
abstract class TuringLock
{
    public $debugMessage;
    public function getTestPairAsString()
    {
        $testpair = $this->createRandomTestPair();
        return $testpair[0].' -> '.$testpair[1];
    }

    public function tryUnlock($machinecode)
    {
        $tm = new TuringMachine($machinecode);

        for ($i = 0; $i < 100; $i++)
        {
            $testpair = $this->createRandomTestPair();
            $tmoutput = $tm->calculate($testpair[0]);
            if (strcmp($tmoutput, $testpair[1]) != 0)
            {
                $this->debugMessage = $tm->getDebugString();
                return false;
            }
        }
        return true;
    }

    public abstract function createRandomTestPair();

    public function createRandomBitString($length = 20)
    {
        $stringlength = rand(1, $length);
        $inputstring = '';
        for ($i = 0; $i < $stringlength; $i++) {
            if (rand(0, 100) < 50) {
                $inputstring .= '0';
            } else {
                $inputstring .= '1';
            }
        }
        return $inputstring;
    }
}
?>
