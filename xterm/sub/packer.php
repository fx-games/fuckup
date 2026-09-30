<?php

include(__DIR__.'/UserFunctions.php');

if (count($argv) < 3)
{
    echo("Usage: packer [command] [script]\n");
    echo("Usage: Where [script] is a relative path to a script file.");
    echo("Usage: Where [command] is either pack or unpack.");
    exit;
}

if (strcmp($argv[1],'pack') != 0 && strcmp($argv[1],'unpack') != 0 && strcmp($argv[1],'encrypt') != 0 && strcmp($argv[1],'decrypt') != 0)
{
    echo('Error: Wrong command.');
    exit;
}

if (!file_exists($argv[2]))
{
    echo('Error: Given filename does not exist.');
    exit;
}

if (strcmp($argv[1],'pack') == 0)
{
    file_put_contents(str_replace('.pb','.ex',$argv[2]),base64_encode(gzcompress(file_get_contents($argv[2]))));
}
else if (strcmp($argv[1],'unpack') == 0)
{
    file_put_contents(str_replace('.ex','.pb',$argv[2]),gzuncompress(base64_decode(file_get_contents($argv[2]))));
}
else if (strcmp($argv[1],'encrypt') == 0)
{
    file_put_contents(str_replace('.pb','.cx',$argv[2]),base64_encode(UserFunctions::encryptData(gzcompress(file_get_contents($argv[2])),'thePr0gram')));
}
else if (strcmp($argv[1],'decrypt') == 0)
{
    file_put_contents(str_replace('.cx','.pb',$argv[2]),gzuncompress(UserFunctions::decryptData(base64_decode(file_get_contents($argv[2])),'thePr0gram')));
}


?>