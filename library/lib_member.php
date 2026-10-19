<?php
	include_once("lib_baseclass.php");
	
	//later move member object here
	
	class member_list extends item_list
	{
		var $group;
		var $group_key;
		var $contents_class;
		var $public_db;		
		
		function __construct($public_db, $readonly_db, $contents_class)
		{
			parent::__construct($readonly_db, ctableMember);
			$this->init_args=func_get_args();
			
			$this->group_key=array();
			$this->contents_class=$contents_class;
			$this->public_db=$public_db;
		}
		
		function populate_list($filter_type='', $filter_value='')
		{							
			$database=$this->database;
			
			$filter_type=$database->SQLSafeString($filter_type);
			$filter_value=$database->SQLSafeString($filter_value);
			$filter='';
			if ($filter_type)
				$filter="WHERE $filter_type='$filter_value'";
			
			$query="SELECT * FROM ".$this->table." $filter ORDER BY custName ASC";			
			$database->query($query);
			while ($row=$database->next_result())
			{
				//load primary data from readonly database
				$member=new $this->contents_class('', $this->readonly_db, $row);
				$member->load_profile($this->public_db);//load additional data from public database
				$this->addItem($member);
			}
				
			return $this->customer_list;
		}
		
		function group_by($categories = array())
		{
			$result=array();
			foreach ($categories as $category)			
			{
				foreach ($this->data as $data)
				{
					if ($data->field_exists($category))
					{						
						$key=$data->select($category);
						
						if (!is_array($result[$category]))
						{
							$result[$category]=array();
							$this->group_key[$category]=array();
						}
						
						if (!is_object($result[$category][$key]))
						{
							$classname=get_class($this);
							$result[$category][$key]=new $classname($this->public_db, $this->database, $this->contents_class);							
							$this->group_key[$category][]=$key;
						}
						
						$result[$category][$key]->addItem($data);
					}					
				}
				if (is_array($this->group_key[$category]))
					sort($this->group_key[$category], SORT_LOCALE_STRING);
			}
			
			$this->group=$result;
			
			return $this->group_key;
		}
		
		function get_group_by_category($category, $key)
		{
			return $this->group[$category][$key];
		}
		
		function get_key_by_category($category, $sort=true)
		{
			if ($sort)
			{
				return $this->group_key[$category];
			}
			else
			{
				return array_keys($this->group[$category]);
			}
		}
	}
?>