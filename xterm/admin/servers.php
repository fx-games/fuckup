<?php
	session_start();
	/* for the load time message */
	$starttime = explode(' ', microtime());
	$starttime = $starttime[1] + $starttime[0];
	/*TODO: dateipfade systemunabhaengig kapseln */
	/*TODO: add admin access for file deletion */
	/* USER CONFIGURATION SETTINGS START HERE*/
	$access_pass = (string)getenv('UPLOAD_PASS'); // unset = uploads disabled
	$page_title = "voynich miniupload 0.03f";	
	/* public_dir must exist as a subdir in the installed directory */
	$public_dir = "../network";
	/* max filesize is in bytes default 100.000.000 ~= 100 mb */
	$maxfilesize = "100000000";
	/* text labels */
	$upload_prompt = "Required: Filename";
	$newdir_prompt = "Optional: Directory Name";
	$buttonlabel = "Upload File";
	$notes = "Note: By default, files are uploaded to the current directory.\n<br />If a directory name is specified, the uploaded file will be placed in that subdirectory after it has been created.";
	$footline = "Copyleft memmaker";
	$msg = '';
	$current_dir = '';
	/* USER CONFIGURATION SETTINGS END HERE*/
	$page_name = basename($_SERVER['SCRIPT_FILENAME']);
	if (isset($_GET['cat']))
	{
		$current_dir = str_replace("+","/",$_GET['cat'])."/";
	}
	// only plain names below network/: no ../, no absolute paths, no markup
	$safe_path = fn($p) => preg_match('#^[\w.-]+(/[\w.-]+)*/?$#', $p) && !str_contains($p, '..');
	if ($current_dir !== '' && !$safe_path($current_dir)) die('Invalid directory.');
	if (!empty($_POST['newdir']) && !$safe_path($_POST['newdir'])) die('Invalid directory name.');
	echo(html::header());
	if (isset($_POST['password']))
	{
		if ($access_pass !== '' && hash_equals($access_pass, (string)$_POST['password']))
		{
			session_regenerate_id(true);
			$_SESSION['loggedin'] = true;			
		}
	}
	if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'])
	{
		echo(html::paragraph("You are logged in."));
	}	
	
	echo(html::uploadForm());
	if (isset($_FILES['uploadedfile']))
	{
		if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'])
		{
			if ($_POST['newdir']!="")
			{
				$target_path2 = "{$public_dir}/{$current_dir}{$_POST['newdir']}";
				mkdir($target_path2) or die("Could not create dir: {$target_path2}.\n");
				$target_path2 .= "/";
			}
			else
			{
				$target_path2 = "{$public_dir}/{$current_dir}";		
			}		
			
			$target_path = $target_path2.basename($_FILES['uploadedfile']['name']);
			if (!$safe_path(basename($_FILES['uploadedfile']['name'])) || preg_match('/^\.|\.ph/i', basename($_FILES['uploadedfile']['name'])))
			{
				$msg = "Error: file name not allowed.";
			}
			else if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $target_path))
			{
	    	$msg = "The file ".basename( $_FILES['uploadedfile']['name'])." has been uploaded to the directory ./{$target_path2}.";
			}
			else
			{
	    	$msg = "There was an error uploading the file, please try again!\nPath was {$target_path}";
			}
		}
		else
		{
			$msg = "Error: You must be logged in, in order to upload files.";
		}
	}
	$ft = new FileTransferrer();
	if (isset($_SESSION['loggedin']) && $_SESSION['loggedin']) echo($ft->getHtmlFileList());	
	$footer = "{$footline}";
	/* for the load time message */
	$mtime = explode(' ', microtime());
	$totaltime = $mtime[0] + $mtime[1] - $starttime;
	$footer .= sprintf("<br />\nPage loaded in %.3f seconds.", $totaltime);
	echo(html::divId($footer, "Footer"));
	echo("<p id=\"OutMessages\">{$msg}</p>");
	echo("</body>\n</html>");
	class FileTransferrer
	{
		private $base_path;
		function __construct()
		{
			global $public_dir;
			$this->base_path = $public_dir;
		}
		function getHtmlFileList()
		{
			global $page_name, $current_dir;
			$target_path1 = "./{$this->base_path}/{$current_dir}";
			$cwd_line = "<strong>Current Directory:</strong> {$target_path1}";
			$files = scandir($target_path1);
            $filelist = '';
			for ($c = 2 ; $c < count($files) ; $c = $c + 1)
			{
				if (is_dir("{$target_path1}/{$files[$c]}"))
				{
					if (substr($files[$c],0,1)!=".")
					{
						$prefix = "|_ ";
						if (isset($_GET['cat']))
						{
							$linkdest = "{$page_name}?cat=".urlencode($_GET['cat'])."%2b{$files[$c]}";
						}
						else
						{
							$linkdest = "{$page_name}?cat={$files[$c]}";
						}					
						$filelist = html::tr(html::td(html::href($files[$c], $linkdest))).$filelist;
					}
				}
				else
				{
					$prefix = "__ ";
					$linkdest = "{$target_path1}{$files[$c]}";
					$lastmod = date("F d Y H:i:s.", filemtime("{$target_path1}{$files[$c]}"));
					$filelist .= html::tr(html::td(html::href($files[$c], $linkdest)).html::td($lastmod));
				}				
			}
			if (isset($_GET['cat']))
			{
                $catlist = '';
				if (strpos($_GET['cat'],"+"))
				{
					$pathway = explode("+",$_GET['cat'],-1);
					foreach ($pathway as $dirpart)
					{
						$catlist .= "{$dirpart}%2b";
					}
					$catlist = substr($catlist,0,-3);
					$filelist = html::tr(html::td("<a href=\"{$page_name}?cat={$catlist}\">..</a>")).$filelist;
				}
				else
				{
					/* eine ebene verlinke einfach zur mainpage..*/
					$filelist = html::tr(html::td("<a href=\"{$page_name}\">..</a>")).$filelist;
				}
				$refresh_button = "<a href=\"{$page_name}?cat=".urlencode($_GET['cat'])."\">refresh</a><br />\n";
			}
			else
			{
				$refresh_button = "<a href=\"{$page_name}\">refresh</a><br />\n";
			}
			return "{$refresh_button}{$cwd_line}".html::divId(html::table($filelist), "FileList");
		}
	}
	class html
	{
		static function header()
		{
			global $page_title;
			return "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en\" lang=\"de\">\n<head>\n<title>{$page_title}</title>\n<meta http-equiv=\"content-type\" content=\"text/html;charset=utf-8\" />\n<style type=\"text/css\">\n@import url(\"../css/layout.css\");\n@import url(\"../css/color.css\");\n</style>\n</head>\n<body>\n<h1>{$page_title}</h1>\n";
		}
		static function uploadForm()
		{
			global $page_name,$maxfilesize,$upload_prompt, $newdir_prompt, $buttonlabel, $notes;
			if (isset($_GET['cat']))
			{
				$action = "{$page_name}?cat=".urlencode($_GET['cat']);
			}
			else
			{
				$action = "{$page_name}";
			}
			$pass_field = '';
			if (!isset($_SESSION['loggedin']))
			{
				$pass_field = "<table border=\"0\" cellpadding=\"0\" cellspacing=\"4\"><tr><td align=\"right\">password:</td><td><input name=\"password\" type=\"password\" size=\"12\" maxlength=\"12\" /></td></tr></table>";
			}
			$form = "<form enctype=\"multipart/form-data\" action=\"{$action}\" method=\"POST\">{$pass_field}\n<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"{$maxfilesize}\" />\n<table border=\"0\">\n<tr><th>{$upload_prompt}</th><th><input name=\"uploadedfile\" type=\"file\" /></th></tr>\n<tr><th>{$newdir_prompt}</th><th><input type=\"text\" name=\"newdir\" /></th></tr>\n</table>\n<input type=\"submit\" value=\"{$buttonlabel}\" />\n</form><p>{$notes}</p>\n";
			return html::divId($form, "UploadForm");
		}
		static function divId($string, $id)
		{
			return "<div id=\"{$id}\">{$string}</div>\n";
		}
		static function href($string, $dest)
		{
			return "<a href=\"{$dest}\">{$string}</a>";
		}
		static function paragraph($string)
		{
			return "<p>{$string}</p>";
		}
		static function table($string)
		{
			return "<table>\n{$string}\n</table>\n";
		}
		static function tr($string)
		{
			return "<tr>{$string}</tr>\n";
		}
		static function td($string)
		{
			return "<td>{$string}</td>";
		}
		static function loginForm()
		{
			$loginform = <<<EOF
	<form action="{$page_name}?action=login" method="post"></form>
EOF;
			return html::divId($loginform, "LoginForm");	
		}
	}	
?>
