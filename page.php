<?php
	error_reporting(E_ERROR); ini_set("display_errors", 1);
	include_once("lib_includes.php");
	
	class invoke_page extends secure_page
	{				
		function __construct($credentials, $input, $secure_db, $readonly_db, $settings)
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
			
			[$junction, $ns]=$this->get_junction($input);
						
			$vc_check=null;
			
			if (($junction) && (is_subclass_of($junction, "\junction")) && ($jc_check=$junction::credential_check($credentials, $settings, $input)) &&
				($vc_check=$credentials->valid_calling_junction($input[_calling_junction], $junction, $ns ?? '', $settings)))
			{
				$this->interface=$this->prepare_junction($junction, $credentials, $input, $secure_db, $readonly_db);
				if ($this->interface)
				{
					$this->pageContents=$this->interface->output();
					$this->H1Title=$this->interface->form_title();
					
					$this->pageTitle=$this->H1Title;
					$this->pageDescription=$this->interface->description();
				}
			}
			else
			{				
				$this->interface=new invokeable_interface($credentials, $input, $secure_db, $readonly_db);
				
				$ec=0;
				if (!$junction)
				{
					/*
					 * Unauthorised can be caused by attempts by bad actor or perhaps overloaded input
					 */
					$ec=400;
				}
				else
				{
					if ($jc_check==false)
					{
						/*
						 * Login failed
						 */
						$ec=401;
					}
					if ($vc_check===false) 
					{
						/*
						 * System Failure
						 * 
						 * If it goes here, please ensure that class name is made in full with its
						 * namespace!
						 * e.g. /ext_spinner/xxx_class
						 */
						
						$ec=500;
					}
				}
				$error=$this->interface->_ERROR($ec);
				
				$this->H1Title=$this->interface->form_title('');
				$this->pageTitle=$this->H1Title;
				
				$this->pageContents=$error;
			}
		}
	}
	
	class invokeable_interface extends junction
	{
		use messages;
	}
	
	$page=new invoke_page($credentials, $login_data, $secure_db, $readonly_db, $settings);
	$page->compress_html=false;
	$page->output();
?>
