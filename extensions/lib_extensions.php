<?php
	/*
	 * Extensions are used to extends the functionality of the system, and necessary but not essential
	 * for the system to function.  
	 * 
	 * It can be removed with need some efforts.  
	 * 
	 * Extension and expansions are interchangeable in term of the progamming accent, however in 
	 * philosophy those are completely different..  

	 */

	/*
	 * For example, messenging funcitonality should be part of this 
	 */

	define('employee_list', 'employee_list');

	include_once("menu/lib_menu.php");
	include_once("contact/contacts.php");	
	include_once("texting/texting.php");
	include_once("ext_schedule/lib_schedule.php");
		
	$settings->add_extension(new \lib_menu($settings, $credentials, $login_data));
	$settings->add_extension(new \texting($settings, $credentials, $login_data));
	$settings->add_extension(new \contacts($settings, $credentials, $login_data));	
	$settings->add_extension(new lib_schedule($settings, $credentials, $login_data));	
?>