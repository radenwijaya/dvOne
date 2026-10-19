<?php
//	define('webservice_auth', 'service');	
	/*
	 * Webservice is now an extension!  
	 * In using webservice you should have several steps needed
	 * 1. have a public key
	 * 2. Activate it as extension
	 * 3. For any expansion/extension needed to use webservice, call webservice_registration_form and give it access
	 * 4. Activate the class via  $settings->push('webservices', 'ws_request_rpm', 'ext_weaver');
	 */
	class lib_webservice extends extensions
	{
		const service_status = [
			0 => [value => 'system_0', display => 'Invalid Credential'],
			2 => [value => 'system_2', display => 'Output Function not exist'],
			3 => [value => 'system_2', display => 'Output Step Error'],
			1 => [value => 'success', display => 'Success']
		];
		
		function __construct($settings, $credentials, $login_data)
		{
			parent::__construct($settings, $credentials, [], $login_data);			
//			$settings->addData('extensions', get_class($this));
			$this->dm=($settings->get_global('lib_datamodule'));
			
			$this->new_list('employee_webauth', 'smart_list', 'auth', 'system', 'table_employee_webauth',
				dbm::dbm_record_1);
		}
		
		function menuset_webservice()
		{
			$result=[];
			
			$result[credential]=cAdminChkSec;
			
			$result[title]='Webservice';
						
			$result[elements][]=[junction => 'create_webservice_auth', 'params' => [], 'target' => ''];
			$result[elements][]=[junction => 'show_publickey', 'params' => [], 'target' => ''];			
			
			return $result;
		}
		
		function available_menu()
		{
			$result=[];
			
			if ($this->credentials->check(cAdminChkSec))
				$result[]=['f' => 'menuset_webservice', 'p' => []];
				
			return $result;
		}		
	}
	
	class ws_credentials extends credentials
	{
		var $webauth;
		
		static function ws_authorise($post, $settings, $secure_db, $active_credentials) : ws_credentials
		{			
			foreach ($post as $key => $value)
			{
				switch ($key)
				{
					case junction: case cUsernameChkSec: case webservice_auth:
						{
							$sanitised=htmlspecialchars($value);
							$login_data[$key]=$sanitised;
							break;
						}
					default :
						$login_data[$key]=$value;
						break;
				}
			}
						
			$credentials=new ws_credentials($secure_db, $login_data[cUsernameChkSec]);
			
			$settings->add_extension(new lib_webservice($settings, $credentials, $login_data));
			
			$credentials->ws_addCredentials($active_credentials, $login_data);
					
			return $credentials;
		}
		
		function validate_webauth($login_data)
		/*
		 * Authoising webauth, ensuirng it is valid and authorised
		 */
		{			
			/*
			 * This is using lib_webservice because lib_credential is not active here
			 */
			$web_auth=$this->get_global('lib_webservice')->get_list('employee_webauth')->
							db_select($login_data[cUsernameChkSec]);
			
			$result=false;
			 
			if (hash_equals($login_data['webservice_auth'], webservice_auth->getValue()))
			/*
			 * Ensure auth data is accepted
			 */
			{
				/*
				 * Now checking 
				 */
				if ($result=$web_auth->ws_authorise($login_data[_junction], $login_data['junction_auth']));
					$this->webauth=$web_auth;
			}
			
			return $result;
		}
		
//		function ws_login(&$login_data)
		/*
		 * Login as Web Service (e.g. enabling all services that is available) to the web service
		 *
		 * and importing to login_data
		 */
/*		{
			if ($this->valid)
			{
				$available_credentials=$this->get_global('settings')->get_global('system_credentials_list');
				
				foreach ($this->get_all_credential_check_list($login_data[cUsernameChkSec], $available_credentials) as $credential)
				
				//				foreach ($this->credential_check_list as $credential)
				{
					$this->addCredential($credential['table'], $credential['name'], $credential['checksum']);
					
					$login_data[$credential['name']]=$credential['checksum'];
				}
				
				$this->login_valid=true;
			}
		}*/
		
		function ws_addCredentials($defined_credentials, $login_data, $extension = null)
		/*
		 * Webservice login adding credentials
		 */
		{
			if ($this->valid==false)
			{
				$this->valid=$this->validate_webauth($login_data);
			}
			
			if ($this->valid)
			{
				//				var_dump($defined_credentials);
				$this->add_system_credentials_list($defined_credentials);
				
				foreach ($defined_credentials as $credential)
				{
					$this->register_credential_table($credential['table']);
					
					/*					if (isset($login_data[$credential['name']]))
					 $this->addCredential($credential['table'], $credential['name'], $login_data[$credential['name']]);*/
					
					if ($extension)
						$this->extensions[$credential['name']]=$extension;
				}								
			}
		}
		
		function get_wsauth()
		{			
			return $this->webauth;
		}		
	}
	
	class auth extends record
	{
		function __construct($credentials, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, 'system', $secure_db, $readonly_db, []);
		}
		
		function auth_hash($value)
		/*
		 * This will add webauth_hash_salt, into the pepper location
		 */
		{
			return $this->hash($value, webauth_hash_salt->getValue());
		}
		
		function ws_authorise($junction, $junction_auth)
		{
			$result=false;
			
			$h_junction=$this->hash($junction);
			
			$c_data=$this->get('access', $this->hash($junction_auth));
			
			$s_result=false;
			foreach ($c_data['class'] as $c_junction)
				if ($s_result=hash_equals($c_junction, $h_junction))
					break;
					
			$result=($s_result!==false) && (time()<$c_data['expirity']);
					
			return $result;
		}
	}
		
	class webservice extends secure_page
	/*
	 * Webservice is a page class, extends secure page
	 * Add class to change webservice auth system
	 */
	{	
		function output()
		{
			$this->toJSON($this->pageContents);
		}
		
		static function page_type()
		{
			return 'webservice';
		}
		
		function __construct($credentials, $input, $secure_db, $readonly_db, $settings)
		{ 
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
			
			[$junction, $ns]=$this->get_junction($input);
			
//			var_dump($credentials->interface_credentials($settings, $input));
//			var_dump($junction::credential_check($credentials, $settings, $input));
			
			if ($credentials->valid)
			{
				$this->interface=$this->prepare_junction($junction, $credentials, $input, $secure_db, $readonly_db);
				
				if ($this->interface)
				{
					$this->pageContents=$this->interface->output();				
				}
			}
			else
			{
				$this->pageContents=['status' => lib_webservice::service_status[0][value]];
			}
		}
	}
		
	class webservice_junction extends master_junction
	/*
	 * This junction is analog to junction to handle engagement junction, e.g. only webservice_junction can on web_service
	 * 
	 * This junction will accept JSON and return JSON
	 */
	{
		final static function authenticate(\auth $auth, $key, #[\SensitiveParameter] $authentication_code)
		/*
		 * Authentication using passcode
		 */
		{						
			$code=$auth->get('access', $key, 'code');
			$exp=$auth->get('access', $key, 'expirity');
									
			return (date::today('timestamp')<$exp) && (hash_equals($code, $authentication_code));			
		}
		
		function output()
		{
/*			$result=['type' => 'application/json'];
			
			$result['content']=$this->content_source()->readyJSON();
			
			return $result;*/
			return $this->content_source()->readyJSON();
		}
	}
	
	class webservice_form extends lib_form
	/*
	 * Still needs lib_form as its handler to ensure data feed there is not malicioius
	 * 
	 * Output function is an array
	 */
	{
		function __construct($credentials, $action, $method, $return)
		{
			parent::__construct($credentials, get_class($this), $action, $method, $return);
		}
		
		function readyJSON()		
		/*
		 * Output junction give an array, but this will wrap it as an error 
		 * json_encode
		 * 
		 * 
		 * 
		 * Do not Confuse this as asArray!  
		 */
		{			
			$output_step_count=count($this->rv_output_step);
			if ($output_step_count>0)
			{
				$output_function=$this->rv_output_step[$this->input_step];
				if (method_exists($this, $output_function))
				{
					$result=$this->$output_function();
//					$result['status']=lib_webservice::service_status[1][value];				
				}
				else
				{
					$result=['status' => lib_webservice::service_status[2][value]];
				}
			}
			else
			{
				$result=['status' => lib_webservice::service_status[3][value]];				
			}
			
			return $result;
		}		
		
		function input($input, ...$fields)
		{
			$this->updateValue($input, ...$fields);
		}
	}
	
	class show_publickey extends junction
	{
		function __construct($credentials, $input, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
			
			$this->form=new show_publickey_form($credentials, 'show_publickey', $secure_db, $readonly_db);
			$this->form->set_junction($this);
		}
		
		function form_title($form_title='')
		{
			return parent::form_title('Public Key');
		}
		
		static function form_caption($mods='')
		{
			return 'Show Public Key';
		}
	}
	
	class show_publickey_form extends inputform_control
	{
		function __construct($credentials, $name, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $name, null, POST, null, $secure_db, $readonly_db);
			
			$this->rv_output_step=['stepZero', 'stepOne'];
			
			$this->add_credential($credentials->primary());
			
			$this->add_submit_button();
		}
		
		function stepZero()
		{
			$result=str_replace("\n", '<br>', client_key->getValue());
			
			$result.=\html::h2('Webservice Auth');
			$result.=webservice_auth->getValue();
			
			return $result;
		}
	}
	
	class create_webservice_auth extends junction
	{
		function __construct($credentials, $input, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
			
			$this->form=new create_webservice_auth_form($credentials, 'randomiser', $secure_db, $readonly_db);
			$this->form->set_junction($this);
			if ($this->form->input($input)==$this->form->final_input_step())
			{
				$this->form->insert();
			}
		}
		
		function form_title($form_title='')
		{
			return parent::form_title('Buat WebService Auth');
		}
		
		static function form_caption($mods='')
		{
			return 'Buat WS Auth';
		}
	}
	
	class create_webservice_auth_form extends inputform_control
	{
		var $code;
		
		function __construct($credentials, $name, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $name, null, POST, null, $secure_db, $readonly_db);
			
			$this->rv_input_step=['Go'];
			$this->rv_output_step=['stepZero', 'stepOne'];
			
			$this->add_text_input('Nama Apps', 'apps', '', '', 32, [required => true]);
			$this->add_date_input('Expired', 'expirity', EOT, date::today(), '', [required => true]);
			$this->add_credential($credentials->primary());
			
			$this->add_submit_button();
		}
		
		function stepZero()
		{
			$result=$this->form_open();
			
			$result.=html::h3('Klik Go untuk mendapatkan kode Authentication Web Service');
			
			$result.=$this->html_text('apps').'&nbsp;'.$this->html_date('expirity').'&nbsp;'.$this->html_submit();
			$result.=$this->html_credential();
			
			$result.=$this->form_close();
			
			$list=$this->get_global('lib_credential')->get_list('employee_webauth');
			
			$u_data=$list->db_select($this->credentials->username);
			
			$result.=html::h4('Authorised Web Application');
			if (($u_data) && ($u_data->get('access')))
			{
				foreach ($u_data->get('access') as ['apps' => $apps, 'expirity' => $expirity])
					$elements[]=[value => $apps.' exp ' .date::date($expirity)];
					
					$result.=html::create_html_list(false, ...$elements);
			}
			else
			{
				$result.='n/a';
			}
			
			return $result;
		}
		
		function stepOne()
		{
			$result=$this->form_open();
			
			$result.=$this->html_hidden_display('apps').'&nbsp;'.$this->html_hidden_display('expirity');
			
			$result.=html::h3('Catat kode dibawah ini karena hanya akan ditampilkan sekali saja.');
			$result.=html::h4($this->code);
			$result.=$this->html_credential();
			
			$result.=$this->form_close();
			
			$result.=$this->exit_form();
			
			return $result;
		}
		
		function insert()
		{
			$list=$this->get_global('lib_credential')->get_list('employee_webauth');
			$e=$list->db_select($this->credentials->username);
			
			$new=false;
			if (!isset($e))
			{
				$new=true;
				$e=$list->new($this->credentials->username);
			}
						
			$code=$this->random_uniqueid(32);
			$this->code=$code;
			
			$h_code=$this->hash($code);
			
			$e->remove('access');
			
			$e->set('access', $this->credentials->username, $h_code, 'maker');
			$e->set('access', $this->select('apps'), $h_code, 'apps');
			$e->set('access', $this->select('expirity'), $h_code, 'expirity');
			
			$this->set_dataholder('dh', $e);

			if ($new)
			{
				$e->flush();
			}
			else
			{
				$e->update();
					//				var_dump($e->data);
			}
		}
	}
		
	class webservice_registration_form extends fc_form
	{
		function __construct($credentials, $name)
		{
			parent::__construct($credentials, $name);
			
			$this->io_set_submit('stepZero', 'stepOne', 'save', 'Show');
			$this->io_set_final('stepOne');
			$this->io_set_insert('stepOne', 'insert_data');
		
			$list=$this->get_global('lib_credential')->get_list('employee_webauth');
			$e=$list->db_select($this->credentials->username);
			
			$ddl=[];
			$access=$e->get('access');
			foreach ($access as $code => ['apps' => $apps, 'expirity' => $ed])
			{
				if ($ed>time())
					$ddl[$code]=[value => $code, display => $apps.' ed:'.\date::date($ed)];
			}
									
			/*
			 * Can be accessed from multiple code. if a code expires, then can add different code, no biggie
			 */
			$this->add_dropdown('Akses', 'access', $ddl, '', [required => true, multiple => true, size => 4]);
			
			$this->add_mandatory_input('expansion', '');
			
			$this->add_mandatory_input('facility', '');
			$this->add_credential($credentials->primary());
			$this->add_submit_button();
		}
		
		function input($input, ...$fields)
		{
			parent::input($input, ...$fields);
			
			$settings=$this->get_global('settings');
			
			$ex=$this->select('expansion');
			$expansions=[];
			foreach ($settings->select('webservices', $this->select('expansion')) as $class)
			{
				$full_class=$ex."\\".$class;
				$expansions[]=[value => $full_class, display => $full_class::form_caption()];
			}
			
			$this->add_dropdown('Akses', 'class', $expansions, '', [required => true]);
			
			return parent::input($input, 'class');
		}
				
		function stepZero()
		{
			$result=$this->form_open();
			
			$result.=$this->html_dropdown('access').\html::br(2);
			
			$result.=$this->html_dropdown('class').\html::br(2).$this->html_submit();
						
			$result.=$this->html_credential();
			
			$result.=$this->form_close();
			
			return $result;
		}		
		
		function stepOne()
		{
			$result=$this->form_open();
			
			$result.=$this->html_hidden_display('access').\html::br(2);
			
			$result.=$this->html_hidden_display('class');
			
			$result.=$this->html_credential();
			
			$result.=$this->form_close();
			
			$result.=$this->exit_form(null, [], ['facility', 'expansion']);
			
			return $result;			
		}
		
		function insert_data()
		{
			$list=$this->get_global('lib_credential')->get_list('employee_webauth');
			$e=$list->db_select($this->credentials->username);

			foreach ($this->select('access') as $access)
			{
//				$e->set('access', [], $access, 'class'); var_dump($e->data); $e->update(); die;
				$haystack=$e->get('access', $access, 'class') ?? [];
				$class=$this->hash($this->select('class'));
						
				if (array_search($class, $haystack)===false)
					$e->push('access', $class, $access, 'class');
			}
			
			$e->update();
		}
	}
	
	class webservice_registration extends junction 
	/*
	 * This will register webservice and associate between webservice user and the webservice other
	 */
	{
		static function form_caption($form_title='')
		{
			return 'Daftar Apps';
		}
		
		function form_title($form_title='')
		{
			return 'Daftar Apps';
		}
		
		function __construct($credentials, $input, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
			
			$this->form=new webservice_registration_form($credentials, 'show_webservice_form');
			$this->form->set_junction($this);
			
			if (($this->form->input($input)) && ($this->form->is_final_input_step()))
				$this->form->insert();
		}		
	}	
?>