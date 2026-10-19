<?php	
    include_once("library/lib_constants.php");
    setlocale(LC_ALL, 'id_ID');
    
	$exchange=new exchange();
	$exchange->set_historic_rate('2018-10-19', 'USD', '15208', 'IDR');
	$exchange->set_historic_rate('2018-10-19', 'CNY', '2250', 'IDR');
				
	define('cUsername', 'username');
	define('cPassword', 'password');
			
	$supported_locale=$exchange->supported_currencies();
	define('default_locale', 'id_ID');
	define('default_date', '2016-09-20');
	define('default_timezone', 'Asia/Jakarta');
	
	define('paypal_sandbox', true);
	
	setlocale(LC_MONETARY, default_locale);
	date_default_timezone_set(default_timezone);
		
	$global_variables=[];
		
	$secure_db=new dbm(db_server->getValue(), db_username->getValue(), db_passwd->getValue(), db_active->getValue());
//	$secure_db->enable_archive(db_server->getValue(), db_username->getValue(), db_passwd->getValue(), db_archive->getValue());
	$readonly_db=new dbm(db_server->getValue(), db_username->getValue(), db_passwd->getValue(), db_active->getValue());

	$Strings=new String_Resources(default_locale);
	$dm=new lib_datamodule($secure_db, $readonly_db);
	$settings=new settings($secure_db, $dm, false);
		
	$active_credentials=$defined_credentials;
	$settings->to_global('system_credentials_list', $active_credentials);
	
	$page_input=lib_page::process_input($settings, $secure_db, $active_credentials, $_GET, $_POST, $_FILES);
	$login_data=$page_input['data'];
	$credentials=$page_input['credentials'];
 
	$settings->credentials=$credentials;
					
	new lib_credential($settings, $credentials, $login_data);
	new lib_qrgen($settings, $credentials, $login_data);	 
?>
