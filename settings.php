<?php
	include_once("library/lib_settings.php");
	
	class settings extends lib_settings
	{		
		/*
		Change Later to dbm_record_1
		*/
		const dbm_record_version = dbm::dbm_record_legacy;
		
		function add_settings_variable()
		/*
		 * Temporary, later add to database
		 */
		{
			parent::add_settings_variable();
			
			/*
			 * Page width in character
			 */
			$this->addData('page_width', 137);
			
			/*
			 * qr variable fill with useful information, it can be auth, search
			 * etc
			 */
			$this->set('qr', 'null', 'default');
			$this->set('qr', 'https://192.168.0.79/qr_check.php?i=', 'auth');
			//it will find the QR and display the data, to confirm with the data at hand 
			//and checking if its accurate or not
			$this->set('qr', '', 'search'); //leave it empty, so the QR will be plain and can be parsed by app			
			
			$this->set('scramble_parameters', '');
			$this->set('encrypt_parameters', 1);
			$this->set('encrypt_parameters_auth', 'sha3-256');
			$this->set('encrypt_hidden_parameters', 1);
			$this->set('encrypt_parameters_iv', enc_iv);
			$this->set('encrypt_parameters_password', enc_passwd);
			
			$this->set('encrypt_database_iv', enc_db_iv);
			$this->set('encrypt_database_password', enc_db_passwd);
					
			$this->set('system', ctableUsername, ctableUsername);
			
			$this->set('_anti_spam', 'on');
			
			$this->set('gst_rate', 0.11);
						
			$this->makeArray('extensions');
			$this->makeArray('expansions');
		}
	}
?>