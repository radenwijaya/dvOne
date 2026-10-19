<?php
	class new_contact_form extends inputform_control
	{		
		use \resource_reader;
		use \messages;
		
		var $list;
		
		function __construct($credentials, $name, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $name, null, POST, null, $secure_db, $readonly_db);

			$this->_set_string_resource('Contact_Strings');
			
			$this->rv_input_step=['OK'];
			$this->rv_output_step=['stepZero', 'stepOne'];
			
			$countries=$this->resource_read('material', 'resources/countries.csv');
			$dropdown=$this->resource_to_dropdown($countries, [value => 'Code', display => 'Country']);
			$this->add_dropdown('Negara', 'country', $dropdown, '', ['required' => true], '-');
			
			$this->add_text_input('Name', 'name', '', '', 32, [required => true]);
			$this->add_text_input('Contact', 'pic', '', '', 32, [required => true]);
			$this->add_textarea_input('Address', 'address', '', '', 128, [required => true, size => 64]);
			$this->add_text_input('City', 'city', '', '', 32, [required => true]);
			$this->add_text_input('Post Code', 'postcode', '', '', 6, [required => true]);
			
			$this->list=$this->get_extension('contacts')->get_list('contact_list')->db_fill();
			$ids=$this->list->extract('id');
			$max=empty($ids) ? 0 : max($ids);
			
			$id_ph=str_pad($max+1, 5, 0, STR_PAD_LEFT);
			$this->add_text_input('Member ID', 'id', $id_ph, $id_ph, 5, [required => true, writeprotect => true]);
			
			$this->add_text_input('Mobil', 'mobile', '', '', 16, [required => true]);
			$this->add_text_input('Work', 'work', '', '', 16, [required => true]);
			$this->add_email_input('Email', 'email', '', 64, [required => true]);

			$this->add_text_input('NPWP', 'tfn', '', '', 16, []);
			$this->add_text_input('KTP', 'ic', '', '', 16, []);
			
			$this->add_credential($credentials->primary());
			
			$this->add_submit_button();						
		}
		
		function stepZero()
		{
			$fields['form_open']=$this->form_open();
			$fields['name']=$this->html_text('name');
			$fields['pic']=$this->html_text('pic');
			$fields['address']=$this->html_textarea('address');
			$fields['city']=$this->html_text('city');
			$fields['country']=$this->html_dropdown('country');
			$fields['postcode']=$this->html_text('postcode');
			$fields['id']=$this->html_text('id');
			
			$fields['mobile']=$this->html_text('mobile');
			$fields['work']=$this->html_text('work');
			$fields['email']=$this->html_text('email');
			$fields['tfn']=$this->html_text('tfn');
			$fields['ic']=$this->html_text('ic');
			
			$fields['credential']=$this->html_credential();
			
			$fields['submit']=$this->html_submit();
			
			$fields['form_close']=$this->form_close();
						
			return $this->layout_maker()->assign_layout($fields);
		}
		
		function stepOne()
		{
			$fields['form_open']=$this->form_open();
			$fields['name']=$this->html_hidden_display('name');
			$fields['pic']=$this->html_hidden_display('pic');
			$fields['address']=$this->html_hidden_display('address');
			$fields['city']=$this->html_hidden_display('city');
			$fields['country']=$this->html_hidden_display('country');
			$fields['postcode']=$this->html_hidden_display('postcode');
			$fields['id']=$this->html_hidden_display('id');
			
			$fields['mobile']=$this->html_hidden_display('mobile');
			$fields['work']=$this->html_hidden_display('work');
			$fields['email']=$this->html_hidden_display('email');
			$fields['tfn']=$this->html_hidden_display('tfn');
			$fields['ic']=$this->html_hidden_display('ic');
			
			$fields['credential']=$this->html_credential();
			
			$fields['form_close']=$this->form_close();
			
			$fields['exit']=$this->exit_form()->toStringFormat();
			
			return $this->layout_maker()->assign_layout($fields);
		}
		
		
		function insert()
		{
			$this->list=$this->get_extension('contacts')->get_list('contact_list')->db_fill();
			
			if (!$this->list->db_select($this->select('id')))
			{
				$c=$this->list->new($this->select('id'));
				
				$c->set('id', $this->select('id'));
				$c->set_name($this->select('name'));
				
				$c->set('pic', $this->select('pic'));
				
				$c->set('address', $this->select('address'));
				$c->set('city', $this->select('city'));
				$c->set('country', $this->select('country'));
				$c->set('postcode', $this->select('postcode'));
				
				$c->set('mobile', $this->select('mobile'));
				$c->set('work', $this->select('work'));
				$c->set('email', $this->select('email'));
				
				$c->set('tfn', $this->select('tfn'));
				$c->set('ic', $this->select('ic'));
				
				$c->flush();								
			}
			else
			{
				/*
				 * Shall never reach here, unless on really special circumstances
				 */
				$this->_ERROR(0);
			}
		}
		
		function input($input, ...$fields)
		{
			parent::input($input, ...$fields);
						
			return $this->input_step;
		}
	}
	
	class new_contact_layout extends layout_maker
	{
		function __construct($layouter)
		{
			parent::__construct($layouter);
			
			$this->set_layout_step('default_layout', 'exit_layout');
		}
		
		function default_layout()
		{
			$result=[];
			
			$result[]='%form_open%';
			$result[]='<h4>Member Details</h4>';
			$result[]='<div>';
			$result[]='%name%<br>';
			$result[]='%pic%<br>';
			$result[]='%address%<br>';
			$result[]='%city%<br>';
			$result[]='%country%<br>';
			$result[]='%postcode%<br>';
			$result[]='%id%';
			$result[]='</div>';
			
			$result[]='<h4>Member Contact</h4>';
			$result[]='<div>';
			$result[]='%mobile%<br>';
			$result[]='%work%<br>';
			$result[]='%email%<br>';
			$result[]='%tfn%<br>';
			$result[]='%ic%';
			$result[]='</div>';
						
			$result[]='%credential%';
			$result[]='<br>%submit%';
			$result[]='%form_close%';
			
			return $result;
		}
		
		function exit_layout()
		{
			$result=[];
			
			$result[]='%form_open%';
			$result[]='<h4>Member Details</h4>';
			$result[]='<div>';
			$result[]='%name%<br>';
			$result[]='%pic%<br>';
			$result[]='%address%<br>';
			$result[]='%city%<br>';
			$result[]='%country%<br>';
			$result[]='%postcode%<br>';
			$result[]='%id%';
			$result[]='</div>';
			
			$result[]='<h4>Member Contact</h4>';
			$result[]='<div>';
			$result[]='%mobile%<br>';
			$result[]='%work%<br>';
			$result[]='%email%<br>';
			$result[]='%tfn%<br>';
			$result[]='%ic%';
			$result[]='</div>';
			
			$result[]='%credential%';
			$result[]='%form_close%<br>';
			
			$result[]='%exit%';
			
			return $result;
		}		
	}
	
	class new_contact extends junction
	{
		function form_title($form_title='')
		{
			return parent::form_title('Contact');
		}
		
		static function form_caption($mods='')
		{
			return 'Add New Contact';
		}
		
		function __construct($credentials, $input, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
			$this->form=new new_contact_form($credentials,'new_contact', $secure_db, $readonly_db);
			$this->form->set_junction($this);
			
			$this->form->set_layout_maker(new new_contact_layout($this->form));
			
			if ($this->form->input($input)==$this->form->final_input_step())
			{
				$this->form->insert();
			}
		}		
	}
	
	class list_contacts extends junction
	{
		var $list;
		
		function form_title($form_title='')
		{
			return parent::form_title('List Contacts');
		}
		
		static function form_caption($mods='')
		{
			return 'List Contacts';
		}
		
		function __construct($credentials, $input, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
 
			$this->list=$this->get_extension('contacts')->get_list('contact_list')->db_fill();
			
			$this->list->addDisplay('get', 'MID', 'id');
			$this->list->addDisplay('get', 'Name', 'name');
			$this->list->addDisplay('get', 'PIC', 'pic');
			$this->list->addDisplay('full_address', 'Address', '');
			$this->list->addDisplay('get', 'Email', 'email');
			$this->list->addDisplay('phone', 'Phone', '');
		}
		
		function toHTML()	
		{ 			
			$result=$this->list->toHTML();
			
			return $result;;
		}
	}
	
	class transfer_member extends junction
	{
		var $list;
		
		function form_title($form_title='')
		{
			return parent::form_title('Transfer Member to Contacts');
		}
		
		static function form_caption($mods='')
		{
			return 'Transfer Member';
		}
		
		function __construct($credentials, $input, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
			
			$this->list=$this->get_extension('contacts')->get_list('contact_list')->db_fill();
			
			$this->list->addDisplay('get', 'MID', 'id');
			$this->list->addDisplay('get', 'Name', 'name');
			$this->list->addDisplay('get', 'PIC', 'pic');
			$this->list->addDisplay('full_address', 'Address', '');
			$this->list->addDisplay('get', 'Email', 'email');
			$this->list->addDisplay('phone', 'Phone', '');
			$this->list->addDisplay('tfn', 'NPWP', '');

/*			foreach ($this->list as $e)
				$e->dispose();*/
			
			$cl=$this->get_global('lib_member')->get_list('customer_list');
			foreach ($cl as $c)
			{
				if (!$this->list->db_select($c->get('id')))
				{
					$element=$this->list->new($c->get('id'));
					$element->set('id', $element->get('uniqueid'));
					
					$element->set_name($c->get('name'));
					
					$element->set('pic', $c->find_contact());
					
					$element->set('address', $c->get('address'));
					$element->set('city', $c->get('suburb'));
					$element->set('postcode', $c->get('postcode'));
									
					$element->set('work', $c->get('work'));
					$element->set('mobile', $c->get('mobile'));
					$element->set('email', $c->get('email'));
					
					$element->set('country', 'ID');
					
					$element->set('type', contacts::customer);
									
					if ($c->is_company())
						$element->set('tfn', $c->get('tfn'));
					else
						$element->set('ic', $c->get('tfn'));
					
					$element->flush();
						
					$this->list->add($element);	
				}
			}
		}
		
		function toHTML()
		{
			$result=$this->list->toHTML();
			
			return $result;;
		}
	}
	
	class transfer_supplier extends junction
	/*
	 * Disable this after transfer
	 */
	{
		var $list;
		
		function form_title($form_title='')
		{
			return parent::form_title('Transfer Supplier to Contacts');
		}
		
		static function form_caption($mods='')
		{
			return 'Transfer Supplier';
		}
		
		function __construct($credentials, $input, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
			
			$this->list=$this->get_extension('contacts')->get_list('contact_list')->db_fill();
			
			$this->list->addDisplay('get', 'MID', 'id');
			$this->list->addDisplay('get', 'Name', 'name');
			$this->list->addDisplay('get', 'PIC', 'pic');
			$this->list->addDisplay('full_address', 'Address', '');
			$this->list->addDisplay('get', 'Email', 'email');
			$this->list->addDisplay('phone', 'Phone', '');
			$this->list->addDisplay('tfn', 'NPWP', '');
			
			$cl=$this->get_global('ext_procurer\lib_procurer')->get_list('supplier_list');
			foreach ($cl as $c)
			{
				if (!$this->list->db_select($c->get('id')))
				{
					$element=$this->list->new($c->get('id'));
					$element->set('id', $element->get('uniqueid'));
					
					$element->set_name($c->get('name'));
					
					$element->set('pic', $c->get('contact'));
					
					$element->set('address', $c->get('address'));
					
					$element->set('email', $c->get('email'));
					
					switch ($c->get('lang'))
					{
						case "en_US":
							$element->set('country', 'US');
						case "id_ID":
							$element->set('country', 'ID');
						case "zh_CN":
							$element->set('country', 'CN');
					}
									
					$element->set('type', contacts::supplier);
					
					$element->flush();					
											
					$this->list->add($element);
				}
			}
		}
		
		function toHTML()
		{
			$result=$this->list->toHTML();
			
			return $result;;
		}
	}	
?>