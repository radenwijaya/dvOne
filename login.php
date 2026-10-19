<?php
	if (!($_SERVER['HTTPS']))
	{
		header('Location: https://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']);
	}
//	error_reporting(E_ALL);	ini_set("display_errors", 1);
	
//	if (substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) ob_start("ob_gzhandler"); else ob_start();
	
  //This file consists of login management protocol
  //SQL safety checked
	
	include_once("lib_includes.php");
	
	class login_page extends secure_page
	{
		var $payment_url;
		
		function initialise_credential_list(&$credentials, $active_credentials)
		{
			/*
			$credentials->init_credential_check_list(ctableAdminList, cAdminChkSec);
			$credentials->init_credential_check_list(ctableFCList, cFCChkSec);
			$credentials->init_credential_check_list(ctableMarketingList, cMarketingChkSec);
			$credentials->init_credential_check_list(ctableTechList, cTechChkSec);
			$credentials->init_credential_check_list(ctableAccountingList, cAccountingChkSec);
			$credentials->init_credential_check_list(ctableHRDList, cHRDChkSec);
			*/
			$credentials->init_all_credentials_check_list($active_credentials);
		}
		
		function __construct(credentials $credentials, $input, $secure_db, $readonly_db, $active_credentials)
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
			
			$username=$this->input['username'];
			$password=$this->input['password'];
			
			$this->pageTitle="Welcome to Golden Sun Administration System";
			$this->pageDescription="Welcome to Golden Sun Administration System";
			
			$this->initialise_credential_list($credentials, $active_credentials);
			if (($username) && ($password))
			{
				if ($credentials->login($username, $password))
				{
			      //Redirect back to index.php
//			      var_dump($credentials->loginkey);
					$ec_string=$this->encrypt_hyperlink_params('index.php', $credentials->credentialArray([]));
					header("Location: index.php?".$ec_string);
				}
				else
				{
					$this->H1Title="Please enter correct username and password";
					$this->pageContents=$this->credentials->login_form();
				}
			}
			else
			{				
				if ($credentials->isBlankstate())
				{
					$this->H1Title="Please configure the system by entering the system administrator name username and password";
				}
				else
				{
					$this->H1Title="Please enter your user name and password!";
				}
	
				$this->pageContents=$this->credentials->login_form();
			}
		}						
	}
	
  //if the username and password has been submitted	
  	if (($_POST['username']) && ($_POST['password']))
	{
		$page=new login_page($credentials, $_POST, $secure_db, $readonly_db, $settings->get_global('system_credentials_list'));
	}
  //if no username and password submitted
	else
	{
   		$page=new login_page($credentials, $_POST, $secure_db, $readonly_db, array());
  	}
?>
<!DOCTYPE html>
<html>
	<head>
		<title><?php echo($page->getH1Title()); ?></title>
	    <meta name="robots" content="noindex">
		<link type="text/css" href="dv1.css" rel="stylesheet">	
	</head>
	<body>
	    <img alt="logo" src="commonFiles/DVCOMPONLY.JPG"><br>
	    <h1><?php echo($page->getH1Title()); ?></h1>
	    <?php echo($page->getContents()); ?> 
	</body>
</html>