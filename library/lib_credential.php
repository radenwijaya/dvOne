<?php
	/*
	 * To maximise security, the name of important commands such as submit may be encrypted using input_session
	 * in form, of course so no one can inject submit to the system without knowing the input_session.  
	 */
	include_once("lib_baseclass.php");
	
	define('defined_credentials', array(
	    array('table' => ctableAdminList, 'name' => cAdminChkSec, 'f_name' => 'Admin'),
//		array('table' => ctableManagementList, 'name' => cManagementChkSec, 'f_name' => 'Management'),
//		array('table' => ctableFCList, 'name' => cFCChkSec, 'f_name' => 'FC'),
//		array('table' => ctableTechList, 'name' => cTechChkSec, 'f_name' => 'Tech'),
//		array('table' => ctableAccountingList, 'name' => cAccountingChkSec, 'f_name' => 'Accounting'),
//		array('table' => ctableHRDList, 'name' => cHRDChkSec, 'f_name' => 'HRD'),
//		array('table' => ctableMarketingList, 'name' => cMarketingChkSec, 'f_name' => 'Marketing'),
//		array('table' => ctableProducerList, 'name' => cProducerChkSec, 'f_name' => 'Producer'),
//		array('table' => ctableEfficiencyCharterList, 'name' => cECChkSec, 'f_name' => 'EfficiencyCharter'),
//		array('table' => ctableSecurityList, 'name' => cSecurityChkSec, 'f_name' => 'Security'),
//		array('table' => ctableChauffeurList, 'name' => cChauffeurChkSec, 'f_name' => 'Supir')
	));
	$defined_credentials=defined_credentials;
		
	class lib_credential extends extensions
	{				
		var $employee_list;
		
		function __construct($settings, $credentials, $login_data)
		{			
			parent::__construct($settings, $credentials, [], $login_data);
			
			if (!$this->get_global('employee_list'))				
			{
				/*
				 * Employee List is a really special list and is part of credential, not HRD.
				 * 
				 * Do not add in HRD!
				 */
				$this->employee_list=$this->new_list('employee_list', 'smart_list', 'employee', 'system', ctableUsername, 
					dbm::dbm_record_legacy, [['f' => 'is_commissioned', 'p' => []]], 1, advanced_list::DEFERRED_LOAD);			
				$this->employee_list->add_element_class_input('', '');
				$this->employee_list->element_id_field='username';
												
				$this->new_list('employee_webauth', 'smart_list', 'auth', 'system', 'table_employee_webauth',
					dbm::dbm_record_1);
				
				
/*				$this->employee_list=new smart_list($credentials, $secure_db, $readonly_db, ctableUsername);
				$this->employee_list->table_hr_name=ctableUsername;
				$this->employee_list->element_class='employee';
				$this->employee_list->list_owner='system';
				$this->employee_list->element_id_field='username';
				$this->employee_list->db_fill();
				$this->employee_list->add_default_condition([['f' => 'is_commissioned', 'p' => []]]);
				$this->employee_list->add_element_class_input('', '');
				$this->to_global('employee_list', $this->employee_list);*/
			}
		}
	}
			
	class credentials extends entity
	{
		use lib_html;
		
		var $data;
		var $database;
		var $username;
		var $email;
		
		var $user_data;
		
		var $blankstate;
		
		var $primary;
		
		var $employee;
		
		var $input_session;		
		
		var $system_credentials_list;
		var $extensions;
		
		var $loginkey;		
		var $scramble_field_name;
		var $scrambled_field;
		
		var $interface_credentials;
		
		var $bc_session;
		
		var $login_valid;
		var $credential_check_list;
		var $add_system_credentials_list;
		
		var $active_interface_name;

		static function getUsernamePasswordChecksum(#[\SensitiveParameter] $username, #[\SensitiveParameter] $password)
		/*
		 * This method has been deprecated and not encouraged to be used in the future 
		 */
		{
			return self::sha512($username.username_password_salt->getValue().$password);
		}
		
		static function getUsernameTableChecksum(#[\SensitiveParameter] $username, $tableName)
		/*
		 * Although this method is not exactly secure, as will create a passcode of a user to 
		 * a facility, this method can still be used because it does not exactly contain sensitive 
		 * user information.
		 * 
		 * This can help to stop pretendinig user from outside the system, but once someone compromise
		 * the machine, it will not protect the system
		 * 
		 * this should be updated with hash hmac instead
		 */
		{
			return self::sha512($username.username_table_salt->getValue().$tableName);  
		}
		
		function validateChecksumString($checksum, $tableName)
		//This function is used to check whether the checksum string
		//is valid or not
		{
		  	$link=$this->database;
		  	
		    $sChecksum=$this->getUsernameTableChecksum($this->username, $tableName);
		    $querychk = "SELECT username FROM $tableName WHERE dchecksum='$checksum' AND schecksum='$sChecksum'";
		    		
		    $link->simple_query($querychk);
		
		    if ($query_row = $link->next_result())
		    {
		    	return $query_row['username'];
		    } 
		    else
		    {
		    	return "";
		    }
		    
		    //mysql_free_result($result);
		}

		function addCredential($tableName, $credname, $checksum)
		/*
		 * Adding credential to rrunning system
		 */
		{
			$checksum=$this->database->SQLSafeString($checksum);			
			
			if ($this->username!='')
				if (hash_equals($this->username, $this->validateChecksumString($checksum, $tableName)))
				{										
					$this->data[$credname]=array('cs'=>$checksum, 'tbl'=>$tableName);
					
					$this->credential_check_list[]=array('table' => $tableName, 'name' => $credname,
						'checksum' => $checksum);
					
					$this->valid=true;
				}
		}
		
		function scramble_input($name, $type='')
		/*
		 * Register a new input form, and registers it 
		 */
		{			
			if (($this->scramble_field_name===false) || ($type=='hidden') || ($type=='credential') || 
				(!$this->input_session()))
				$result=$name;
			else
				if ((!$result=$this->scrambled_field[$name]) && ($name!=''))
					$result=hash_hmac($this->get_global('settings')->select('scramble_parameters'), $name, $this->input_session());
			
			$this->scrambled_field[$name]=$result;
			
			return $result;				
		}
		
		function descramble_input($input, $fields)
		/*
		 * Might cause problem with files, but who knows?
		 */
		{
			$result=[];
			
			if ($fields)
			{
				foreach ($fields as $name)
				{
					$value=$input[$this->scramble_input($name)];
					if (!is_null($value))
						$result[$name]=$value;
				}
			}
			else
				$result=$input;
				
			return $result;			
		}
		
		function get_scrambled_input($separator=';')
		{
			return implode($separator, array_keys($this->scrambled_field));
		}
		
		function validate_login_data($login_data)
		/*
		 * Validate login data will return fail when loginkey is not existed
		 */
		{
			if ($this->loginkey)
			{
				$pk=$login_data['pk'];
				unset($login_data['pk']);
				$params_query=json_encode($login_data);
				$check_pk=hash_hmac($this->get_global('settings')->select('encrypt_parameters_auth'), $params_query, $this->loginkey);
				
				$result=((hash_equals($pk, $check_pk)) &&
					     (hash_equals(basename($_SERVER["PHP_SELF"]), basename($login_data['uri']))));
			}
			else
				$result=false;
			
			return $result;
		}

		function addCredentials($defined_credentials, $login_data, $extension = null)
		/*
		 * This method will be called during initialisng of the system, and should do nothing
		 */
		{								
			if (($this->get_global('settings')->select('encrypt_parameters')) && ($login_data['pk']) && 
				($this->login_valid==false))
			{
				$this->login_valid=$this->validate_login_data($login_data);
			}
			
			if ($this->login_valid)
			{
				$this->add_system_credentials_list($defined_credentials);
			    foreach ($defined_credentials as $credential)
			    {
			    	$this->register_credential_table($credential['table']);
			    	
			    	if (isset($login_data[$credential['name']]))
			        	$this->addCredential($credential['table'], $credential['name'], $login_data[$credential['name']]);	    	
	
		        	if ($extension)	        			       
		        		$this->extensions[$credential['name']]=$extension;	        		
			    }
			}
		}
		
		function hash_blockchain($sequence, $nonce, $algo)
		{			
			$sequence['nonce']=$nonce;
			
			$hash=$this->hashiteration(json_encode($sequence), 0, $algo);
			
			return $hash;
		}
						
		function find_nonce($sequence, $algo)
		/*
		 * Set difficulty of the blockchain here!
		 */
		{			
			$diff=$this->get_global('settings')->select('blockchain', 'diff');
			
			$checkstring=str_repeat('0', $diff);
			
			$i=0;
			do 
			{
				$hash=$this->hash_blockchain($sequence, $i, $algo);
				if (substr($hash, 0, $diff)==$checkstring)	
				{
					$result=['hash' => $hash, 'nonce' => $i];
					
					break;
				}
				$i++;				
			}
			while (($diff>0) && ($i<=PHP_INT_MAX));
			
			return $result ?? []; 
		}
		
		function validate_chain()
		/*
		 * this function will validate every record on the chain.
		 * 
		 * warning it will take a long time to finish, when the chain was pretty long
		 */
		{
			$result=[];
			
			if (($this->bc_session['table']) && ($this->bc_session['uid']) &&
				($this->bc_session['id']) && ($this->bc_session['id_field']))
			{
				$re=new record($this, $this->primary(), $this->database, $this->database, [], $this->bc_session['table'], '', $this->bc_session['id_field']);				
				$re->load($this->bc_session['uid']);
				
				if ($re->exists)
				{
					$sequence=$this->bc_session['id'];
					$result[$re->{$this->bc_session['id_field']}]=$re->valid_sequence($sequence);
	
					while (!$re->blockchain_last($sequence))
					{												
						$result[$re->{$re->raw_data['meta'][$sequence]['id_field']}]=
							$re->valid_sequence($sequence);
						
						$sequence=$re->raw_data['meta'][$sequence]['seq'];
						$re=$re->prev_block;	
					}
/*					do
					{						
						$r=new record($this, $this->primary(), $this->database,
							$this->database, [], $re->raw_data['meta'][$sequence]['table'], '', 
							$re->raw_data['meta'][$sequence]['id_field']);					
						$r->load($re->raw_data['meta'][$sequence]['uid']);
						
						if (is_null($result[$r->{$re->raw_data['meta'][$sequence]['id_field']}]))
							$result[$r->{$re->raw_data['meta'][$sequence]['id_field']}]=$r->valid_sequence($re->raw_data['meta'][$sequence]['seq']);
						
						$sequence=$re->raw_data['meta'][$sequence]['seq'];
						$re=$r;
					}
					while (!$re->blockchain_last($sequence));*/
				}
			}
			
			return $result;
		}
				
		function get_blockchain_id($uniqueid, $table, $id_field, $data)
		/*
		 * This will create new blockchain id by 
		 * load a recorded entity of previous blockchain, and then read it
		 * 
		 * 'table', 'uid', 'seq' is the address of the particular previous 
		 * 
		 * 'id' is the actual hash of the data with sequence number
		 */
		{
			if (($this->bc_session['table']) && ($this->bc_session['uid']) && 
				($this->bc_session['id']) && ($this->bc_session['id_field'])) 
			{
				$re=new record($this, $this->primary(), $this->database, $this->database, [], $this->bc_session['table'], '', 
					$this->bc_session['id_field']);
						
				$re->load($this->bc_session['uid']);
					
				/*
				 * Append previous sequence hash to this sequence, and choose new algo for it
				 * do not use old algo for this
				 */			
				$data['seq_id']=$re->raw_data['meta'][$this->bc_session['id']]['hash'];
				$algo=$this->get_global('settings')->select('blockchain', 'algo');
				
				$h_info=$this->find_nonce($data, $algo);
					
				$result=['id' => $h_info['hash'], 'nonce' => $h_info['nonce'], 'table' => $this->bc_session['table'], 'uid' => $this->bc_session['uid'], 
					'id_field' => $this->bc_session['id_field'], 'seq' => $this->bc_session['id']];
			}
			else
			{
				/*
				 * Authenticate against itself
				 */
				$data['seq_id']=$uniqueid;
				
				$h_info=$this->find_nonce($data, 
					$this->get_global('settings')->select('blockchain', 'algo'));
				
				$result=['id' => $h_info['hash'], 'nonce' => $h_info['nonce'], 'table' => $table, 'uid' => $uniqueid, 'id_field' => $id_field, 'seq' => $uniqueid];
			}
				
			return $result;
		}
		
		function destroy_blockchain_info()
		{
			$query="UPDATE tableUsername SET ".
				"bc_id='',"."bc_uid='',"."bc_id_field='',"."bc_table=''".
				" WHERE username='".$this->database->SQLSafeString($this->username)."'";
			
			$this->database->simple_query($query);				
		}
		
		function store_blockchain_info($table, $uniqueid, $sequence, $id_field)
		{
			$this->bc_session['id']=$this->database->SQLSafeString($sequence);
			$this->bc_session['uid']=$this->database->SQLSafeString($uniqueid);
			$this->bc_session['table']=$this->database->SQLSafeString($table);
			$this->bc_session['id_field']=$this->database->SQLSafeString($id_field);
		
			$query="UPDATE tableUsername SET ".
				"bc_id='".$this->bc_session['id']."',".
				"bc_uid='".$this->bc_session['uid']."',".
				"bc_id_field='".$this->bc_session['id_field']."',".
				"bc_table='".$this->bc_session['table']."'".
				" WHERE username='".$this->database->SQLSafeString($this->username)."'";
			
			if ($this->valid_input_session())
				$this->database->simple_query($query);
		}
		
		function __construct($database, $username)
		{						
			$this->blankstate=false;
			$this->valid=false;
			$this->login_valid=($this->get_global('settings')->select('encrypt_parameters')==0);
			
			$this->input_session=[];
			
			$this->credential_check_list=array();
			$this->add_system_credentials_list=[];
			
			$this->scramble_field_name=array_search($this->get_global('settings')->select('scramble_parameters'), hash_hmac_algos());
			$this->scrambled_field=[];			
			
			$this->extensions=[];
			
			$this->database=$database;
			if ($username!='')
			{
			    $querychk = "SELECT * FROM tableUsername WHERE username='".$database->SQLSafeString($username)."'";
			//    echo($querychk);
			    $chk_result = $database->simple_query($querychk);
			    if ($database->num_rows()>0)
			    {
					$this->data=array();
					$this->username=$username;
					
		            $row=$database->next_result();
					$this->email=$row['email'];
					
					$this->input_session['next_session']=$row['input_session'];
					$this->input_session['this_session']=$row['input_session'];
					
					$this->bc_session['id']=$row['bc_id'];
					$this->bc_session['uid']=$row['bc_uid'];
					$this->bc_session['table']=$row['bc_table'];
					$this->bc_session['id_field']=$row['bc_id_field'];
					
					$this->loginkey=$row['loginkey'];
					
					$this->user_data=$row;
					
					if (class_exists('employee'))
					{
					    $this->load_employee();
					}
			    }
			}
			else
			{
			    $querychk = "SELECT username FROM tableUsername";
			    $chk_result = $database->simple_query($querychk);
				$this->blankstate=($database->num_rows()==0);
			}
		}
		
		function load_employee()
		{
		    if ($this->get_global('employee_list'))
		        $this->employee=$this->get_global('employee_list')->select($this->user_data['username']);
		        
	        if (!$this->employee)
	        {
	            $this->employee=new employee($this, $this->database, $this->database, [], '');
	            $this->employee->load($this->user_data['username'], $this->user_data);
		            //						$this->get_global('employee_list')->add($this->employee);
	        }		        
		}
		
		function shuffle_input_session()
		{
			$is=$this->input_session['this_session'];
			 
			if (!$this->database->is_dq_enabled())
			{
				$this->scrambled_field=[];
				
				$this->input_session['next_session']=$this->random_uniqueid(8);
				$this->input_session['this_session']=$this->input_session['next_session'];
		
				$query="UPDATE tableUsername SET input_session='".
					$this->input_session['next_session']."'"." WHERE username='".
					$this->database->SQLSafeString($this->username)."'";
				$this->database->simple_query($query);

				$is=$this->input_session['next_session'];
			}
				
			return $is;
		}

		function begin_input_session()
		{
			$settings=$this->get_global('settings');
			if ($settings->select('_anti_spam')=='on')
			{
				$settings->addData('_anti_spam', 'lock');
			}
		}
		
		function end_input_session()
		{
			$settings=$this->get_global('settings');
			if ($settings->select('_anti_spam')=='lock')
			{
				$settings->addData('_anti_spam', 'on');
				$this->shuffle_input_session();
			}
		}
		
		function valid_input_session($input_session=null)
		{						
			if ($this->get_global('settings')->select('_anti_spam')=='on')
				$result=hash_equals($this->input_session['next_session'], ($input_session ?? $this->input_session['this_session']));
			else
				$result=true;
//			var_dump($result, $this->input_session);
			return $result;
		}
			
		function set_input_session($input_session)
		{			
			$this->input_session['this_session']=$input_session;
		}
		
		function input_session()
		{
			return $this->input_session['next_session'];
		}
		
		function current_input_session()
		{
			return $this->input_session['this_session'];
		}
		
		function isBlankstate()
		{
			return $this->blankstate;
		}
		
		function isValid()
		{
			return $this->valid;
		}
		
		function asArray()
		{
			if ($this->valid)
				return $this->data;
			else
				return array();
		}
		
		function toString()
		/*
		 * Return everything
		 */
		{
			return $this->credentialString([]);
		}
		
		function credential_data($required_credentials=[], $as_string=false)
		{
			$result=$this->credentialArray($required_credentials);
			
			if ($this->active_interface_name)
			{
				if ($this->active_interface_name[0]=='\\')
					$result[_calling_junction]=$this->active_interface_name;
				else
					$result[_calling_junction]='\\'.$this->active_interface_name;
			}
			
			if ($as_string)
				$result=http_build_query($result);
			else
				$result=$result;
			
			return $result;
		}
				
		function credentialArray($required_credentials=[])
		{
			$credentials_array=[];
			$credentials_array[cUsernameChkSec]=$this->username;
							
			if (is_array($required_credentials))
			{
				if (empty($required_credentials))
				{
					foreach ($this->data as $credname => $data)
						$credentials_array[$credname]=$data['cs'];
				}
				else
					foreach ($required_credentials as $credential)
					{
						if ($this->check($credential))
							$credentials_array[$credential]=$this->data[$credential]['cs'];
					}
			}
			else
			{
				if ($this->check($required_credentials))
				{
					$credentials_array[$required_credentials]=$this->data[$required_credentials]['cs'];
				}
			}
			
			return $credentials_array;
		}
		
		function credentialString($required_credentials=[])
		{
			return http_build_query($this->credentialArray($required_credentials));
		}		
		
		function check(...$credentials)
		{
//			var_dump($credentials);
			//$this->primary($credname);						
			if (count($credentials)>1)
			{
				$checksum=array();
				foreach ($credentials as $credname)
				{
					if (isset($this->data[$credname]['cs']))
					{
						$checksum[$credname]=$this->data[$credname]['cs'];
					}
				}
				if (empty($checksum))
				{
					$checksum=null;
				}
				else
				{
					reset($checksum);
					$this->primary(key($checksum));
				}
			}
			else
			{				
				$credname=reset($credentials);
				$checksum=null;
//				var_dump($this->select($credname));
				if ($credname)
				{
					if ($checksum_check=$this->select($credname))
					{
						$checksum=$checksum_check['cs'];
//					var_dump($credname);
					}
					if ($checksum) //setting primary credential
						$this->primary($credname);
				}
			}
			
			return $checksum;
		}
		
		function add_system_credentials_list($system_credentials)
		{
			foreach ($system_credentials as $system_credential)
				$this->system_credentials_list[$system_credential['name']]=$system_credential;
		}		
	
		function logout()
		{
		  	if ($this->username!="")
		  	{			
		  		$username=$this->database->SQLSafeString($this->username);
		  		foreach ($this->data as $data)
		  		{		  		
		  			$tableName=$data['tbl'];
					
			    	$checksum=$this->random_uniqueid(16);

			    	$queryexec = "UPDATE $tableName SET dchecksum='$checksum' WHERE username='$username'";
			 
			    	$this->database->simple_query($queryexec);
				}
			}
		}
		
		function checkCredential($tableName, $credname, $checksum)
		//This function is used to check whether the user exists by checking the schecksum
		//if the user existed on the table, then it will update the checksum
		{
			$username=$this->database->SQLSafeString($this->username);
			
		    $querychk = "SELECT username FROM $tableName WHERE username='$username'";
		    $chk_result = $this->database->simple_query($querychk);
		    
		    if ($this->database->num_rows($chk_result)>0)
		    {
		        $queryexec = "UPDATE $tableName SET dchecksum='$checksum' WHERE username='$username'";
		 
		        $this->database->simple_query($queryexec);
		        
				$this->data[$credname]=array('cs'=>$checksum, 'tbl'=>$tableName);
				$this->valid=true;
		    }
		
		    //mysql_free_result($result);
		
		    return isset($this->data[$credname]) ? $this->data[$credname] : null;
		}
		
		function get_all_credential_check_list($username, $active_credentials)
		/*
		 * This method will expose the the random checksum, because it will give the credentials required for login
		 * it and must be executed after making sure login is valid
		 */
		{
			foreach ($active_credentials as $credential)
			{
				$cred_table=$credential['table'];
				$cred_name=$credential['name'];
				
				$querychk = "SELECT * FROM $cred_table WHERE username='$username'";
				$qid = $this->database->query($querychk);
				
				if($this->database->num_rows($qid)>0)
				{
					$row=$this->database->next_result($qid);
					
					$yield=['table' => $cred_table, 'name' => $cred_name, 'checksum' => $row['dchecksum']];
					
					$this->credential_check_list[]=$yield;
					
					yield $yield;
				}				
			}
		}
		
		function init_all_credentials_check_list($active_credentials)
		{
			foreach ($active_credentials as $credential)
			{
				$cred_table=$credential['table'];
				$cred_name=$credential['name'];
				$this->init_credential_check_list($cred_table, $cred_name);
			}			
		}
				
		function init_credential_check_list($cred_table, $cred_name)
		//this will tell the system which credentials are active and used by the software		
		{			
			$cstrong=true;
			$cred_checksum=bin2hex(openssl_random_pseudo_bytes(64, $cstrong));
			$this->credential_check_list[]=array('table' => $cred_table, 'name' => $cred_name, 
												 'checksum' => $cred_checksum);
		}
		
		static function checkin(dbm $database, #[\SensitiveParameter] $username, #[\SensitiveParameter] $password, $credential)
		/*
		 * Checkin function is used to check in, e.g. logging in by using username only
		 * 
		 * this function only works when the user has been logging in before
		 */
		{
			$c=-1;
			
			$username=$database->SQLSafeString($username);

			if (self::is_valid_login($database, $username, $password))
			{
				$query = "SELECT dchecksum FROM ".$credential['table']." WHERE username='$username'";
					
				$dcs=$database->simple_query($query);
					
				if ($database->num_rows($dcs)>0)
				{					
					$row=$database->next_result($dcs);
						
					$c=new credentials($database, $username);
					$c->checkCredential($credential['table'], 
						$credential['name'], $row['dchecksum']);		
				}
				else
					$c=-2;
			}

//			var_dump($c->check(...$credentials));
			
			return $c;
		}
		
		static function is_valid_login($database, #[\SensitiveParameter] $username, #[\SensitiveParameter] $password)
		{
			$result=false;
						
			$query = "SELECT username, password, checksum FROM tableUsername WHERE username='".$database->SQLSafeString($username)."'";			
			$database->simple_query($query);
			
			//if username and password can be found
			if (($database->num_rows()>0) && ($row=$database->next_result()) &&
				($row['username']==$username))
			{
				$result=password_verify($username.$password, $row['checksum']);
			}
			
			return $result;
		}
		
		function check_login_credential(#[\SensitiveParameter] $username, #[\SensitiveParameter] $password)
		{
			
			$this->valid=$this->is_valid_login($this->database, $username, $password);
			
			return $this->valid;
		}		
		
		function update_password(#[\SensitiveParameter] $username, #[\SensitiveParameter] $password)
		/*
		 * Checksum will need to be changed to hash_hmac
		 */
		{
			$username=$this->database->SQLSafeString($username);
			
			$passwd=password_hash($password, password_algo->getValue());
			$checksum=password_hash($username.$password, password_algo->getValue());
			
			$query = "UPDATE tableUsername SET password='".$passwd."', checksum='".$checksum."' ".
					 "WHERE username='".$username."'";
			
			$this->database->simple_query($query);
		}
		
		function password_verify(#[\SensitiveParameter] $password)
		/*
		 * Only works when user already logged in
		 */
		{
			$query = "SELECT username, password, checksum FROM tableUsername WHERE username='".$this->database->SQLSafeString($this->username)."'";
			$this->database->simple_query($query);
			
			$valid_password=false;
			
			if (($this->database->num_rows()>0) && ($row=$this->database->next_result()) &&
				($row['username']==$this->username))
			{
				$db_pwd_hash=$row['password'];
				if (password_verify($password, $db_pwd_hash))
					$valid_password=true;
			}
			
			return $valid_password;
		}
		
		function login(#[\SensitiveParameter] $username, #[\SensitiveParameter] $password)
		{
			$result=false;
			
			$this->username=$username;
			$query = "SELECT username, password, checksum FROM tableUsername WHERE username='".$this->database->SQLSafeString($username)."'";
			
			$this->database->simple_query($query);
			if (($this->database->num_rows()>0) && ($row=$this->database->next_result()) &&
				($row['username']==$username))
			{
				$db_pwd_hash=$row['password'];
				
				$valid_password=false;
				if (password_needs_rehash($db_pwd_hash, password_algo->getValue()))
				{
					/*
					 * Create and update new password
					 */
					switch (true)
					{
						case password_verify($password, $db_pwd_hash):
							{
								/*
								 * Correct password but outdated
								 */
								$valid_password=true;
								
								/*
								 * Verify the checksum as valid?
								 */								
								break;
							}							
						case (hash_equals($this->getUsernamePasswordChecksum($username, $password), $row['checksum'])):
							{
								/*
								 * Old method and needs to be updated
								 */
								$valid_password=true;								
								break;
							}
						default:
							{
								/*
								 * Wrong Password
								 */
								$result=false;
								
								break;
							}
					}
				}
				else
				{
					$valid_password=password_verify($password, $db_pwd_hash);
				}
				
				if ($valid_password)
				{
					/*
					 * Always update password on login success
					 */
					$this->update_password($username, $password);
					
					foreach ($this->credential_check_list as $credential)
						$this->checkCredential($credential['table'], $credential['name'], $credential['checksum']);
						
					$loginkey=$this->random_uniqueid(10);
						
					$query = "UPDATE ".ctableUsername." SET loginkey='$loginkey' WHERE username='".
						$this->database->SQLSafeString($username)."'";
					$this->database->simple_query($query);
						
					$this->loginkey=$loginkey;
					$result=true;
				}
			}
			
			return $result;
		}
				
		function firstlogin(#[\SensitiveParameter] $username, #[\SensitiveParameter] $password)
		{
			$result=false;
			if ($this->isBlankstate())
			{
				$database=$this->database;
				$this->username=$username;
				$this->password=password_hash($password);
				
				$checksum=password_hash($username.$password);
				$sChecksum=$this->getUsernameTableChecksum($username, ctableAdminList);
				
			    $query = "INSERT INTO tableUsername(username, password, checksum) 
			              VALUES('".$database->SQLSafeString($username)."', 
								 '".$database->SQLSafeString($this->password)."', 
								 '".$database->SQLSafeString($checksum)."')";
			    $save_result = $database->simple_query($query);

			    $adminquery = "INSERT INTO tableAdminList(username, schecksum)
			                   VALUES('".$database->SQLSafeString($username)."', 
							  '".$database->SQLSafeString($sChecksum)."')";
			    $save_result = $database->simple_query($adminquery);

				$result=true;
			}
			return $result;
		}

		function login_form()
		{
			if ($this->isBlankstate())
			{
				$form=new lib_form($this, 'firstlogin', 'firstlogin.php', 'post', '');
			}
			else
			{
				$form=new lib_form($this, 'firstlogin', 'login.php', 'post', '');
			}
			
			$form->add_text_input('Username', 'username', '', '', 10, array('required'=>true));
			$form->add_password_input('Password', 'password', 5, 128);
			$form->add_submit_button('', 'submit', 'OK');
			
			return $form->toString();
		}
				
		function primary($primary = '')
		//primary credential which is used on a page will be set by automatically on check credential 
		//but it should be set manually in case of first credential check is not the primary credential 
		{			
			if (!$this->primary)
			{
				if ($primary) $this->primary=$primary;
				else 
					$this->primary=$this->anycredential();
			}
			
			return $this->primary;
		}
		
		function available_credentials()
		{
			$result=[];
			foreach ($this->credential_check_list as $credential)
				$result[]=$credential['name'];
							
			return $result;
		}
		
		function anycredential(...$credentials)
		/*
		 * $credentials is the string of credential name, such as cFCChkSec
		 * it is used to hunt which of the credential is valid and return it
		 * accordingly
		 */
		{
			$result=null;
			if (empty($credentials))
			{
				foreach ($this->credential_check_list as $credential)
				{
					if ($this->check($credential['name']))
					{
						$result=$credential['name'];
						break;
					}
				}
			}
			else
			{
				foreach ($credentials as $credential_name)
					if ($this->check($credential_name))
					{						
						$result=$credential_name;
						break;
					}
			}
						
			if ((!$result) && ($this->check(cGuestChkSec)))
			{
				$result=cGuestChkSec;
			}
			
			return $result;
		/*
			if ($this->check(cGuestChkSec))
				return cGuestChkSec;
			elseif ($this->check(cFCChkSec))
				return cFCChkSec;
			elseif ($this->check(cAccountingChkSec))
				return cAccountingChkSec;
			elseif ($this->check(cHRDChkSec))
				return cHRDChkSec;				
			else if ($this->check(cTechChkSec))
				return cTechChkSec;
			elseif ($this->check(cAdminChkSec))
				return cAdminChkSec;*/
		}
		
		function username()
		{
			return $this->username;
		}
		
		function email()
		{
		    return $this->email;		
		}
		
		function getName()
		{
			return $this->employee->getName();
		}
				
		function getSecurityString()
		{
			$msgUsername=cUsernameChkSec."=".$this->username;
			foreach ($this->credential_check_list as $credential)
				$msgChkSec=$credential['name'].'='.$credential['checksum'];
							
			return $msgUsername."&".$msgChkSec;
		}
		
		function user_credentials()
		{
			$result=array();
			foreach ($this->credential_check_list as $credential)
				$result[]=$credential['name'];
			
			return $result;
		}
		
		function check_other_credentials($table, $username)
		{
			if (is_array($table))
			/*
			 * Cache Mode
			 */
			{
				$result=isset($table[$username]);
			}
			else
			{
				$query="SELECT username FROM ".$this->database->SQLSafeString($table).
					   " WHERE username ='".$this->database->SQLSafeString($username)."'";
				
				$qr=$this->database->simple_query($query);
					
				$result=($this->database->num_rows($qr)>0);
			}
			
			return $result;
		}
		
		function definition_data($credential, $data)
		{
			$defined_credentials=defined_credentials;
			foreach ($defined_credentials as [$data => $result, 'name' => $name])
				if ($name==$credential)
					break;
							
			return $result;
		}
		
		function register_credential_table($table)
		{
			$query="CREATE TABLE IF NOT EXISTS `$table` (
						`username` varchar(10) NOT NULL,
						`dchecksum` varchar(128) DEFAULT NULL,
						`schecksum` varchar(128) DEFAULT NULL)";
			
			$this->database->simple_query($query);
		}
		
		function find_employee($username)
		{
			$employee=$this->get_global('employee_list')->select($username);
			
			return $employee;
		}
			
		function find_employee_name($username)
		{
			$employee=$this->find_employee($username);
			
			return $employee ? $employee->getName() : $username;
		}
		
		function prepare_seal()
		{
			$this->employee->prepare_seal();
		}
		
		function sign($contents, $date=null)
		{		
			return $this->employee->sign($contents, $date);
		}
		
		function standarise_menu_elements($elements)
		{
			$result['params']=null;
			$result['class']=null;
			$result[junction]=null;
			$result['interop']=null;
			
			foreach ($elements as $key => $values)
			{
				$elements[$key]=array_merge($result, $values);
			}
			return $elements;
		}
				
		function interface_credentials($settings, $login_data)
		/*
		 * This function is used to receive the credential that an interface
		 * can accept as valid.
		 */
		{	
			$this->interface_credentials=[];
			$this->fm_unique_check=[];
			
			foreach ($settings->select('extensions') as $extension_class)
			{
				$c=$this->get_global($extension_class);
				$menu_set=$c->available_menu();								
				
				foreach ($menu_set as ['f' => $menu_function, 'p' => $m_params])
				{					
					$menu=$c->get_menu($menu_function, ...$m_params);
					
					$menu_credential=is_array($menu['credential']) ? $menu['credential'] : [$menu['credential']];
					
					$binds=$menu['binds'] ?? [];
					$menu['elements']=$this->standarise_menu_elements($menu['elements']);
					foreach ($menu['elements'] as ['class' => $class, junction => $interface, 'params' => $params, 'interop' => $interop])
					{
						if (isset($menu['params']))
							$params=($params ?? [])+$menu['params'];

						if (!$interface)
							$interface=$class;
							
						if (($interface) && ($interface[0]=='\\'))
						{
							$interface=substr($interface, 1);
							if ($ns=$c->select('namespace'))
								$params['_namespace']=$ns;
						}
						else
							if (!$interop)
								if ($ns=$c->select('namespace'))
								{
									$interface=$ns.'\\'.$interface;
									$params['_namespace']=$ns;
								}
							
						$valid=true;						
						foreach ($binds as $bind)
						{//var_dump($menu_function);
							if (isset($login_data[$bind]))
								if (is_array($login_data[$bind]))
									$valid=array_search($params[$bind], $login_data[$bind]) !== false;
								else
									if (!hash_equals($params[$bind] ?? '', $login_data[$bind] ?? ''))
										$valid=false;
						}
							
						if (($valid) && ($interface) && (class_exists($interface)))
						{
							foreach ($menu_credential as $credential)
							{							
								$this->interface_credentials[$interface][]=$credential;
							}
								
							$family_menus=$interface::get_invocable_interface($this, $settings);
							$this->expand_family_menu_credentials($interface, $ns, $settings, $extension_class, $family_menus);
//							var_dump($interface);
						}
					}
				}
			}
			
			return $this->interface_credentials;
		}
				
		var $fm_unique_check;
					
		function make_interface_id($fm_interface, $namespace)
		{
			if ($fm_interface[0]=='\\')
			/*
			 * If fm interface is full name already
			 */
			{
				$interface_id=substr($fm_interface, 1);
			}
			else
			{			
			/*
			 * This non full name interface often missed 
			 */
				$interface_id=$namespace ? $namespace.'\\'.$fm_interface : $fm_interface;
			}
			
			return $interface_id;
		}
	
		function expand_family_menu_credentials($interface, $namespace, $settings, $extension_class, $family_menus)
		/*
		 * This method is used to expand family menu credentials that might be defined by a class
		 * 
		 * This therefore completing chain of proof that a class can execute another class but not executed from other
		 * class  
		 * 
		 * Add checking to ensure that link is fullfilled
		 */
		{
			if (!empty($family_menus))
			{
				foreach ($family_menus as ['f' => $f_menu, 'p' => $p_menu])
				{
					$family_menu=$interface::$f_menu(...$p_menu);
					
					/*
					 * Ensure it is on array format
					 */
					if (is_array($family_menu['credential']))
						$credentials=$family_menu['credential'];
					else
						$credentials=[$family_menu['credential']];
						
					foreach ($family_menu['elements'] as [junction => $fm_interface])
					{
						$unique=false;
						$interface_id=$this->make_interface_id($fm_interface, $namespace);
						
						if (class_exists($interface_id))
						{
							if ($namespace)
								$settings->addData('namespace_master', $extension_class, $namespace.'\\'.$fm_interface);
								
							foreach ($credentials as $credential)
								if (!isset($this->fm_unique_check[$interface_id][$credential]))
								{
									$this->interface_credentials[$interface_id][]=$credential;
									$this->fm_unique_check[$interface_id][$credential]=1;
									
									$unique=true;
								}
							
							if (($unique) && (class_exists($interface_id)))
							{							
								$f_menus=$interface_id::get_invocable_interface($this, $settings);
								$this->expand_family_menu_credentials($interface_id, $namespace, $settings, $extension_class, $f_menus);
							}
						}
					}
				}
			}
		}		
		
		function valid_calling_junction($calling_interface, $interface, $namespace, $settings)
		/*
		 * Use full name spacing 
		 * e.g. \extensions\class_name
		 * 
		 * on get_invocable_interface!
		 */
		{
			$result=true;
			if (($calling_interface) && ($calling_interface!=$interface))
			{				
				$result=false;
				
				$family_menus=$calling_interface::get_invocable_interface($this, $settings);

				foreach ($family_menus as ['f' => $f_menu, 'p' => $p_menu])
				{
					$family_menu=$calling_interface::$f_menu(...$p_menu);
					foreach ($family_menu['elements'] as [junction => $fm_interface])
					{
						//var_dump($calling_interface, $fm_interface, $interface);
						if ($fm_interface==$interface)
						{
							$result=true;
							break;
						}
					}
				}
			}
			
			return $result;
		}		
		
		function get_credential_master($credential)
		/*
		 * Thius method will scan all available credentials on the system and returns the master / owner of the wanted
		 * credential
		 */
		{
			$result=null;
			
			foreach ($this->get_global('system_credentials_list') as ['name' => $credential_name, 'owner' => $owner])
				if ($credential==$credential_name)
				{
					if ($this->get_global($owner))
						$result=$this->get_global($owner)->get_master_from_credential($credential);
				}
			
			return $result;
		}
	}
	
	define('cGuestChkSec', "_checksec");
?>