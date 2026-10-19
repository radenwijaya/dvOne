<?php
	define('encryption_chiper', 'aes-128-cbc');
	define('decryption_chiper', encryption_chiper);

	include_once("lib_base_list.php");
			
	class lib_settings extends entity
	{		
		use lib_html;
		use runtime_settings;
		
		var $secure_db;
		var $readonly;
		var $extensions;
		
		var $promised;
		
		var $credentials;
		var $data_module;
		var $primaryTable;		
		
		static function user_changable_settings()
		{
			return 
				[
					'ReportingEmail' => 1,
				 	'shift_length' => 1,
				 	'shift_number' => 1,
				 	'ShopEmail' => 1,
					'ShopFaxNo' => 1,
					'ShopPhoneNo' => 1,
					'ShopWebsite' => 1,
					'SpinningModuleCtr' => 1,
					'WeavingModuleCtr' => 1,
					'FinishingModuleCtr' => 1
				];
		}
				
		/*
		 * These functions dealy the loading of extensions and choose only when they are needed
		 */
		function promise_extension($extension, ...$params)
		{
			$this->promised[$extension]=$params;
		}
		
		function is_promised($extension)
		{
			return isset($this->promised[$extension]) ? $this->promised[$extension] : null;
		}
		
		function load_extensions(...$extensions)
		/*
		 * Load promised extensions, if empty then will load everything
		 * 
		 * Use this to replace get_global when multiple get_global are used
		 */
		{
			if (empty($extensions))
				$extensions=array_keys($this->promised);
			
			foreach ($extensions as $extension)
				if ($params=$this->is_promised($extension))
				{
					$this->add_extension(new $extension(...$params));
				}
			
			$this->associate_extensions();
		}
				
		/*
		 * End
		 */
		
		function add_extension($extension)
		{
			$this->extensions[get_class($extension)]=$extension;			
		}
				
		function associate_extensions()
		{
			foreach ($this->extensions as $extension)
			{
				$extension->search_interoperability($this->extensions);
				$extension->load_member_class();
			}
		}
				
		function load_settings()
		/*
		 * Used to load settings from database and remove settings
		 * 
		 * Database 'value' might be json array might not be json array
		 * 
		 * therefore needs to be checked
		 */
		{
			$query='SELECT * FROM '.ctableSettings;
			$q=$this->secure_db->query($query);
			
			while ($row=$this->secure_db->next_result($q))
			{
				if ($this->select('spec'))
				{					
					$row['spec'];					
					$values=json_decode($row['value']);
					if (json_last_error()==JSON_ERROR_NONE)
						foreach ($values as $index => $value)
							$this->addData($row['spec'], $value, $index);
					else
					{
						$this->addData($row['spec'], $row['value']);
					}
				}				
			}
		}
		
		function add_settings_variable()
		/*
		 * Temporary, later add to database
		 */
		{
			$this->addData('blockchain', 0, 'enabled');
			$this->addData('blockchain', 'sha3-256', 'algo');		
			$this->addData('blockchain', 0, 'diff');
			
			/*
			 * This will delay decoding of rows until when its needed
			 * or using select
			 * 
			 * This is not yet implemented
			 */
			$this->addData('delayed_decode', 0);
		}
		
		function __construct($secure_db, $data_module, $readonly=true)
		{			
			$this->secure_db=$secure_db;
			$this->primaryTable=ctableSettings;
			$this->readonly=$readonly;
			$this->extensions=[];
			$this->promised=[];
			$this->data_module=$data_module;
			
			parent::__construct();
			
			$this->load();
			
			$this->to_global('settings', $this);
			
			$this->add_settings_variable();
		}
		
		function load()
		{
			$query='SELECT * FROM '.$this->primaryTable;
			$query_no=$this->secure_db->query($query);
			if ($this->secure_db->num_rows($query_no)>0)
			{
				while ($row=$this->secure_db->next_result($query_no))
				{					
					$this->addData($row['spec'], $row['value']);
				}
			}
		}
		
		function save($spec, $value)
		{
			$spec=$this->secure_db->SQLSafeString($spec);
			$value=$this->secure_db->SQLSafeString($value);
			
			$query='INSERT INTO '.$this->primaryTable."(spec, value) VALUES ('".$spec."', '".$value."') ON DUPLICATE KEY UPDATE value='".$value."'";
			$this->secure_db->query($query);
		}
		
		function get_module_credentials($module_name, $module_constant)
		{
			$dynamic_credentials=array();
			$counter=$this->select($module_constant);
			for ($i=1; $i<=$counter; $i++)
			{
				$credential_name="$module_name"."_$i";
				$credential_table="dcredTable".$module_name."_$i";
				$credential_fname="$module_name"." $i";
				$dynamic_credentials[]=['table' => $credential_table, 
										'name' => $credential_name, 
										'f_name' => $credential_fname,
										'module' => $module_name];
				
				$this->addData($credential_name, $credential_fname, 'f_name');
				$this->addData($credential_name, $credential_table, 'c_table');
			}
			
			return $dynamic_credentials;
		}
		
		function create_standard_datatable($table, $credential_name)
		{
			$name=$table.'_'.$credential_name;
			$tablename='T'.hash('haval128,3', $name);
						
//			var_dump($credential_name.' '.$table.' '.$tablename);
			
			$this->addData($credential_name, $tablename, $table);
			
			if (!$this->readonly)
			{
				$query="CREATE TABLE IF NOT EXISTS ".$tablename."(uniqueid VARCHAR (128) NOT NULL, data MEDIUMTEXT, PRIMARY KEY (uniqueid))";
			
				$this->secure_db->query($query);
				
				$this->secure_db->archive_query($query);				
			}
			
			return $tablename;
		}
		
		var $div_array;
		function credential_display($name)
		{
			$div_array=[];
			$divisions=$this->get_global('system_credentials_list');
			foreach ($divisions as $div)
				$div_array[$div['name']]=['value' => $div['name'], 'display' => $div['f_name']];
			
			$this->div_array=$div_array;
			
			return $div_array[$name]['display'];
		}
				
		function associate_credentials_facility($master_module, ...$module_credentials)
		{			
			foreach ($module_credentials as $credentials)
			{
				$i=1;
				foreach ($credentials as $credential)
				{
					$this->addData('facility_credentials', $master_module.'_'.$i, $credential['name']);
					$this->addData($credential['module'], $credential['name'], $master_module.'_'.$i);
					$i++;
				}
			}		
		}
		
		function get_credentials_module_check($module_constant, ...$modules)
		{
			$result=array();
			
			$counter=$this->select($module_constant);
			for ($i=1; $i<=$counter; $i++)
				foreach ($modules as $module)
					$result[]=$module.'_'.$i;
				
			return $result;
		}
						
		function select_all($name, $index)
		{
			$extensions=$this->select($name);
			
			$result=[];
			foreach ($extensions as $extension)
			{
				$models=$this->select($extension, $index);
				if ($models)
					foreach ($models as $key => $value)
						$result[$key]=$value;
			}
			
			return $result;
		}
		
		function facility_division($facility)
		/*
		 * Returns all facility division from that facility group
		 */
		{
			$div=[];
			$f=is_array($facility) ? $facility : [$facility];
			$fg=$this->select('facility_group');
			
			foreach ($f as $input_f)
			{
				
//				var_dump($divisions, $input_f); echo("<BR><BR>");
				foreach ($fg as $facilities)
				{
					if ($divisions=$facilities[$input_f])					
						$div=[...$div, ...$divisions];					
				}					
			}
//			var_dump($facility, $div);
//			echo("<BR><BR>");
//			var_dump($div);		
			return $div;
		}		
		
		function get_facility($division)
		/*
		 * Used to return facility from a division
		 */
		{
			$fg=array_keys($this->select('facility_group'));
//			var_dump($this->get_global('lib_human_resources')->select('facility_group'));
			foreach ($fg as $ext)
			{				
				if ($facility=$this->get_global($ext)->get_master_from_credential($division))
					break;
			}
						
			if (!$facility)
				$facility=$division;
			
			return $facility;
		}				
	}
	
	class settings_form extends inputform_control
	/*
	 * A form to change the settings based on default setting as the template
	 */
	{
		function prepare_input_field($root, $name_string='')
		{
			foreach ($root as $name => $value)
			{
				if (is_array($value))
				{
					$this->prepare_input_field($value, $name);
				}
				else
				{
					$f_name=$name_string ? $name_string.'_'.$name : $name;
					
					$this->add_text_input($name, $f_name, $value, $value, 128, ['required' => true, 'readonly' => true]);
				}
			}
		}
		
		function __construct($credentials, $name, $secure_db, $readonly_db, $input)
		{
			parent::__construct($credentials, $name, null, 'post', null, $secure_db, $readonly_db);
			
			$this->add_mandatory_input('mode', $input['local'] ?? 'settings');
			$datasource=$this->get_global($this->select('mode'));
						
			$this->prepare_input_field($datasource->data);
			
			$this->rv_input_step=['save', 'confirm'];
			$this->rv_output_step=['stepZero', 'stepOne', 'stepTwo'];
			
			$this->add_submit_button('', 'Save', 'save');
			$this->add_credential($credentials->primary());
		}
		
		function output_input_field($root, $name_string='', $level=2)
		{
			$result="<h$level>".$name_string."</h$level>";
			
			foreach ($root as $name => $value)
				if (lib_settings::user_changable_settings()[$name]==1)
				{
					if (is_array($value))
					{
						$result.=$this->output_input_field($value, $name, $level+1);
					}
					else
					{
						$f_name=$name_string ? $name_string.'_'.$name : $name;
						
						$result.=$this->html_text($f_name, $name_string.'_'.$name, $value, $value, 128, 
							['required' => true, 'readonly' => true]).'<br>';
					}
				}
				
			return $result;
		}
		
		function stepZero()
		{
			$result=$this->form_open();
			
			$datasource=$this->get_global($this->select('mode'));
			
			$result.=$this->output_input_field($datasource->data);
			
			$result.=$this->form_close();
			
			return $result;
		}
	}
	
	class settings_interface extends junction
	/*
	 * General interface for system settings, to see and over ride by user from
	 * default settings
	 */
	{
		static function form_caption($mods='')
		{
			return 'Settings';
		}
		
		function form_title($form_title='')
		{
			return parent::form_title('Settings');
		}		
		
		function __construct($credentials, $input, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
			
			$this->form=new settings_form($credentials,
				'settings', $secure_db, $readonly_db, $input);
			$this->form->set_junction($this);
			
			if ($this->form->input($input)==$this->form->final_input_step());
/*			{
				$this->form->insert();
			}*/
		}
	}
	
	trait runtime_settings
	/*
	 * Trait to change runtime settings of the scripts
	 */
	{
		var $memory_size;
		var $exec_time;
		
		function set_memory_size($memory_size='4G')
		{
			$this->memory_size=$memory_size;
			
			ini_set('memory_limit', $memory_size);						
		}
		
		function set_time_limit($exec_time)
		{
			$this->exec_time=$exec_time;
			
			set_time_limit($exec_time);
			ini_set('max_execution_time', $exec_time);			
		}
		
		function get_memory_size()
		{
			return $this->memory_size;
		}		
	}
	
	class SYSTEM extends L
	{
		static function _CONTENTS()
		{
			return
			[
				'extension_user_management_title' => ['id_ID' => 'Pengaturan Staff'],
				'extension_user_management_caption' => ['id_ID' => 'Pengaturan Staff'],
			];
		}
	}
?>