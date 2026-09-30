<?php
session_start();
// password comes from the environment; unset = page disabled
$editpass = (string)getenv('INTEL_PASS');

require_once('../sub/dblite.php');
class Crud
{
    public $db;
    public $table;
    public $columns;

	public function __construct()
    {
	
        $this->db = new DbLite('../niswes.db');
		
		$this->table = 'intel';
		$this->columns = array('keyword', 'entry');
        
        $this->db->tables[$this->table] = $this->columns;

        $this->db->activetable = $this->table;
    }
	
	public function handle()
	{
        global $editpass;
        if ($editpass === '')
        {
            echo('<p>Disabled: set INTEL_PASS to enable.</p>');
            return;
        }
        if (isset($_POST['editpass']) && hash_equals($editpass, (string)$_POST['editpass']))
        {
            session_regenerate_id(true);
            $_SESSION['intel_admin'] = true;
        }

        if (empty($_SESSION['intel_admin']))
        {
            $this->show_login_form();
            return;
        }

		if (isset($_GET['add']) && isset($_POST['keyword']) && isset($_POST['entry']))
		{
			$this->db->activetable = $this->table;
			$newEntry = array(
				'keyword' => strtolower(trim($_POST['keyword'])),
				'entry' => $_POST['entry']
			);
			$this->db->insert($newEntry);
		}
		else if (isset($_GET['del']))
		{
			$this->db->activetable = $this->table;
			$this->db->delete(array('rowid' => $_GET['del']));
		}

        $this->show_edit_form();
        $this->show_entries();

    }
    public function show_login_form()
    {
        $htmloutput = '<form action="" method="post">';
        $htmloutput .= '<p>Editpass: <input type="password" name="editpass"/></p>';
        $htmloutput .= '<p><input type="submit" name="submit"/></p>';
        $htmloutput .= '</form>';
        echo($htmloutput);
    }
	public function show_edit_form()
	{
		$htmloutput = '<form action="?add" method="post">';
		$htmloutput .= '<p>Keyword: <input type="text" name="keyword"/></p>';
		$htmloutput .= '<p>Entry: <textarea name="entry" cols="50" rows="10"></textarea></p>';
		$htmloutput .= '<p><input type="submit" name="submit"/></p>';
		$htmloutput .= '</form>';
		echo($htmloutput);
	}
	
	public function show_entries()
	{
		$this->db->activetable = $this->table;
		$allrows = $this->db->selectall();
		$htmloutput = "<table>\n";
		
		$htmloutput .= "<tr>\n";
		foreach ($allrows[0] as $colname => $value)
		{
			$htmloutput .= "<th>";
			$htmloutput .= $colname;
			$htmloutput .= "</th>";
		}
		$htmloutput .= "</tr>\n";
		
		foreach ($allrows as $row)
		{
			$htmloutput .= "<tr>\n";
			foreach ($row as $colname => $value)
			{
				$htmloutput .= "<td style=\"border: 1px solid black;\">";
				$htmloutput .= nl2br(htmlspecialchars((string)$value));
				$htmloutput .= "</td>";
			}
			$htmloutput .= "<td>";
			$htmloutput .= '<a href="?del='.urlencode($row['rowid']).'">del</a>';
			$htmloutput .= "</td>";
			$htmloutput .= "</tr>\n";
		}
		$htmloutput .= "</table>\n";
		
		echo($htmloutput);
	}
}

function pageheader($page_title)
{
    
    return "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en\" lang=\"de\">\n<head>\n<title>{$page_title}</title>\n<meta http-equiv=\"content-type\" content=\"text/html;charset=utf-8\" />\n<style type=\"text/css\">\n@import url(\"../css/color.css\");\n</style>\n</head>\n<body>\n<h1>{$page_title}</h1>\n";
}

function footer()
{
    return '</body></html>';
}

$crud = new Crud();
echo(pageheader('voynich intel creation'));
$crud->handle();
echo(footer());

?>