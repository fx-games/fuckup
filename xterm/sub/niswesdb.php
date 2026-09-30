<?php

require('dblite.php');

define('DS', DIRECTORY_SEPARATOR); // I always use this short form in my code.

function copy_r( $path, $dest )
{
    if(is_dir($path))
    {
        @mkdir($dest);
        $objects = scandir($path);
        if( sizeof($objects) > 0 )
        {
            foreach($objects as $file)
            {
                if($file == "." || $file == ".." || $file == ".svn")
                    continue;
                // go on
                if( is_dir($path.DS.$file))
                {
                    copy_r($path.DS.$file, $dest.DS.$file);
                }
                else
                {
                    copy($path.DS.$file, $dest.DS.$file);
                }
            }
        }
        return true;
    }
    elseif(is_file($path))
    {
        return copy($path, $dest);
    }
    else
    {
        return false;
    }
}

function normal_chars($string)
{
    $string = htmlentities($string, ENT_QUOTES, 'UTF-8');
    $string = preg_replace('~&([a-z]{1,2})(acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml);~i', '$1', $string);
    $string = html_entity_decode($string, ENT_QUOTES, 'UTF-8');
    $string = preg_replace(array('~[^0-9a-z]~i', '~[ -]+~'), ' ', $string);

    return trim($string, ' -');
}

class NiswesDb
{
    public $db;
    public $networkdir;

    public function __construct()
    {
        $this->db = new DbLite('../niswes.db');

        $this->networkdir = '../network';

        // define table structure

        /*
        $this->db->tables['servers'] = array
        (
            'servername', 'level'
        );
        */
        $this->db->tables['users'] = array
        (
            'username', 'email', 'token', 'title', 'terminal_width'
        );

        $this->db->tables['intel'] = array
        (
            'keyword', 'entry'
        );

        $this->db->tables['feedback'] = array
        (
            'entry'
        );

        $this->db->activetable = 'intel';
        if (!$this->db->hasTable('intel')) {
            $this->db->createTable(array('keyword'));
        }
        /*
        $this->db->activetable = 'servers';
        if (!$this->db->hasTable('servers')) {
            $this->db->createTable(array('servername'));
        }
        */
        $this->db->activetable = 'users';
        if (!$this->db->hasTable('users')) {
            $this->db->createTable(array('username', 'token'));
            // guest's MainFrame dir ships with the repo, so skip create_user()'s exists-check
            $this->db->insert(array('username' => 'guest', 'email' => 'false', 'token' => password_hash('guest', PASSWORD_DEFAULT), 'title' => 'VP Trainee', 'terminal_width' => 80));
        }
    }

    public function server_exists($servername)
    {
        return file_exists($this->get_server_dir($servername));
    }

    // locks are solved per user: the lock file stays, the user's dir gets a marker
    public function server_is_locked($servername, $username)
    {
        return file_exists($this->get_lock_file($servername))
            && !file_exists($this->get_user_dir($username) . '/.unlocked/' . basename($servername));
    }

    public function mark_unlocked($servername, $username)
    {
        @mkdir($this->get_user_dir($username) . '/.unlocked');
        touch($this->get_user_dir($username) . '/.unlocked/' . basename($servername));
    }

    public function get_lock_file($servername)
    {
        return $this->get_server_dir($servername) . '/.hidden/turing.lock';
    }

    public function get_server_list()
    {
        return array_diff(scandir($this->networkdir), array('.','..'));
    }

	public function get_file_path($complete_filename, $server, $username)
    {
        if (strcmp($server,'MainFrame') == 0)
        {
            $basedir = $this->get_user_dir($username);
        }
        else
        {
            $basedir = $this->get_server_dir($server);
        }
        // only files inside the server's (or user's) directory, no ../ escapes
        $base = realpath($basedir);
        $path = realpath($basedir . '/' . $complete_filename);
        if ($base && $path && str_starts_with($path, $base . '/')) {
            return $path;
        }
        return $basedir . '/.does-not-exist';
    }

	
    public function get_server_dir($servername)
    {
        return $this->networkdir . '/' . $servername;
    }
    public function get_user_dir($username)
    {
        return $this->get_server_dir('MainFrame') . '/' . normal_chars($username);
    }

    public function create_server($servername)
    {
        if ($this->server_exists($servername)) return;

        mkdir($this->get_server_dir($servername));
        mkdir($this->get_server_dir($servername).'/bin');
		mkdir($this->get_server_dir($servername).'/.hidden');
		/*
        $this->db->activetable = 'servers';
        $this->db->insert(array
        (
            'servername' => $servername,
            'level' => 0
        ));
		*/
    }

	public function hide_dotted_files($filename)
	{
		if (strcmp($filename[0], '.') == 0)
		{
			return false;
		}
		return true;
	}
	
    public function get_server_files($servername)
    {
        $names = array_filter(scandir($this->get_server_dir($servername)), array($this,'hide_dotted_files'));
        sort($names);
        return $names;
    }

    public function get_server_binaries($servername)
    {
        $names = array_filter(scandir($this->get_server_dir($servername).'/bin'), array($this,'hide_dotted_files'));
        sort($names);
        return $names;
    }

    public function get_user_binaries($username)
    {
        $filenames = array_filter(scandir($this->get_user_dir($username).'/bin'), array($this,'hide_dotted_files'));
        sort($filenames);
        return $filenames;
    }

    public function get_user_files($username)
    {
        $filenames = array_filter(scandir($this->get_user_dir($username)), array($this,'hide_dotted_files'));
        sort($filenames);
        return $filenames;
    }
    public function user_exists($username)
    {
        return $this->get_user_by_name($username) || is_dir($this->get_user_dir($username));
    }

    public function set_terminal_width($username, $newwidth)
    {
        $this->db->activetable = 'users';
        $this->db->update(array('username' => $username), array('terminal_width' => $newwidth));
    }

    public function remove_user($username)
    {
        $this->db->activetable = 'users';
        if ($this->db->selectone(array('username' => $username)))
        {
            $this->db->delete(array('username' => $username));
            // keep the files, but free the name (timestamp avoids clashes on repeated wipes)
            rename($this->get_user_dir($username), $this->get_user_dir('NA' . $username . time()));
        }
    }

    // the token column holds a password_hash(); old md5(user:pass) values are upgraded on login
    public function check_password($username, $password)
    {
        $user = $this->get_user_by_name($username);
        if (!$user) return false;
        if (password_verify($password, $user['token'])) return true;
        if (!hash_equals($user['token'], md5($username . ':' . $password))) return false;
        $this->db->update(array('username' => $username), array('token' => password_hash($password, PASSWORD_DEFAULT)));
        return true;
    }

    public function create_user($username, $password)
    {
        //echo("DB: trying to create user ".$username);
        if (!preg_match('/^[A-Za-z0-9_-]{1,32}$/', $username))
        {
            throw new Exception('Usernames may only contain letters, digits, _ and - (max. 32).');
        }
        if ($this->user_exists($username))
        {
            throw new Exception('User already exists.');
        }
        $this->db->activetable = 'users';
        $this->db->insert(array
        (
            'username' => $username,
            'email' => 'false',
            'token' => password_hash($password, PASSWORD_DEFAULT),
            'title' => 'VP Trainee',
            'terminal_width' => 80
        ));
		if (strcmp($username,'guest') != 0)
        {
            $user_dir = $this->get_user_dir($username);
            $src = $this->get_user_dir('skeleton');
            copy_r($src, $user_dir);
        }
        /*
        if (!file_exists($user_dir))
        {
            mkdir($user_dir);
        }


        $user_dir = $user_dir.'/*.*';
        echo(shell_exec("xcopy /E /Y $src $user_dir"));
        */
    }


    public function get_user_by_name($username)
    {
        $this->db->activetable = 'users';
        return $this->db->selectone(array('username' => $username));
    }

    public function create_intel($keyword, $entry)
    {
        $this->db->activetable = 'intel';
        $this->db->insert(array('keyword' => strtolower($keyword), 'entry' => $entry));
    }

    public function get_intel($keyword)
    {
        $this->db->activetable = 'intel';
        $result = $this->db->selectone(array('keyword' => strtolower($keyword)));
        if (!$result) return false;
        return $result['entry'];
    }

    public function create_feedback($entry)
    {
        $this->db->activetable = 'feedback';
        $this->db->insert(array('entry' => $entry));
    }

    public function try_copy_file($source, $target)
    {
        if (file_exists($target) || is_dir($target))
        {
            return false;
        }
        if (!file_exists($source) || is_dir($source))
        {
            return false;
        }
        copy($source, $target);
        return true;
    }

    public function try_delete_file($file)
    {
        if (file_exists($file) && !is_dir($file))
        {
            unlink($file);
            return 'File deleted.';
        }
        else
        {
            return 'File does not exist.'; //"'.$file.'"
        }
        $this->db->activetable = 'users_files';
    }
}
?>