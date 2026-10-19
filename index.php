<?php
	if (substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) ob_start("ob_gzhandler"); else ob_start();
	error_reporting(E_ERROR); ini_set("display_errors", 1); 
	
  //connect to the server
	if (!($_SERVER['HTTPS']))
	{
		header('Location: https://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']);
	}		
		
	include_once("lib_includes.php");
		
  //if system has no security checksum then
	if (!$credentials->valid)
    {    	    	
		require("login.php");		
    }
  	else
    {
		$fullname=$credentials->getName();
		
		$welcome_param=$credentials->encrypt_hyperlink_params('page.php', [_junction => '_welcome', ...$credentials->credentialArray()]);
		
		echo("<!DOCTYPE html>");
		echo("<html>");
		echo("<head>");
		echo("<title>Welcome $fullname</title>");
		echo("<link rel='shortcut icon' type='image/png' href='resources/favicon.ico'>");		
		echo("<link rel='stylesheet' type='text/css' href='resources/dv1.css'>");
		echo("</head>");
		echo("<body>");
		echo("<iframe id='if_menu' name='menu' src='leftmenu.php?".$credentials->encrypt_hyperlink_params('leftmenu.php', $credentials->credentialArray([]))."'></iframe>");
		echo("<iframe id='if_contents' name='contents' src='page.php?".$welcome_param."'></iframe>");
//		echo("<iframe id='if_contents' name='contents' src='welcome.php?".$credentials->encrypt_hyperlink_params('welcome.php', $credentials->credentialArray([]))."'></iframe>");
		echo("</body>");
		echo("</html>");		
		
//      mysql_free_result($result);
//		global $link;
//      	$link->close();
	}
?>
