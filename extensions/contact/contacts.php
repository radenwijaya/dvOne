<?php
	include_once("contact_management.php");
	include_once("Strings.php");
		
	class contacts extends extensions
	{
		/*
		 * this will be used 
		 */
		const internal = 4;
		const customer = 1;
		const supplier = 2;
		const trading = 3;
		
		function __construct($settings, $credentials, $login_data)
		{
			$extension_credentials=[];
			
			parent::__construct($settings, $credentials, $extension_credentials, $login_data);
			
			$list=$this->new_list('contact_list', 'smart_list', 'contact', cProducerChkSec, 'table_contact_list');
			$list->add_db_index('id', 0);
			$list->add_db_index('ic', 0);
			
			$list->db_fill();
		}
		
		function menu_set_1()
		{
			//			var_dump($facility);
			$result[credential]=cFCChkSec;
			$result['title']='Contact';
			
			$result[elements][]=[junction => 'new_contact'];
			$result[elements][]=[junction => 'list_contacts'];
			
//			$result[elements][]=[junction => 'transfer_member'];			
//			$result[elements][]=[junction => 'transfer_supplier'];
			
			return $result;
		}
		
		function available_menu()
		{
			$result[]=['f' => 'menu_set_1', 'p' => []];
			
			return $result;
		}		
	}
	
	class contact extends record
	{
		var $id;
		
		function __construct($credentials, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $credentials->primary(), $secure_db, $readonly_db, []);
		}
		
		function set_name($name)
		{
			$this->set('name', $name);
			$this->split_name();
		}
				
		function split_name()
		{
			$name=$this->get('name');
			$expression=array();
			
			$match=preg_match('/(?:(?P<salutation>(?:(?i:bp|nn|ibu|tn|ny|sdri{0,1}|dr|prof|ir|pt|cv|ud)(?:[\s\.\,])*)*)(?:[\s\.\,]+))*(?P<name>(?:\w+\V*)+)/', $name, $expression);
			
			if (($match) && ($expression))
			{
				switch (strtolower($expression['salutation']))
				{
					case 'pt': case 'cv': case 'ud':
						$this->set('company', $expression['salutation']);
						break;
					default: 
						$this->set('company', 'false');
				}
				
				$this->set('sortable_name', $expression['name']);
			}
			
			return $expression;
		}
		
		function sortable_name()
		{
			return $this->get('sortable_name');
		}
		
		function is_company()
		{
			return $this->get('company')!='false';
		}
		
		function toHTML()
		{
			return $this->get('name')."<br>".$this->tfn().'<br>'.$this->full_address().'<br>'.$this->phone();
		}
		
		function formal_consignee()
		{
			return $this->select('name').'<br>'.$this->full_address();
		}
				
		function full_address()
		{
			$result=$this->select('address');
			if ($this->select('city')!='na')
				$result.='<br>'.$this->select('city');
			if ($this->select('postcode')!='na')
				$result.=' - '.$this->select('postcode');
					
			return $result;
		}
		
		function phone()
		{
			$result='';
			if ($this->select('work'))
				$result.='Office :'.$this->select('work').'<br>';
			if ($this->select('mobile'))
				$result.='HP :'.$this->select('mobile').'<br>';
					
			return $result;
		}		
		
		function tfn()
		{
			$expression=array();
			
			if ($this->is_company())
			{
				preg_match('('.tfn_pattern.')', $this->select('tfn'), $expression);
				
				$tfn=$expression['d1'].'.'.$expression['d2'].'.'.$expression['d3'].'.'.
					 $expression['d4'].'-'.$expression['d5'].'.'.$expression['d6'];
				
				if ($this->get('country')=='ID')
					$result='NPWP: '.$tfn;
				else
					$result='TFN: '.$tfn;
			}
			else
			{			
				$id=$this->get('ic');
				
				if ($this->get('country')=='ID')
					$result='KTP: '.$id;
				else
					$result='Passport: '.$id;
			}
			
			return $result;
		}		
		
		function is_type($type)
		/*
		 * This will return current type, e.g. supplier, customer or both
		 */
		{
			return ($this->get('type')==$type);
		}
	}	
?>