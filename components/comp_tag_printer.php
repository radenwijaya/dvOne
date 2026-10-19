<?php
	class comp_tag_printer extends components
	/*
	 * This class will be initialised by each of extension that requires it
	 * and will store information to it
	 */
	{
		function __construct()
		{
			parent::__construct();
		}
	}
	
	trait tag_maker
	{
		/*
		 * Add this trait on any record or its desceendent so  
		 * it will be able to harvest information of its tag
		 */
	
		var $_tag_ordering;
		
		function add_tag_information($name, $display, $type, $format=null)
		/*
		 * Adding tag information, e.g. what is written on tag 
		 */
		{
			if (!isset($this->tag_visible))
				$this->tag_visible=[];
				
			switch ($type)
			{
				case 'function':
					if ((method_exists($this, $name)))
					{
						$value=$this->{$name}();
						break;
					}
				case 'get': case 'select': case 'qr': 						
					$value=$this->get($name);
					break;
				case 'text': 
					$value=$name;
					break;
			}
			
			if ($format)
				$value=$this->{$format}($value);
			
			$hash_name=$this->quick_hash($name);
			
			$this->_tag_ordering[]=['type' => $type, 'hash' => $hash_name, 'display' => $display, 'value' => $value];
		}
				
		function get_tag_information()
		/*
		 * Returns tag ordering 
		 */
		{
			return $this->_tag_ordering;
		}
						
		function prepare_tag()
		/*
		 * Example of prepare_tag, please override on the actual  class
		 */
		{
			$this->add_tag_information('uniqueid', 'Unique ID', 'qr');
			$this->add_tag_information('vanity_name', 'Name', 'function');
		}
		
		function clone_tag($original_tag_maker)
		/*
		 * This method is used to copy tag information from original tag maker
		 */
		{
			if ($original_tag_maker->is_tag_maker())
			{
				$original_tag_maker->prepare_tag();
				
				$this->_tag_ordering=$original_tag_maker->get_tag_information();			
			}
		}		
		
		function is_tag_maker()
		{
			return true;
		}
		
		function vanity_name()
		/*
		 * Override in actual class, it was here to prevent the system from breaking
		 */
		{
			return $this->select('uniqueid');
		}
	}
	
	trait tag_printer
	/*
	 * Add this strait on a form or its inheritance to enable tag printing
	 */
	{
		var $_tag_printer_queue;
		
		function print_tag($tag_maker)
		{
			$result='<div>';
						
			$qr='';
			
			$table=$this->open_html_table([], 'Nama', 'Nilai');			
			$show_table=false;
			foreach ($tag_maker->get_tag_information() as ['type' => $type, 'display' => $display, 'value' => $value])
			{
				if ($type!='qr')
				{
					$table.=$this->add_html_table_row($display, $value);
						
					$show_table=true;
				}
				else
				{					
					$qr_code=ext_QRCode::headerless_text_png($value);
					
					$qr.="<img src='data:image/png;base64,$qr_code' alt='$display'>";					
				}
			}
			
			$table.=$this->close_html_table();
			
			if ($qr)
				$result.=$qr;
			if ($show_table)
				$result.=$table;
			
			$result.='</div>';
			
			return $result;
		}
		
		function add_tag_print_queue($element)
		{
			if (!$this->_tag_printer_queue)
				$this->_tag_printer_queue=[];
				
			$this->_tag_printer_queue[]=$element;
		}
		
		function master_print_tag()
		{
			$list=$this->_tag_printer_queue;
			
			foreach ($list as $element)
			{				
				$element->prepare_tag();
				
				$result.=$this->print_tag($element);
				//				$result.=$this->print_tag($list->db_select($yarn));
			}
			
			$result.='<br>'.$this->html_print();
			return $result;
		}		
		
		function enable_tag_css()
		{
			$css=new \css($this->credentials, 'components/tag.css');
			 
			if ($this->interface instanceof \junction)
			{
				$this->interface->add_page_features($css);
			}
			
			return $css;
		}
	}
	
	class list_tag_printer extends junction
	/*
	 * Generalised class to print QR tag on a physical asset
	 *
	 * Derive from this class to have an asset tag printer class
	 * 
	 * check loom_qr_tag_printer for example on how to derive it
	 */
	{
		var $list;
		
		static function form_caption($mods='')
		{
			return 'QR Tag Printer';
		}
		
		function form_title($form_title='')
		{
			return parent::form_title($form_title);
		}
		
		function __construct($credentials, $input, $secure_db, $readonly_db, $list)
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
			
			$this->list=$list;
		}				
	}
	
	class list_tag_printer_form extends inputform_control
	/*
	 * Directly usable form for tag printing
	 */
	{
		use tag_printer;
		
		function __construct($credentials, $name, $secure_db, $readonly_db, $list_hr_name, $list, $extra_input)
		{
			parent::__construct($credentials, $name, null, 'post', null);
			$this->rv_input_step=['Show'];
			$this->rv_output_step=['stepZero', 'stepOne'];
			
			foreach ($extra_input as $field => $value)
				$this->add_mandatory_input($field, $value);
				
			$this->list=$list;
			$items_list=$list->asDropdownArray(['value' => 'getIndex', 'display' => 'vanity_name'], 'display', []);
				
			$this->add_dropdown($list_hr_name, 'elements', $items_list, '', ['required' => true, 'multiple' => true, 'size' => 32]);
				
			$this->add_credential($credentials->primary());
			$this->add_submit_button();
		}
		
		function stepZero()
		{
			$result=$this->form_open();
			
			$result.=$this->html_dropdown('elements').'<br><br>';
			$result.=$this->html_submit();
			
			$result.=$this->html_credential();
			
			$result.=$this->form_close();
			
			return $result;
		}
		
		function stepOne()
		{
			$elements=$this->select('elements');
			foreach ($elements as $element)
				$this->add_tag_print_queue($this->list->select($element));
				
			$result=$this->master_print_tag();
			$result.=$this->exit_form(null, [], ['facility'])->toStringFormat();
				
			return $result;
		}
	}	
?>