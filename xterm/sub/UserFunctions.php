<?php
class UserFunctions
{
    public $username;
    public $servername;
    public $db;
    public $mainframe;
    public $token;
    public function __construct($niswesdb, $username, $token, $servername, $mainframe)
    {
        $this->mainframe = $mainframe;
        $this->db = $niswesdb;
        $this->username = $username;
        $this->token = $token;
        $this->servername = $servername;
    }

    public function getArrayElement($array, $key)
    {
        return $array[$key];
    }

    public function lsWrapper($showBinDir = false)
    {
        if (strcmp($this->servername,'MainFrame') == 0)
        {
            if ($showBinDir) return $this->db->get_user_binaries($this->username);
            return $this->db->get_user_files($this->username);
        }
        else
        {
            if ($showBinDir) return $this->db->get_server_binaries($this->servername);
            return $this->db->get_server_files($this->servername);
        }
    }

    public function setTerminalWidthWrapper($newwidth)
    {
        $this->db->set_terminal_width($this->username, $newwidth);
    }

	public function fileWrapper($filename)
	{
		$path = $this->db->get_file_path($filename, $this->servername, $this->username);
		return is_file($path) ? file($path, FILE_IGNORE_NEW_LINES) : array('File not found.');
	}
	
	public function fileGetContentsWrapper($filename)
	{
		return file_get_contents($this->db->get_file_path($filename, $this->servername, $this->username));
	}
	
	public function fileExistsWrapper($filename)
	{
		return file_exists($this->db->get_file_path($filename, $this->servername, $this->username));
	}

    public function wipeUser()
    {
        return $this->db->remove_user($this->username);
    }

    public function play($move)
    {
        if (strcmp($move,'reset') == 0)
        {
            if (file_exists('/usr/local/fuckup/chess/currentBoard'))
            {
                unlink('/usr/local/fuckup/chess/currentBoard');
                return 'Game reset.';
            }
            return 'No game being played.';
        }
        $reply = shell_exec('/usr/local/fuckup/chess/plMove.sh '.escapeshellarg($move));
        return $reply;
    }

    public function cr($mode, $key, $value)
    {

    }

    // was mcrypt Rijndael-256 (removed in PHP 7.2); .cx files re-encrypted with AES-256-ECB
    public static function encryptData($value, $key){
        return openssl_encrypt($value, 'aes-256-ecb', $key, OPENSSL_RAW_DATA);
    }

    public static function decryptData($value, $key){
        return openssl_decrypt($value, 'aes-256-ecb', $key, OPENSSL_RAW_DATA);
    }

    public function unlock($code)
    {
        $code = str_replace('\n',"\n",$code);
        if (!$code) return 'No code provided.';
        if (!$this->db->server_is_locked($this->servername, $this->username))
        {
            return 'Server is not locked.';
        }
        $lockname = basename(trim(file_get_contents($this->db->get_lock_file($this->servername))));
        require_once('./TuringLocks/'.$lockname.'.php');
        $lock = new $lockname();
        if ($lock->tryUnlock($code))
        {
            $this->db->mark_unlocked($this->servername, $this->username);
            return '[[;#2f2;]Success!] Turing lock disabled.';
        }

        $pairs = implode("\n",array($lock->getTestPairAsString(), $lock->getTestPairAsString(), $lock->getTestPairAsString()))."\n";

        return $code."\n".$lock->debugMessage."\nCorrect Samples:\n".$pairs;
    }

    public function connect($servername)
    {
        if ($this->db->server_exists($servername))
        {
            $_SESSION['connected_server'] = $servername;
            $this->servername = $servername;
            $this->mainframe->send_tab_completion_list = true;
            return 1;
        } else {
            return 0;
        }
    }

}
