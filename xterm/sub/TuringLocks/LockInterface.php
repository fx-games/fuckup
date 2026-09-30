<?php
/*
require_once('TuringMachine.php');
$tm = new TuringMachine("0,0,1,R,0\n0,1,1,R,0\n");
echo($argv[1]."\n");
echo($tm->calculate($argv[1])."\n");
*/
/*
require_once('ZeroToOneLock.php');
$lock = new ZeroToOneLock();
*/

class LockInterface
{
    private $lock;
    public function __construct($lockname)
    {

    }

    public function getExamples()
    {
        return implode("\n",array($this->lock->getTestPairAsString(), $this->lock->getTestPairAsString(), $this->lock->getTestPairAsString()))."\n";
    }

    public function tryUnlock($code)
    {
        if ($this->lock->tryUnlock($code))
        {
            // manipulate persistent storage as to indicate that this lock has been broken
            return "Lock OPENED.";
        }
        else
        {
            return "Lock still CLOSED.\n".$this->lock->debugMessage."\n";
        }
    }
}

$copycode = "0,0,a,r,1\n0,1,b,r,2\n1,0,0,r,1\n1,1,1,r,1\n1,?,0,l,3\n2,0,0,r,2\n2,1,1,r,2\n2,?,1,l,3\n3,0,0,l,3\n3,1,1,l,3\n3,a,0,r,0\n3,b,1,r,0";
$replacecode = "0,0,1,R,0\n0,1,1,R,0\n";
$invertcode = implode("\n",array(
    "0,0,1,R,0",
    "0,1,0,R,0"));

$li = new LockInterface('InvertLock');
//echo($li->getExamples());
echo($li->tryUnlock($invertcode));

