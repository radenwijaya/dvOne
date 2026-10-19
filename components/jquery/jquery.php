<?php
	class jquery extends features
	{
		var $name;
		
		function __construct($credentials, $name)
		{
			$this->name=$name;
			parent::__construct($credentials, [], 'jquery', 'use_jquery');
		}
		
		function use_jquery()
		{
			return '<script src="./components/jquery/jquery-2.1.1.min.js"></script>';
		}		
	}
?>