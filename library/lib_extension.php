<?php
	/*
	 * Libraries used to exend and expand the system
	 * 
	 * difference between extensions and components
	 * extensions is used to extend the functionality of the system as modules
	 * 
	 * componentss is used by extensions as a part of extension
	 * 
	 * known issue:
	 * When you add new extension, you have to be in a logged in state.  otherwise its database will not be initialising and read
	 * thus rendering the system unusable.
	 * Do not fix it to prevent people making new credential table without someone actually logged on
	 */

	class extensions extends entity
	{
		var lib_datamodule $dm;
		var $credentials;
		var $master_credentials;
		var $extension_credentials;
		
		var $local_settings;
		
		var $member_class;
		
		var $management;
		var $lists_version;
		
		function __construct($settings, credentials $credentials, $extension_credentials, $login_data)
		{			
			$this->addData('namespace', $this->find_namespace($this));
			$settings->addData('extensions', get_class($this));
			
			foreach (array_keys($extension_credentials) as $key)
				$extension_credentials[$key]['owner']=get_class($this);
			
			$c=$settings->get_global('system_credentials_list');
			$settings->to_global('system_credentials_list', [...$c, ...$extension_credentials]);
			
			//			$this->local_settings=new lib_settings(get_class($this), $settings->secure_db);
			$this->makeArray('local_settings');
			$this->add_settings_variables($settings);
			
			$this->to_global(get_class($this), $this);
			
			$this->extension_credentials=$extension_credentials;
			$credentials->addCredentials($extension_credentials, $login_data, $this);
			
			$this->dm=($settings->get_global('lib_datamodule'));
			$this->credentials=$credentials;
			
			$this->interop_data=[];
			$this->interop_menu=[];
			$this->interoperability=[];
			$this->member_class=[];
			
			$this->interop_list=[];
			
			$this->lists_version=[];
		}
		
		function add_settings($settings, $name, $value, ...$indices)
		/*
		 * if settings variable is valid, then it will become global settings
		 * otherwise it will only affect local settings
		 *
		 * Global settings is managed on settings page
		 *
		 * Local settings is managed on local settings page
		 */
		{
			$this->set($name, $value, ...$indices);
			if ($settings instanceof lib_settings)
				$settings->set($name, $value, ...$indices);
			else
				$this->set('local_settings', $name);
		}
		
		function add_settings_variables(lib_settings $settings)
		/*
		 * This method is always called and must be changed in sublcass if the
		 * subclass requires settings
		 */
		{
			/*
			 * Change in subclass
			 */
		}
		
		function settings_menu()
		/*
		 * Update credentials with autoritative credentials or master?
		 * set on each class that is extending this class
		 *
		 * I'm thinking better to use authoritative instead of masters
		 */
		{
			$result=[];
			
			if (!empty($this->select('local_settings')))
			{
				$mk=array_keys($this->master_credentials ?? []);
				
				$result['credential']=empty($mk) ? $this->credentials->primary() : $mk;
				$result['title']=$this->extension_name ?? 'Settings '.get_class($this);
				$result['params']=['local' => get_class($this)];
				$result['elements'][]=[junction => 'settings_interface'];
			}
			
			return $result;
		}
		
		function get_datatable_name($credential, $table_name)
		{
			return $this->dm->select($credential, $table_name);
		}
		
		function available_menu()
		{
			$result=[];
			
			if ($this->report_collectors_enabled())
				$result[]=[f => 'menu_set_collector', p => []];
				
			foreach (($this->member_class ?? []) as $class)
			{
				$class_menu=$class->available_menu();
				foreach ($class_menu as ['f' => $f, 'p' => $p])
					$result[]=['f' => 'member_menu', 'p' => [$class, $f, $p]];
			}
			
			return $result;
		}
		
		function set_credentials_master($credential, $counter)
		/*
		 * This method is used to set the master credentials for a module that can be expanded
		 * e.g. weaving_X, spinning_X
		 */
		{
			for ($i=1; $i<=$counter; $i++)
				$this->master_credentials[$credential.'_'.$i]=$i;
		}
		
		function get_master_credentials()
		/*
		 * This method will return the master credentials that is made on this module
		 */
		{
			return array_keys($this->master_credentials);
		}
		
		function get_credentials_number($master)
		/*
		 * This method is used to get the number of credentials from a master credential
		 */
		{
			return $this->master_credentials[$master];
		}
		
		function get_credential_from_master($master, $credential)
		/*
		 * This method is used to get a credential from master, e.g. WeavingWarehouse_X from Weaving_X
		 */
		{
			return $credential.'_'.$this->get_credentials_number($master);
		}
		
		function get_credentials_from_master($master)
		/*
		 * This function will return credentials list that is from mastered, e.g. it work in reverse of 
		 * get master from credentials
		 */
		{
			$master_no=$this->get_credentials_number($master);
			
			$result=[];
			
			foreach ($this->extension_credentials as ['name' => $name, 'no' => $no])
				if ($master_no==$no)
				{
					$result[]=$name;
				}
						
			return $result;			
		}
		
		function get_master_from_credential($c_name)
		/*
		 * This method is used to get the master credential from extension_credentials, or
		 * expanded credentials
		 */
		{
			$master=null;
			
			if ($this->master_credentials)
				foreach ($this->extension_credentials as ['name' => $name, 'no' => $no])
					if ($c_name==$name)
					{
						$master=array_search($no, $this->master_credentials);
						break;
					}
				
			return $master;
		}
		
		function get_credential_name($c_name)
		/*
		 * This method is used to get the human readable credential name from a credential
		 */
		{
			foreach ($this->extension_credentials as ['name' => $name, 'f_name' => $familiar_name])
				if (hash_equals($c_name, $name))
				{
					$result=$familiar_name;
					break;
				}
			
			return $result;
		}
		
		function get_credential_name_from_master($master, $credential)
		/*
		 * This method is used to get the human readable credential name from a master and credential
		 */
		{
			$c_name=$this->get_credential_from_master($master, $credential);
			
			return $this->get_credential_name($c_name);
			/*
			 $result='';
			 
			 foreach ($this->extension_credentials as ['name' => $name, 'f_name' => $f_name])
			 if ($c_name==$name)
			 {
			 $result=$f_name;
			 break;
			 }
			 
			 return $result;*/
		}
		
		function get_credentials_module_check(...$credentials_name)
		{
			$result=[];
			
			foreach ($this->master_credentials as $credential_no)
				foreach ($credentials_name as $credential_name)
					$result[]=$credential_name.'_'.$credential_no;
					
					return $result;
		}
		
		static function expand_credentials($extension_credentials, $counter)
		/*
		 * Expand the credentials according to counter
		 */
		{
			$result=[];
			for ($i=1; $i<=$counter; $i++)
				foreach ($extension_credentials as ['table' => $table, 'name' => $name, 'f_name' => $f_name])
					$result[]=['table' => "dcredTable".$table."_$i", 'name' => $name."_$i", 'f_name' => $f_name." $i", 'a_name' => $name, 'no' => $i];
					
					return $result;
		}
		
		static function get_credentials($settings)
		/*
		 * Returns the credentials created by this extension and will be used by promise
		 *
		 * This method is just a stub and must be overriden when you are creating another extension
		 * that requires additional credentials.
		 *
		 * expand_credentials can also be called from this method or its descandent
		 */
		{
			return [];
		}
		
		var $interoperability;
		var $interop_menu;
		var $interop_data;
		function get_menu($menu_set, ...$params)
		{
			$menu=$this->{$menu_set}(...$params);
			
			if (isset($this->interop_menu[$menu_set]))
			{
				foreach ($this->interop_menu[$menu_set] as $interop_menu)
				{
					$class=get_class($this);
					$extension=$this->get_global($interop_menu);
					
					$interop_menu=$extension->get_interop_menu($class, $menu_set);
					
					foreach ($interop_menu['elements'] as $elements)
					{
						foreach ($elements as $element)
						{
							if (isset($menu['params']))
								$element['params']=array_merge($element['params'] ?? [], $menu['params'] ?? []);
								
								/*						foreach ($element['params'] as $param_key => $param_value)
								 {
								 $element['params'][$param_key]=$param_value ?? $menu['params'][$param_key];
								 }*/
						
							$element['params'][_extending]=$class;
								
							$menu['elements'][]=$element;
						}
					}
				}
			}
			
			return $menu;
		}
		
		function get_interop_menu($class, $menu_set)
		{
			return $this->interoperability[$class][$menu_set];
		}
		
		function get_interoperability($class)
		/*
		 * This function will check the interoperability of this class to the caller class ($class)
		 */
		{
			return isset($this->interoperability[$class]) ? $this->interoperability[$class] : null;
		}
		
		function add_interoperability($class, $menu_set, $elements)
		/*
		 * This function will add interoperability of target class, to this class.
		 * It will add menu set and elements as well
		 * 
		 * Important: Do not add namespace on the elements!
		 */
		{				
			foreach ($elements as $key => $element)
			{
//				$elements[$key]['interop']=($this->find_namespace($this) ? $this->find_namespace($this).'\\' : "").get_class($this);
				$elements[$key]['interop']=get_class($this);
				
				/*
				 * If this class junction existed as global or fully defined, then prioritise it 
				 * otherwise 
				 * 
				 * if the class exists on from a namespace, then automatically add namespace. 				 
				 */
				if (class_exists($element[junction]))
					$elements[$key][junction]=$element[junction];
				else if ($this->find_namespace($this))
					$elements[$key][junction]=$this->find_namespace($this).'\\'.$element[junction];
			}
//			if ('ext_procurer\lib_procurer'==get_class($this)) var_dump($elements);
			
			$this->interoperability[$class]['menu_set'][]=$menu_set;
			$this->interoperability[$class][$menu_set][elements][]=$elements;
		}
		
		function add_interop_data($class, ...$data)
		/*
		 * This function will add interoperability of target class, to this class.
		 * It will transfer data to it
		 */
		{
			foreach ($data as [n => $name, v => $value])
			{
				$this->interoperability[$class]['data'][$name]=$value;
			}
		}
		
		function get_interop_data($name, $class=null)
		{
			return $class ? ($this->interop_data[$name][$class]) : $this->interop_data[$name];
		}
		
		function search_interoperability($extensions)
		{
			$this_class=get_class($this);
			foreach ($extensions as $key => $extension)
				if ($this_class!=$key)
				{										
					if ($interop=$extension->get_interoperability($this_class))
					{						
						if (isset($interop['menu_set']))
							foreach ($interop['menu_set'] as $menu_set)
							{								
								$this->interop_menu[$menu_set][$key]=$key;
//								if (exp_weaver==$this_class) var_dump($menu_set, $key);								
							}
						if (isset($interop['data']))
							if (isset($interop['data']))
							{
								foreach ($interop['data'] as $data_name => $value)
									$this->interop_data[$data_name][$key]=$value;
							}
						if (isset($interop['list']))
						{
							foreach ($interop['list'] as $list_name => $list)
								$this->interop_list[$list_name][$key]=$list;
						}
					}
				}			
		}
		
		var $lists;
		
		function use_legacy_list($list_name, $credential=null)
		/*
		 * Compatibility to use legacy list made using older ways
		 * to maintain discipline of access using get_list
		 */
		{
			if ($credential==null)
			{
				$list=$this->get_global($list_name);
				$credential=$list->credentials->primary();
			}
			else
				$list=$this->get_global($list_name)[$credential];
				
				
			$this->list[$list_name][$credential]=$list;
				
			return $this->list[$list_name][$credential];
		}
		
		function request_list($local_name, $maker, $list_name, $credential)
		/*
		 * This method is used to request list and rename it differently to be accessed
		 * as local list
		 */
		{
			$this->lists[$local_name][$credential]=$this->get_global($maker)->get_list($list_name, $credential);
			
			return $this->lists[$local_name][$credential];
		}
		
		function new_list($list_name, $list_class, $item_class, $credential,
			$table_name, $db_list_version=dbm::dbm_record_default, $default_dbfill_condition=[], $plain_datatable=0,
			$load_mode=advanced_list::DEFERRED_DIRECT) : advanced_list
			/*
			 * Automatically creates new list and its standard data table
			 * if plain_datatable is neither 0 or 1, it will not create data table
			 */
		{
			switch ($plain_datatable)
			{
				case 1:
					$this->dm->create_plainname_datatable($table_name);
					break;
				case 0:
					$this->dm->create_standard_datatable($table_name, $credential);
					break;
			}
			
			$list=$this->dm->new_list($this->credentials,
				$list_name, $list_class, $item_class, $credential, $table_name);
			
			$list->set_owner($this);
			$list->set('list_version', $db_list_version);
			$list->set('deferred_loading', $load_mode);
			
			$this->lists[$list_name][$credential]=$list;
			
			if ($this->select('namespace'))
				$this->lists[$list_name][$credential]->namespace=$this->select('namespace');
				
			if (!empty($default_dbfill_condition))
				$this->lists[$list_name][$credential]->add_default_condition($default_dbfill_condition);
					
			return $this->lists[$list_name][$credential];
		}
		
		function get_list($list_name, $credential=null) : smart_list
		{
			$list=null;
			if ($this->lists[$list_name])
				if ($credential)
					$list=$this->lists[$list_name][$credential];
				else
					$list=$this->lists[$list_name][array_key_first($this->lists[$list_name])];
						
			return $list;
		}
		
		function get_every_list_name($list_name)
		/*
		 * Get each list of the name
		 */
		{
			$list=null;
			if ($this->lists[$list_name])
				$list=$this->lists[$list_name];
				
			return $list;
		}
		
		function add_member_class($name, $class_name, $class)
		/*
		 * this function is used add member class to another class
		 * e.g. marketing_weaving to marketing (global) and real processing
		 * will be done in load_member_class
		 * this should be used when one class actually uses another's to work
		 */
		{
			$this->member_class[$name]['name']=$class_name;
			$this->member_class[$name]['class']=$class;
		}
		
		function load_member_class()
		/*
		 * This function will search the required class and registers its
		 * member class
		 */
		{
			foreach ($this->member_class as $name => $data)
			{
				if ($class=$this->get_global($name))
					$class->register_member_class($data['name'], $data['class']);
			}
		}
		
		function register_member_class($class_name, $class)
		/*
		 * This function will register the member class to this class
		 * it will be called by lib_settings associate_extensions
		 * and result in registration of the class
		 */
		{
			$this->member_class[$class_name]=$class;
		}
		
		function member_menu($class, $function, ...$params)
		/*
		 * This method will be executed on available menu as class
		 */
		{
			return $class->{$function}(...$params);
		}
		
		var $interop_list;
		function add_interop_list($list_name, $class, $list)
		/*
		 * This is used to share a list from an extension to this extension
		 *
		 * $list_name : is the name of the list, can be attached from multiple extendsion to this extension
		 * $class : is the extension who receive this list
		 * $list : the actual list shared
		 *
		 * One owner can only one share list name, and it does not support dual list
		 */
		{
			$this->interoperability[$class]['list'][$list_name]=$list;
		}
				
		function get_interop_list($list_name, $owner=null)
		/*
		 * This is used to get the list added in add_interop_list
		 * you will need the list name with optional list owner
		 */
		{
			if ($owner)
				return $this->interop_list[$list_name][$owner];
			else
				return $this->interop_list[$list_name];
		}
		
		function interop_validate_extending($class)
		{
			if (!isset($this->interoperability[$class]))
				throw new Exception('Not a Valid Extending Interop');			
		}
		
		function get_facility_group($extension_credentials)
		{
			$f_group=[];
			$master_group=array_keys($this->master_credentials);
			foreach ($master_group as $master)
			{
				$f_group[$master]=[];
				foreach ($extension_credentials as ['name' => $name])
					$f_group[$master][]=$this->get_credential_from_master($master, $name);
			}
			
			return $f_group;
		}
		
		function report_collectors_enabled()
		{
			return false;
		}
		
		final function validate_choices($needle, $haystack='facilities', $exception='Incorrect validation parameters')
		/*
		 * This method is used to validate system parameters passed using web and a non user input
		 * 
		 * haystack will be changed to non optional in the future 
		 */
		{
			if (null!=$this->get($haystack, $needle))
				$result=$needle;
			else
				throw new Exception($exception);
					
			return $result;
					//return \lib_form::validate_choices($needle, $haystack);
		}		
	}
	
	class extension_user_management_edit_form extends \inputform_control
	/*
	 * User management form for it
	 */
	{
		var $divisions;
		var $counterform;
		
		function __construct($credentials, $name, $secure_db, $readonly_db, $divisions)
		{
			parent::__construct($credentials, $name, null, POST, null, $secure_db, $readonly_db);
			
			$this->rv_input_step=[];
			$this->rv_output_step=['stepZero'];
									
			$this->add_credential($credentials->primary());
			
			$this->divisions=$divisions;
		}
		
		function input($input, ...$fields)
		{			
			parent::input($input, ...$fields);
			
			$this->revert_input_step(0);
			
			return $this->input_step;
		}	
		
		function stepZero()
		{
			$result=[];
			$result['form_open']=$this->form_open();
			$result['caption']=$this->select('caption');
			$result['employee_list']=$this->html_dropdown('employee_list');
			$result['save']=$this->html_submit('save');
			$result['credential']=$this->html_credential();
			$result['form_close']=$this->form_close();
									
			return $result;
		}
				
		function load_division_cache()
		/*
		 * This will output in drorpdown array compatible format hence it can be output into dropdown
		 */
		{
			$query="SELECT * FROM ".$this->divisions['table'];
			$q_result=$this->secure_db->query($query);
			$formatresult=$this->secure_db->get_query($q_result);
			
			$result=[];
			while ($row=$formatresult->fetch_array())
			{
				$result[$row[0]]=[value => $row[0], display => $row[0]];
			}
			
			return $result;
		}
		
		function get_outside_division($e_div)
		{
			$result=[];
			
			$employee_list=$this->get_global('lib_credential')->get_list('employee_list');
			
			foreach ($employee_list as $employee)
			{
				if (!isset($e_div[$employee->username]))
					$result[$employee->username]=[value => $employee->username, display => $employee->username];
			}
			
			return $result;
		}	
		
		function initialise($caption, $mode, $extension_hash)
		{
			$this->add_submit_button('', 'save', $mode);
			$this->add_hidden_input('caption', $caption);
			$this->add_mandatory_input('_extension', $extension_hash);
			
			$e_div=$this->load_division_cache();	
			if ($mode==='Delete')
				$employees=$e_div;
			else
				$employees=$this->get_outside_division($e_div);
			
			$this->add_dropdown('Nama', 'employee_list', $employees, '', [sort => true, multiple => true, size => 5, required => true]);			
		}
		
		function update()
		{
			$employee_ids=[];
			foreach ($this->select('employee_list') as $id)
				$employee_ids[$id]=[value => $id, display => $id];
				
			switch ($this->fields['save']['value'])
			{
				case 'Delete':
				{					
					$this->remove_dropdown_item('employee_list', ...$this->select('employee_list'));
										
					$this->counterform->add_dropdown_item('employee_list', ...$employee_ids);
					
					$this->process_delete();
					break;
				}
				case 'Insert':
				{
					$this->remove_dropdown_item('employee_list', ...$this->select('employee_list'));
					
					$this->counterform->add_dropdown_item('employee_list', ...$employee_ids);
					
					$this->process_insert();
					break;
				}
			}
		}	
		
		function process_delete()
		{
			foreach ($this->select('employee_list') as $employee)
			{
				$username=$this->secure_db->SQLSafeString($employee);
				$query = "DELETE FROM ".$this->divisions['table']." WHERE username='".$username."'";
				
				$this->secure_db->query($query);
			}
		}
		
		function process_insert()
		/*
		 * This method is used to insert into division credential table
		 * schecksum is used to sign if its approved by the system.  
		 * 
		 * It should be updated using hash_hmac, but with what password?
		 */
		{
			foreach ($this->select('employee_list') as $employee)
			{
				$username=$this->secure_db->SQLSafeString($employee);
				
				$sChecksum=credentials::getUsernameTableChecksum($employee, $this->divisions['table']);
				$query = "INSERT IGNORE INTO ".$this->divisions['table']."(username, schecksum) ".
						 "VALUES('".$username."', '".$sChecksum."')";
								
				$this->secure_db->query($query);				
			}			
		}
	}
	
	class extension_user_management extends SYSTEM_JUNCTION
	{
		use t_layouter;
		
		function __construct($credentials, $input, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
			
			$employee_list=$this->get_global('lib_credential')->get_list('employee_list');
			$employee_list->db_fill();
			
			$settings=$this->get_global('settings');
			//			$this->get_global('data_module')->fill_supplier_list();
			$form_pairs=[];
			
			foreach ($settings->extensions as $key => $extension)
			{
				$hash=$this->hash($key, $this->credentials->loginkey);
				
				if ($hash==$input['_extension'])
				{					
					foreach ($extension->extension_credentials as $cred)
					{
						$form_pair=[];

						$add=new extension_user_management_edit_form($credentials, 'add_'.$cred['name'], $secure_db, $readonly_db, $cred);
						$add->initialise('&nbsp;', 'Insert', $hash);
						$add->set_junction($this);
						
						$layout=new layout_maker($add);
						$layout->set_layout_step('layout_staf_management');
						
						$delete=new extension_user_management_edit_form($credentials, 'del_'.$cred['name'], $secure_db, $readonly_db, $cred);
						$delete->initialise($cred['f_name'], 'Delete', $hash);
						$delete->set_junction($this);	
						
						$layout=new layout_maker($delete);
						$layout->set_layout_step('layout_staf_management');
						
						$add->counterform=$delete;
						$delete->counterform=$add;
						
						$form_pair[]=$delete;
						$form_pair[]=$add;
						
						$form_pairs[]=$form_pair;
						
						switch ($input['_form_name'])
						{
							case $add->name: 
							{
								$add->input($input);
								$add->update();
								break;
							}
							case $delete->name: 
							{
								$delete->input($input);
								$delete->update();
								break;
							}
						};
					}										
				}
			}

			/*
			 * Layout maker for Junction
			 */
			$this->set_layout_maker(new extension_user_management_layout($this));
			$this->layout_maker()->set_page_layout(...$form_pairs);			
		}		
		
		function toHTML()
		/*
		 * Special type of layouter, hence it kept the name toHTML
		 */
		{			
			$forms=[];
			foreach ($this->forms as $key => $form)					
				$forms[$key]=$form->render()[value];
			
			$result=$this->layout_maker()->assign_layout($forms);
			
			return $result;
		}
	}
	
	class extension_user_management_layout extends layout_maker 
	{
		var $page_layout;
		
		function __construct($layouter)
		{
			parent::__construct($layouter);
		}
		
		function set_page_layout(...$form_pairs)
		{
			$result=[];
			
			$result[]=$this->open_html_table([], 'Delete', 'Insert');
			
			foreach ($form_pairs as [$delete, $insert])
				$result[]=$this->add_html_table_row($this->to_layout_name($delete->name), $this->to_layout_name($insert->name));
			
			$result[]=$this->close_html_table();
		
			$this->page_layout=$result;
			
			$this->set_layout_step('get_junction_layout');;
			
			return $result;
		}
		
		function get_junction_layout()
		{
			return $this->page_layout;
		}
	}
	
	class components extends entity
	/*
	 * Don't forget to add _userclass and other required input as an mandatory input when using
	 * components variable that is shareable
	 * 
	 * and get the variable using get_global
	 */
	{
		var $variables;
		var $lists;
		
		function __construct()
		{
			$this->variables=[];
			$this->lists=[];
			
			if (!$this->get_global(get_class($this)))
				$this->to_global(get_class($this), $this);
			
			$this->makeArray('user');
		}
		
		function new_list($dm, $list_name, $list_class, $item_class, $credential,
			$table_name) : smart_list
		/*
		 * This is used to create new list that is used / shared by this component
		 * and not with others
		 */
		{
			$dm->create_standard_datatable($table_name, $credential);
			
			$list=$dm->new_list($this->get_global('lib_credential')->credentials,
				$list_name, $list_class, $item_class, $credential, $table_name);
			
			$list->set_owner($this);
			
			$this->lists[$list_name][$credential]=$list;
			
			return $this->lists[$list_name][$credential];
		}
		
		function get_list($list_name, $credential) : smart_list
		/*
		 * This must be accessed using credential hence not everyone can access
		 * the list
		 */
		{
			$list=null;
			if ($this->lists[$list_name])
				if ($credential)
					$list=$this->lists[$list_name][$credential];
						
			return $list;
		}		
		
		function add_user($user_class)
		/*
		 * Adding user of the components, e.g. class formal name
		 * Only user and specified
		 */
		{
			$class_name=is_string($user_class) ? $user_class : get_class($user_class);
			
			$this->addData('user', $class_name);
		}
				
		function add_permission($user1, $user2)
		/*
		 * Making permssion
		 */
		{
			$this->addData('permission', $user1, $user2);
		}
		
		var $lists_version;
		
		function db_list_set_version($list_name)
		{
			$this->lists_version[$list_name]=2;
		}
		
		function db_list_get_version($list_name)
		{
			$result=dbm_record_legacy;
			
			if (isset($this->lists_version[$list_name]))
				$result=$this->lists_version[$list_name];
				
			return $result;
		}
		
/*		function select($user_class, $name=null)
		{
			$class_name=is_string($user_class) ? $user_class : get_class($user_class);
			
			return ($this->variables[$class_name][$name]);
		}*/
	}

	trait multi_facilities
	{		
		function validate_facility($needle)
		/*
		 * Call to validate_choices
		 */
		{
			return $this->validate_choices($needle, 'facilities');					
		}		
	}
?>