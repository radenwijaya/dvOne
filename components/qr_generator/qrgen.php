<?php
	/*
	 * Example of using qr_generator
	 */

	include("lib_qrgen.php");
	
	$qr_container=new qr_container($credentials, $secure_db, $readonly_db, $_GET);
	$qr_container->qrprint();	
?>