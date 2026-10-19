<?php
	/*
	 * A special interface for collection
	 */

	class lib_report_collator extends junction
	/*
	 * This class will register collector user of that certain interface
	 */
	{
		function form_title($form_title='')
		{
			return parent::form_title('Daftar Collator Laporan');
		}
		
		static function form_caption($mods='')
		{
			return 'Report Collator';
		}
				
		function __construct($credentials, $input, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
						
			$this->form=new register_collator_user($credentials, $input['extension'], $input['extension']);
		}
	}
	
	class register_collator_user extends inputform_control
	/*
	 * This class will register collectorr user for a particular class
	 */
	{
		var $collectors;
		
		function __construct($credentials, $name, $extension)
		{
			parent::__construct($credentials, $name, null, 'post', null);
			
			$this->rv_input_step=['Add'];
			$this->rv_output_step=['stepZero', 'stepOne'];
			
			$collectors=$this->get_global($extension)->get_collector_class();
			$this->collectors=$collectors;
			
			$this->get_global('employee_list')->db_fill(); 
			foreach ($collectors as ['class' => $class, 'credentials' => $cred])
			{				
				$employees=$this->get_global('employee_list')->asDropdownArray(['value' => 'getIndex', 'display' => 'getName'], 'display',
					['is_ondivision' => $cred]);
				
				$this->add_dropdown('', 'available_'.$class, $employees, '', [required => true, size => 5, multiple => true]);
				$this->add_dropdown('', 'selected_'.$class, $employees, '', [required => true, size => 5, multiple => true]);
				
				$this->add_submit_button('', 'in_'.$class, '>');
				$this->add_submit_button('', 'out_'.$class, '<');
			}
			
			$this->add_credential($credentials->primary());
		}
		
		function stepZero()
		{
			$result=$this->form_open();

			$result.=$this->open_html_table([], 'Pengguna', '', 'Dipilih');
			foreach ($this->collectors as ['class' => $class])
			{
				$result.=$this->add_html_table_row($this->html_dropdown('available_'.$class), 
					$this->html_button('in_'.$class).'<br>'.$this->html_button('out_'.$class),
					$this->html_dropdown('selected_'.$class));
			}
			$result.=$this->close_html_table();
			
			$result.=$this->html_credential();
			$result.=$this->form_close();
			
			return $result;
		}
	}	

	class report_collator extends lib_form
	/*
	 * report_collector class is a special class that is descending from inputform
	 * and this class will output JSON with report informations that will be
	 * on a collector interface
	 */	
	{
		static function name()
		/*
		 * override with name of this report
		 */
		{
			return '';
		}
	}
			
	class report_collator_interface extends junction
	/*
	 * lib_collector is an extension to junction, and used to call multipole
	 * report collector each with its own permissions
	 * 
	 * i.e.. change welcome.php to this
	 */
	{
		
	}
	
	trait report_collators
	{
		/*
		 * report_collector is stored in the collector_class variable.
		 * This report will be read by lib_collector to be reported
		 *
		 *
		 */
		var $collector_class;
		
		function report_collectors_enabled()
		{
			return true;
		}
		
		function get_collector_class()
		{
			return $this->collector_class ?? [];
		}
		
		function set_collector_class(...$collectors)
		/*
		 * input only class name, actual object as an actual object will made this slow
		 *
		 * collector format is an array as follows [
		 * ['credentials' => $credential
		 * 'class' => $class]
		 */
		{
			$this->collector_class=[];
			
			foreach ($collectors as ['class' => $collector, 'credentials' => $cred])
			{
				$full_name=$this->find_namespace($this).'\\'.$collector;
				if (class_exists($full_name))
					$this->collector_class[]=['class' => $full_name, 'credentials' => $cred];
			}
		}
		
		function collector_menu_name()
		/*
		 * This will return collector menu report name that defaults to class name
		 */
		{
			return get_class($this);
		}
		
		function collector_menu_credential()
		/*
		 * You can override on the child class
		 */
		{
			return $this->credentials->primary();
		}
		
		function menu_set_collector()
		{
			$result['credential']=$this->collector_menu_credential();
			$result['title']='LR '.$this->collector_menu_name();
			
			$result['elements'][]=[junction => '\lib_report_collator', params => ['extension' => get_class($this)]];
			
			return $result;
		}
	}

?>