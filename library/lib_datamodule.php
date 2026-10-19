<?php
	class lib_datamodule extends entity
	{
		var $readonly;
		
		var $secure_db;
		var $readonly_db;
		
		var $lists;
		
		var $addon_indices;
				
		function __construct($secure_db, $readonly_db)
		{
			$this->secure_db=$secure_db;
			$this->readonly_db=$readonly_db;
			
			$this->to_global('lib_datamodule', $this);
			
			$this->lists=[];
			$this->addon_indices=[];
		}
		
		function is_query_cached($table_name, $type, $query)
		{
			$settings=$this->get_global('settings');
			
			$t_hash=$this->quick_hash($table_name.'_'.$type);
			$query_hash=$this->quick_hash($query);
			
			return hash_equals($query_hash, $settings->get($t_hash) ?? '');
		}
		
		function cache_query($table_name, $type, $query)
		/*
		 * For dropping table, type should be 'create_table' as 
		 * table disposal is a part of table creation routine
		 */
		{
			$settings=$this->get_global('settings');
			
			$t_hash=$this->quick_hash($table_name.'_'.$type);
			$query_hash=$this->quick_hash($query);
			
			$settings->save($t_hash, $query_hash);
		}
		
		function add_index($table, $credential_name, $key)
		/*
		 * index is always varchar 128.  If its over 128, it will be truncated to 128 character
		 * 
		 * 
		 */
		{
			$name=$table.'_'.$credential_name;
			
			$index=$this->hash($key, $name);
			
			$tablename='T'.hash('haval128,3', $name);
			
			$original_query="ALTER TABLE ".$tablename." ADD (".$index." VARCHAR(128));". 
				   			"CREATE INDEX ".$index." ON ".$tablename." (".$index.")";
			
			if (!$this->is_query_cached($name, 'index:'.$index, $original_query))
			{
				/*
				 * This should be the same, but due to system already installed with cache, it was made different
				 */
				$queries="ALTER TABLE `".$tablename."` ADD (`".$index."` VARCHAR(128));".
						 "CREATE INDEX `".$index."` ON `".$tablename."` (`".$index."`)";
				
				foreach (explode(';', $queries) as $query)
				{
					$this->secure_db->query($query);
					
					if ($this->secure_db->is_table_archived($tablename))
						$this->secure_db->archive_query($query);
				}
				
				$this->cache_query($name, 'index:'.$index, $original_query);				
			}
			
			$this->addon_indices[$index]=$name;
		}
		
		function create_standard_datatable($table, $credential_name)
		{
			$name=$table.'_'.$credential_name;
			
			$tablename='T'.hash('haval128,3', $name);
			$query="CREATE TABLE IF NOT EXISTS ".$tablename."(uniqueid VARCHAR (128) NOT NULL, data MEDIUMTEXT, PRIMARY KEY (uniqueid))";
			
			if (!$this->is_query_cached($name, 'create_table', $query))
			{			
			//			var_dump($credential_name.' '.$table.' '.$tablename);
			
				$this->addData($credential_name, $tablename, $table);
				
				if (!$this->readonly)
				{				
					$this->secure_db->query($query);
					
					$this->secure_db->archive_query($query);
					
					$this->cache_query($name, 'create_table', $query);
				}
			}
			
			$this->get_global('settings')->addData($credential_name, $tablename, $table);
			
			return $tablename;
		}
		
		function create_plainname_datatable($table)
		{
			$tablename=$this->secure_db->SQLSafeString($table);
			
			$query="CREATE TABLE IF NOT EXISTS ".$tablename."(uniqueid VARCHAR (32) NOT NULL, data MEDIUMTEXT, PRIMARY KEY (uniqueid))";
			
			if (!$this->is_query_cached($tablename, 'create_table', $query))
					
				if (!$this->readonly)
				{				
					$this->secure_db->query($query);
					
					$this->secure_db->archive_query($query);
					
					$this->cache_query($tablename, 'create_table', $query);					
				}
			
			return $tablename;
		}
		
		function new_list($credentials, $list_name, $class_name, $element_class, $list_owner, $table_hr_name, ...$element_class_input)
		{
			$settings=$this->get_global('settings');
			//			if ($list_name=='employee_list') var_dump($list_name, $class_name, $element_class, $list_owner, $table_hr_name);
			$table_name=$this->select($list_owner, $table_hr_name) ?? $settings->select($list_owner, $table_hr_name);
			
			$list_class=self::decode_namespace_class($class_name, null);
			
			$this->lists[$list_name]=new $list_class($credentials, $this->secure_db, $this->readonly_db, $table_name);
			$this->lists[$list_name]->table_hr_name=$table_hr_name;
			$this->lists[$list_name]->element_class=$element_class;
			$this->lists[$list_name]->list_owner=$list_owner;
			
			$this->lists[$list_name]->set('name', $list_name);
			$this->lists[$list_name]->set('owner', $list_owner);
			$this->lists[$list_name]->dm=$this;
			
			if (!empty($element_class_input))
				$this->lists[$list_name]->add_element_class_input(...$element_class_input);
			
			$this->to_global($list_name, $this->lists[$list_name]);
			
			return $this->lists[$list_name];
		}		
		
		function drop_table(advanced_list $list, $list_owner, $table_hr_name)
		{
			$settings=$this->get_global('settings');
			
			$table_name=$this->select($list_owner, $table_hr_name) ?? $settings->select($list_owner, $table_hr_name);
			
			$query="DROP TABLE ".$table_name;
			
			$this->secure_db->query($query);
			
			$name=$table_hr_name.'_'.$list_owner;
			
			$this->cache_query($name, 'create_table', $query);
						
			foreach ($this->addon_indices as $index => $table_index)
				if ($name==$table_index)
					$this->cache_query($name, 'index:'.$index, '');
		}
	}
?>