<?php 
	error_reporting(E_ERROR); ini_set("display_errors", 1);
	include_once("lib_includes.php");
	include_once("components/imaging/imaging.php");	

	$image=new secure_image($credentials, $login_data, $secure_db, $readonly_db, $settings);
	$image->output();
?>