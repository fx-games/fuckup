<?php
session_start();
require_once('json-rpc.php');
require_once('niswesdb.php');
require_once('pBasic.php');
require_once('UserFunctions.php');
require_once('Encoding.php');

function preserve_wordwrap($tstr, $len = 75, $br = "\n") {
    $strs = explode($br,$tstr);
    $retstr = "";
    foreach ($strs as $str) {
        $retstr .= _wordwrap($str,$len,$br) . $br;
    }
    return $retstr;
}

function _wordwrap( $str, $width, $break = "\n" )
{
    $formatted = '';
    $position = -1;
    $prev_position = 0;
    $last_line = -1;

    /// looping the string stop at each space 
    while( $position = mb_stripos( $str, " ", ++$position, 'utf-8' ) ) {
        if( $position > $last_line + $width + 1 ) {
            $formatted.= mb_substr( $str, $last_line + 1, $prev_position - $last_line - 1, 'utf-8' ).$break;
            $last_line = $prev_position;
        }
        $prev_position = $position;
    }

    /// adding last line without the break 
    $formatted.= mb_substr( $str, $last_line + 1, mb_strlen( $str ), 'utf-8' );
    return $formatted;
}


class MainFrame
{
    public $username;
    public $db;
    public $executable;
    public $send_tab_completion_list;
    public $terminal_width;
    function __construct()
    {
        $this->send_tab_completion_list = false;
        $this->terminal_width = 80;
        $this->init();
    }

    public function init()
    {
        $this->db = new NiswesDb();

        if (!array_key_exists('connected_server', $_SESSION)) {
            $_SESSION['connected_server'] = 'MainFrame';
        }
    }

    public function normal_response($msg)
    {
        $msg = Encoding::toUTF8($msg);
        $msg = preserve_wordwrap($msg, $this->terminal_width);
        return $this->raw_response($msg);
    }

    public function raw_response($msg)
    {
        $metadata = array();
        if ($this->send_tab_completion_list)
        {
            $metadata['tab_completion_list'] = $this->get_tab_completion_list();
            $this->send_tab_completion_list = false;
        }
        $answer = array('msg' => $msg, 'metadata' => $metadata);
        return $answer;
    }

    public function get_tab_completion_list()
    {
        if (strcmp($_SESSION['connected_server'],'MainFrame') == 0)
        {
            $tablist = array_merge($this->db->get_user_files($this->username),$this->db->get_user_binaries($this->username));
        }
        else
        {
            $tablist = array_merge($this->db->get_server_files($_SESSION['connected_server']),$this->db->get_server_binaries($_SESSION['connected_server']));
        }
        return $tablist;
    }

    protected function sanitize_filename($file)
    {
        return str_replace(array('..','/','\\','"','\''),'',$file);
    }


    protected function get_executable_file_path($complete_filename, $server)
    {
        $basename = $complete_filename;
        if (strcmp($server,'MainFrame') == 0)
        {
            $basedir = $this->db->get_user_dir($this->username);
            $fullpath = $this->try_directories($basedir, $basename);
            if ($fullpath)
            {
                return $fullpath;
            }
        }
        else
        {
            $basedir = $this->db->get_server_dir($server);
            $fullpath = $this->try_directories($basedir, $basename);
            if ($fullpath)
            {
                return $fullpath;
            }

        }
        return false;
    }

    protected function try_directories($basedir, $basename)
    {
        $defaultpath = $basedir . '/' . $basename;

        if (file_exists($defaultpath))
        {
            return $defaultpath;
        }

        $extendedfilename = $this->try_extensions($defaultpath);
        if ($extendedfilename)
        {
            return $extendedfilename;
        }

        $binpath = $basedir . '/bin/' . $basename;

        if (file_exists($binpath))
        {
            return $binpath;
        }

        $extendedBinFilename = $this->try_extensions($binpath);
        if ($extendedBinFilename)
        {
            return $extendedBinFilename;
        }

        return false;
    }

    protected function try_extensions($basepath)
    {
        $pbpath = $basepath . '.pb';
        $expath = $basepath . '.ex';
        $cxpath = $basepath . '.cx';

        if (file_exists($pbpath)) {
            return $pbpath;
        } elseif (file_exists($expath)) {
            return $expath;
        } elseif (file_exists($cxpath)) {
            return $cxpath;
        }
        return false;
    }

    protected function get_bin_file_path($complete_filename, $server)
    {
        $basename = $this->get_file_basename($complete_filename);
        if (strcmp($server,'MainFrame') == 0)
        {
            return $this->db->get_user_dir($this->username).'/bin/'.$basename;
        }
        else
        {
            return $this->db->get_server_dir($server).'/bin/'.$basename;
        }
    }

    // the token is a random per-session value, only valid together with the session cookie
    protected function is_logged_in($token)
    {
        if (!isset($_SESSION['token'], $_SESSION['user']) || !hash_equals($_SESSION['token'], (string)$token)) {
            return false;
        }
        $result = $this->db->get_user_by_name($_SESSION['user']);
        if ($result) {
            $this->username = $result['username'];
            $this->terminal_width = $result['terminal_width'];
            return true;
        }
        return false;
    }

    static $login_documentation = "login to the server (return token)";
    public function login($params)
    {
        $user = (string)($params[0] ?? '');
        $passwd = (string)($params[1] ?? '');
        if (!$this->db->check_password($user, $passwd)) {
            throw new Exception("Wrong username or password.");
        }
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        $_SESSION['token'] = bin2hex(random_bytes(16));
        $_SESSION['connected_server'] = 'MainFrame';
        $this->username = $user;
        $this->send_tab_completion_list = true;
        return $this->raw_response($_SESSION['token']);
    }

    public function run($token, $command, $parameters)
    {
        if (!$this->is_logged_in($token)) {
            return $this->normal_response('Must be logged in to use this command. ');
        }

        if ($this->db->server_is_locked($_SESSION['connected_server'], $this->username) && strcmp($command,'unlock') != 0 && strcmp($command,'connect') != 0)
        {
            // server is locked and command is not unlock
            return $this->normal_response("This server is protected by a Turing Lock (patent pending).");
        }

        $filename = $this->sanitize_filename($command);

        $filepath = $this->get_executable_file_path($filename, $_SESSION['connected_server']);

        if (file_exists($filepath) && !is_dir($filepath))
        {
            $contents = $this->load_script($filepath);
            $output = $this->execute_script($filename, $token, $parameters, $contents);
            return $this->normal_response($output);
        }
        else
        {
            return $this->normal_response("File '".$filename."' does not exist.");
        }
    }

    private function execute_script($filename, $token, $parameters, $contents)
    {
        $userfuncs = new UserFunctions($this->db, $this->username, $token, $_SESSION['connected_server'], $this);

        $pbasic = new pBasic();
        $pbasic->user_functions['create_user'] = array($this->db, 'create_user');
        $pbasic->user_functions['user_exists'] = array($this->db, 'user_exists');
        $pbasic->user_functions['get_intel'] = array($this->db, 'get_intel');
        $pbasic->user_functions['list_files'] = array($userfuncs, 'lsWrapper');
        $pbasic->user_functions['get_element'] = array($userfuncs, 'getArrayElement');
        $pbasic->user_functions['try_connect'] = array($userfuncs, 'connect');
        $pbasic->user_functions['file'] = array($userfuncs, 'fileWrapper');
        $pbasic->user_functions['file_get_contents'] = array($userfuncs, 'fileGetContentsWrapper');
        $pbasic->user_functions['file_exists'] = array($userfuncs, 'fileExistsWrapper');
        $pbasic->user_functions['term_width'] = array($userfuncs, 'setTerminalWidthWrapper');
        $pbasic->user_functions['wipe_user'] = array($userfuncs, 'wipeUser');
        $pbasic->user_functions['play'] = array($userfuncs, 'play');
        $pbasic->user_functions['unlock'] = array($userfuncs, 'unlock');

        $parameters = array_reverse($parameters);
        $parameters[] = $filename;
        $parameters = array_reverse($parameters);
        $environment = array(
            'USERNAME' => $this->username,
            'CONNECTED_SERVER' => $_SESSION['connected_server']
        );

        $output = implode('', $pbasic->interpret($contents, $parameters, $environment));
        return $output;
    }

    private function load_script($filepath)
    {
        if (strstr($filepath, '.ex'))
        {
            $contents = gzuncompress(base64_decode(file_get_contents($filepath))) . "\n";
        }
        elseif (strstr($filepath, '.cx')) {
            $contents = gzuncompress(UserFunctions::decryptData(base64_decode(file_get_contents($filepath)),'thePr0gram')) . "\n";
        }
        elseif (strstr($filepath, '.pb')) {
            $contents = file_get_contents($filepath) . "\n";
        }
        else
        {
            throw new Exception("No executable.");
        }
        return $contents;
    }

}

handle_json_rpc(new MainFrame());

?>