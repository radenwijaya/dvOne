<?php
	/*
	 * This file is used to includes libraries
	 */
//	$directory_suffix='';
	//it will be used to direct the root documents or etc
	/*
	 * Load encryption keys and secrets
	 */
	include_once("library/lib_secret.php");
	include_once("resources/secrets_config.php");
	include_once("resources/secrets.php");
	
	include_once("resources/Strings.php");
	
//	include_once("components/qr_generator/phpqrcode/qrlib.php");
	
	/*
	 * Import dependencies
	 */
	include_once("library/lib_algorithms.php");	
	include_once("library/lib_html.php");
	
	/*
	 * Primary libraries
	 */	
	include_once("library/resource_reader.php");
	include_once("library/lib_baseclass.php");	
	include_once('library/lib_page_features.php');	
	include_once("library/lib_form.php");	
	include_once("library/lib_page.php");
	include_once("components/camera/camera_input.php");	//later move to better place for speeding up process
	include_once("components/faceapi/faceapi.php");	//later move to better place for speeding up process
	
	include_once('library/lib_extension.php');	
	include_once("library/lib_employee.php");
	include_once('library/lib_sockets.php');
	include_once('library/lib_ajax.php');	
	include_once("library/lib_base_list.php");
	include_once("library/lib_analyser.php");
	include_once("library/lib_file_handler.php");	
	include_once("library/lib_datamodule.php");
	include_once("library/lib_credential.php");
	include_once("library/lib_dbm.php");
	include_once("library/lib_finance.php");
	include_once('library/lib_menu.php');
	
	include_once("library/lib_page_features.php");
	include_once("library/lib_report_collator.php");
	
	/*
	 * Load Components
	 */
	include_once("components/qr_generator/lib_qrgen.php");
	include_once("components/comp_tag_printer.php");
	include_once("components/qr_reader/comp_qr_reader.php");	//later move to better place for speeding up process
	include_once("components/jquery/jquery.php");	//later move to better place for speeding up process
	
	/*
	 * Load Settings
	 */
	include_once("settings.php");
		
	include_once("library/lib_calendar.php");
	
	include_once("library/lib_messaging.php");	

	/*
	 * Load the actual loaders
	 */
	include_once("lib_main.php");
		
	include_once("extensions/lib_extensions.php");
	include_once("expansions/lib_expansions.php");
	 
	$credentials->load_employee();
	
//	$data_module->initialise();
?>
