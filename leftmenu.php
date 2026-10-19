<?php
	if (substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) ob_start("ob_gzhandler"); else ob_start();
	error_reporting(E_ERROR);	ini_set("display_errors", 1);
	
/*
  Copyright &copy; 2005 - 2020 Sony Lin
  All rights reserved.
*/

	include_once("lib_includes.php");
	include("extensions/menu/menu.php");

	$page=new menu($credentials, $login_data, $secure_db, $readonly_db);
	
	if ($page->write_output())
		echo($page->toHTML());
?>