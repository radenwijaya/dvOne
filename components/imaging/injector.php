<?php
	include_once("lib_main.php");
	include_once("lib_injector.php");
	
	if ($_GET[img])
	{
		$compressor=new image_compressor($_GET[img], $public_db, $_GET[width], $_GET[height]);
		$compressor->realOutputImage();
	}
?>
