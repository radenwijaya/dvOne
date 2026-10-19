<?php
	error_reporting(E_ERROR); ini_set("display_errors", 1);
	
	include_once("lib_includes.php");	
	
	if ($credentials->valid)
	{		
		$page=new webservice($credentials, $login_data, $secure_db, $readonly_db, $settings);
		
		$page->output();
	}

//		$page->output();

//	echo(json_encode(['test 2']));
//	echo(json_encode($_GET));
?>