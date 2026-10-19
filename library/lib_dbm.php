<?php
	define('dbm_journal_table', '_rt_journal');
	
	define('dbm_update_failed', 'Data gagal direkam!');
		
	class dbm extends s_lib implements iterator
	{						
		const dbm_record_legacy = 0;
		const dbm_record_1 = 1;
		
		const dbm_record_default = settings::dbm_record_version;
		
		var $link;
		var $query_result;
		var $no_of_queries;
		
		var $links;
		var $database_list;
		var $active_link;
		var $execution_list;
		
		var $use_cache;
		var $table_value_cache;
		
		var $journal_status;
		
		var $archive_db;
		 
		var $tables;
		
		var $query_no;
		
		var $dq_queries;
		var $dq_enabled;
		var $dq_max;	
		var $dq_cache;
		
		/*
		 * Deffered Queries is used to speed up massive updates or insert operations by
		 * bypassing the number of open / close SQL operation by combining queries into 
		 * several operations at once
		 * 
		 * it does not support journal ID!.
		 * 
		 * to use:
		 * 1. open_deferred_queries(max = 20)
		 * 2. do insert or update operation on as usual.  
		 * 3. engage_deferred_query
		 * 4. close_deferred_query
		 */
		
		function open_deferred_queries($max=20)
		{
			$this->dq_queries=[];
			$this->dq_cache=[];
			
			$this->dq_max=$max;
			$this->dq_enabled=true;
		}
		
		function close_deferred_queries(?credentials $credential = null)
		/*
		 * If crdential is supplied, then you can shuffle the input session for extra security 
		 */
		{
			$this->dq_queries=[];
			$this->dq_cache=[];
			
			$this->dq_max=0;
			$this->dq_enabled=false;
			
			if (isset($credential))
				$credential->shuffle_input_session();
		}
		
		function is_dq_enabled()
		{
			return $this->dq_enabled;
		}
		
		function same_query_type($old, $new)
		{
			$result=false;
			
			if ($old['type']==$new['type'])
				if ($old['table']==$new['table'])
				{					
					if (array_keys($old['values'])==array_keys($new['values']))
						$result=true;
				}
			
			return $result;
		}
		
		function combine_insert_query($queries)
		{
			return implode(';', $queries);
		}
		
		function combine_update_query($type, $queries)
		{
			$values_array=[];
			foreach ($queries as ['key_value' => $key_value, 'values' => $values])
			{
				$data=["'".$this->SQLSafeString($key_value)."'"];
				foreach ($values as $value)
					$data[]="'".$this->SQLSafeString($value)."'";					
				
				$values_array[]='('.implode(', ', $data).')';
			}
			
			$fields_array=[$this->SQLSafeString($type['key_field'])];			
			$update_array=[];
			
			foreach ($type['values'] as $field => $value)
			{
				$fields_array[]=$this->SQLSafeString($field);
				$update_array[$field]=$this->SQLSafeString($field)."=VALUES(".$this->SQLSafeString($this->SQLSafeString($field)).")";
			}
			
			$field_string=implode(', ', $fields_array);
			$update_string=implode(", ", $update_array);
			
			$values_string=implode(', ', $values_array);
			$query="INSERT INTO ".$this->SQLSafeString($type['table'])." (".$field_string.") VALUES ".$values_string." ON DUPLICATE KEY UPDATE $update_string";
			
			return $query;
		}
		
		function combine_query($type, $queries)
		{
			$combined_query='';
			
			switch ($type['type'])
			{
				case 'insert':
					$combined_query=$this->combine_insert_query($queries);
					break;
				case 'update':
					$combined_query=$this->combine_update_query($type, $queries);
					break;
			}
			
//			var_dump($combined_query); die;
			
			return $combined_query;
		}
		
		function engage_deferred_queries()
		/*
		 * You have to engage this manually after each using it.  Otherwise the query will not be used
		 * 
		 * Don't forget to close_deferred_queries after finishing 
		 */
		{
			$result=0;
			if (!empty($this->dq_queries))
			{
				$type=null;
				
				$queries=[];
				
				foreach ($this->dq_queries as $q_hash)
				{
					$q_cache=$this->dq_cache[$q_hash];
										
					if ($type==null)
						$type=$q_cache;
					
					if (!$this->same_query_type($type, $q_cache))
					/*
					 * Is query is changed, between insert and update then will write the query
					 */
					{			
						$combined_query=$this->combine_query($type, $queries);
						
						$result=$this->run_query($combined_query);
						
						$type=$q_cache;
						
						$queries=[];
					}
//					var_dump($type, 'x');
					switch ($q_cache['type'])
					{
						case 'insert': 
							$queries[]=$q_cache['query'];
							break;
						case 'update':
							$queries[]=$q_cache;
							break;
					}
				}
					
				
				if (($type) && (!empty($queries)))
				{
					$combined_query=$this->combine_query($type, $queries);
				
					$result=$this->run_query($combined_query);
				}
				
				$this->dq_queries=[];
				$this->dq_cache=[];
			}
			
			return $result;
		}
				
		function SQLSafeString($input)
		{
			$link=$this->link;
			
			$result='';
			if (is_string($input))
		    	$result=$link->real_escape_string($input);
			else
				$result=$link->real_escape_string((string) $input);
			
		    return $result;
		}
		
		function table_extract($table, $key)
		{
			$result='';
			
			$table=$this->SQLSafeString($table);			
			
			$query="SELECT * FROM ".$table;
			
			$query_id=$this->simple_query($query);
			while ($row=$this->next_result($query_id))
			{
				$result[$row[$key]]=$row;
			}
			
			return $result;
		}		
										
		function is_dml_query($query)
		/*
		 * This is used to check if a query is a data manipulation type
		 */
		{
			return ((stripos($query, 'insert')!==false) || (stripos($query, 'delete')!==false) 
					|| (stripos($query, 'update')!==false));
		}
		
		function journal_status($journal_id)
		{
			$result='not_exists';

			if ($this->journal_status[$journal_id])
			{
				$status=$this->journal_status;
			}
			else 
			{
				$uniqueid=$this->SQLSafeString($journal_id);
				
				$query="SELECT * FROM ".dbm_journal_table." WHERE uniqueid='$uniqueid'";
				$query_result=$this->simple_query($query);
				
				$journal_row=$this->next_result($query_result);
				if ($journal_row) $status=$journal_row['status'];
			}
			
			switch ($status)
			{
				case 0: $result='exists'; break;	
				default: $result='operation failed'; break;
			}
			
			return $result;
		}				
		
		function journal_cleanup()
		{
			
		}
		
		function query($query, $journal_id='')
		{
			if (($this->dq_enabled) && ($this->is_dml_query($query))) 
			/*
			 * If it is a DML query then it will buffer the queries for 
			 * execution / engagement later
			 */
			{				
				$this->dq_queries[]=$this->hash($query);
				
				if (count($this->dq_queries)==$this->dq_max)
					$result=$this->engage_deferred_queries();
			}
			else
			{
				/*
				 * For non DML operation to proceed, every other DML queries
				 * must be executed to ensure coherency of the database. 
				 */
				if (!empty($this->dq_queries))
				{
					$this->engage_deferred_queries();
				}
				
				$result=$this->run_query($query);
			}
			
			return $result;
		}
		
		function run_query($query, $journal_id='')
		{
			if (($journal_id) && ($this->is_dml_query($query)))
			{								
				$uniqueid=$this->SQLSafeString($journal_id);				
				$result=$this->simple_query($query);				
				if ($this->query_result[$result] instanceof mysqli_result)
				//if the query results in true 
				{
					$status='0';
				}
				else 
				{
					$status=$this->query_result[$result]['error_no'];
				}
				
				$query="INSERT INTO ".dbm_journal_table." (uniqueid, status) VALUES('$uniqueid', $status)";				
				$this->simple_query($query);
				$this->journal_status[$uniqueid]=$status;
			}
			else
			{	
				$result=$this->simple_query($query);
								
				if (!$this->query_result[$result] instanceof mysqli_result)
				{
					if (!$this->query_result[$result]['result']) die($this->query_result[$result]['error']);
				}
			}					
				
			return $result;
		}	
		
		function is_table_exists($table)
		{
			return isset($this->tables[$table]);
		}
		
		function is_table_archived($table)
		{
			if ($this->is_archive_enabled())
				$result=$this->archive_db->is_table_exists($table);
			else
				$result=false;
			
			return $result;
		}
		
		function enable_archive($server, $mysql_user, $mysql_pass, $dbname)
		{
			$this->archive_db=new dbm($server, $mysql_user, $mysql_pass, $dbname);
		}
		
		function is_archive_enabled()
		{
			return !is_null($this->archive_db);
		}
		
		function archive_query($query)
		{
			$result=null;
			
			if ($this->is_archive_enabled()) 
				$result=$this->archive_db->query($query);
			
			return $result;
		}
		
		function archive_next_result($query_id=0)
		{
			return $this->archive_db->next_result($query_id);
		}
		
		function archive(record $element)
		{					
			$credentials=$element->credentials;
							
			$element->addData('_archive', \date::today('timestamp'), 'date');
			$element->addData('_archive', $credentials->username, 'username');
			$element->addData('_archive', 'start', 'status');
						
			//Copy to archive DB 
			$element->flush();
			
			//Disposal from main DB
			$element->dispose();
			
			$element->addData('_archive', 'ok', 'status');
			$element->update();										
		}
		
		function unarchive(record $element)
		{				
			//Copy to main DB
			$element->flush();
				
			//Disposal from archive DB
			
			$element->dispose();
				
			$element->remove('_archive');
			$element->update();
//			var_dump($element->data);
		}		
		
		function simple_query($query)
		{
			$this->no_of_queries++;//start from 1;
			
			$start_time=microtime(true);

			try
			{
				if (!$query_result=$this->link->query($query))
					throw new Exception($this->link->error);
			}
			catch (Exception $e)
			{
				echo $e->getMessage();
				die;
			}
		
			if ($query_result)
			{
				if ($query_result instanceof mysqli_result)
					$this->query_result[$this->no_of_queries]=$query_result;
				else
					$this->query_result[$this->no_of_queries]=array('result' => $query_result);
				
				if (is_object($this->query_result[$this->no_of_queries]))					
					if (($this->link->affected_rows>0) && ($this->query_result[$this->no_of_queries]->num_rows==0))
						$this->execution_list[]=$query;					
			}
			else
			{
				$this->query_result[$this->no_of_queries]=
					array('result' => $query_result, 'error_no' => $this->link->errno, 'error' => $this->link->error);
			}
			
			$exec_time=microtime(true)-$start_time;
						
			if ($exec_time>0.5)
			{
				$this->next_link();
			}

			$this->query_result[0]=$this->query_result[$this->no_of_queries];
			
			return $this->no_of_queries;
		}
		
		function get_query($query_id=0)
		{
			return $this->query_result[$query_id];
		}
		
		function next_result($query_id=0)
		{
			if ($this->query_result[$query_id])
				return $this->query_result[$query_id]->fetch_array();
			else
				return false;
		}
		
		function num_rows($query_id=0)
		{
			return $this->query_result[$query_id]->num_rows;
		}
		
		function insert_id()
		{
			return $this->link->insert_id;
		}
				
		function alter_table($columns=array())
		{			
			if (count($columns)>0)
			{
				foreach ($columns as $column)
				{
					$query='';
					switch ($column['command'])
					{
						case 'add': 
							$query="ALTER TABLE ".$column['tableName']." ADD COLUMN ".$column['column']." ".$column['type'];
							break;
						case 'drop':
							$query="ALTER TABLE ".$column['tableName']." DROP COLUMN ".$column['column'];
							break;
						case 'change':
							$query="ALTER TABLE ".$column['tableName']." CHANGE COLUMN ".$column['column']." ".$column['column']." ".$column['type'];
							break;
						default: 
							$query='';
					}
					if ($query)
					{
						$active_query=$this->query_result[0];//save query
						//echo($query);						
						$this->query($query);
						$this->query_result[0]=$active_query;//return query so can continue query id
					}
				}
			}
		}		
	
		function repair_url($url)
		{
	    	return str_replace(' ', '%20', trim($url));
		}
				
		function __construct(#[\SensitiveParameter] $server, #[\SensitiveParameter] $mysql_user, 
							 #[\SensitiveParameter] $mysql_pass, #[\SensitiveParameter] $dbname, $use_cache=true)
		{
			$this->query_no=0;
			$this->query_result=array();
			$this->link = new mysqli($server, $mysql_user, $mysql_pass, $dbname);
			
			$this->links=array();
			$this->links[$dbname]=$this->link;
			$this->database_list=array();
			$this->database_list[]=$dbname;
			
			$this->active_link=$dbname;			
			$this->execution_list=array();
			
			$this->use_cache=$use_cache;
			$this->table_value_cache=array();
			
			$this->journal_status=array();
			
			$this->archive_db=null;
			
			$this->tables=[];
			
			$this->dq_enabled=false;
			
			$q=$this->query("SHOW TABLES");
			while ($row=$this->next_result($q))
				$this->tables[$row[0]]=$row[0];
		}
		
		function add_link(#[\SensitiveParameter] $server, #[\SensitiveParameter] $mysql_user, #[\SensitiveParameter] $mysql_pass, #[\SensitiveParameter] $dbname)
		{
			$this->links[$dbname]=array('server'=>$server, 'username'=>$mysql_user, 'password'=>$mysql_pass, 'database'=>$dbname);
			$this->database_list[]=$dbname;
		}
		
		function next_link()
		{
			$this->next();
			$link=current($this->links);
			next($this->database_list);
			
			$dbname='';
			if ($link)
			{
				$this->activate_link($link);
				$dbname=current($this->database_list);
			}
			else 
			{
				reset($this->links);
				reset($this->database_list);
				
				$link=current($this->links);
				$dbname=current($this->database_list);
			}
						
			$this->syncrhonise_database();
			$this->active_link=$dbname;
		}
		
		function syncrhonise_database()
		{
			foreach ($this->execution_list as $query)
			{
				foreach ($this->database_list as $dbname)
				{
					if ($dbname!=$this->active_link)
					{
						$link=$this->activate_link($this->links[$dbname]);						
						if (is_array($query))
						{
							$this->complex_query_execute($link, $query);
						}
						else
						{
							//echo($dbname.':'.$query.'<br>');
							$link->query($query);
						}
					}
				}
			}	
						
			$this->execution_list=[];
		}
		
		function activate_link($var)
		{
			if (($var) && (!is_object($var)))
			{
				$dbname=$var['database'];
				$var=new mysqli($var['server'], $var['username'], $var['password'], $var['database']);//activating connection
				$this->links[$dbname]=$var;
				$this->link=$var;
			}
			
			return $var;
		}
		
		public function rewind(): void
		{
			reset($this->links);			
		}
		
		public function current(): mixed
		{
			$var = current($this->links);
			return $var;
		}
		
		public function key(): mixed
		{
			$var = key($this->links);
			return $var;
		}
		
		public function next(): void
		{
			next($this->links);
						
//			return $var;
		}
		
		public function valid(): bool
		{
			$key = key($this->links);
			$var = ($key !== NULL && $key !== FALSE);
			return $var;
		}		
		
		function __destruct()
		{
			$this->syncrhonise_database();
		}
		
		function complex_query_execute($link, $execution)
		{
			$statement=$link->prepare($execution['query']);
			$statement->bind_param($execution['bind'], ...$execution['data']);
			
			foreach ($execution['longdata'] as $longdata)
			{
				$statement->send_long_data($longdata['param_no'], $longdata['data']);
			}
			
			$statement->execute();
			$statement->store_result();
			
			return $statement->get_result();
		}
		
		function complex_query($query, $bind, $longdata, ...$params)
		//longdata is an array contains long data		
		{			
			$this->no_of_queries++;
			
			$execution=array();
			
			$execution['query']=$query;
			$execution['bind']=$bind;
			$execution['longdata']=$longdata;
			$execution['data']=$params;
			
			$start_time=microtime(true);			
			$this->query_result[$this->no_of_queries]=$this->complex_query_execute($this->link, $execution);
			$exec_time=microtime(true)-$start_time;
			
			if ($exec_time>1)
			{
				$this->next_link();
			}
			
			if (($this->link->affected_rows>0) && ($this->query_result[$this->no_of_queries]->num_rows==0))
				$this->execution_list[]=$execution;
		}
		
		function input_sanitize($input)
		{
			$result=array();
			
			foreach ($input as $key => $value)
			{
				$result[$this->SQLSafeString($key)]=$this->SQLSafeString($value);				
			}
			
			return $result;
		}
		
		function IPC_read($id)
		/*
		 * Read value from $id, decrypt it and delete it.  Delay is in the SQL Server
		 * as proven by echo('X') and counted the number of X's 
		 */
		{
			$id=$this->SQLSafeString($id);
			$query="SELECT * FROM table_IPC WHERE ID='$id'";;
			$q=$this->query($query);
//			echo('X');
			if ($this->num_rows($q)>0)
			{
				$values=$this->next_result($q);			
				$result=json_decode($values['value'], true);
//				if ($values) var_dump($values); 
//				sleep(1);
				$query="DELETE FROM table_IPC WHERE ID='$id'";
				$this->query($query);
			}
			
			return $result;
			
//			return $input;
		}
		
		function IPC_write($id, $values)
		/*
		 * Write values as encrypted JSON
		 */
		{
			$jstring=json_encode($values);
			$query="INSERT INTO table_IPC (id, value) VALUES ('".$id."', '".$jstring."')";
			$this->query($query);
		}
		
		function make_update_query($table, $key_field, $key_value, $values)
		/*
		 * This method will make update query 
		 * Values is an array of key and values
		 * 
		 * Only support string values for now
		 */
		{
			$values_array=[];
			$values_string='';
			
			foreach ($values as $field => $value)
			{
				$values_array[$field]="`".$this->SQLSafeString($field)."`"."='".$this->SQLSafeString($value)."'";
			}
			
			$values_string=implode(", ", $values_array);
			
			$query="UPDATE ".$this->SQLSafeString($table)." SET ".$values_string." WHERE ".$this->SQLSafeString($key_field)."='".$this->SQLSafeString($key_value)."'";
			
			if ($this->is_dq_enabled())
			{
				$this->dq_cache[$this->hash($query)]= 
					['type' => 'update', 'table' => $table, 'key_field' => $key_field, 'key_value' => $key_value, 'values' => $values, 'query' => $query];				
			}
			
			return $query;
		}
		
		function make_flush_query($table, $key_field, $key_value, $values)
		/*
		 * This method will make flush query
		 * Values is an array of fields and values
		 * 
		 * Only support string values for now
		 */
		{
			$fields_array=[$this->SQLSafeString($key_field)];
			$values_array=["'".$this->SQLSafeString($key_value)."'"];
			
			foreach ($values as $field => $value)
			{
				$fields_array[]="`".$this->SQLSafeString($field)."`";
				$values_array[]="'".$this->SQLSafeString($value)."'";
			}
			
			$field_string=implode(', ', $fields_array);
			$values_string=implode(', ', $values_array);
			
			$query="INSERT IGNORE INTO ".$this->SQLSafeString($table)." (".$field_string.") VALUES (".$values_string.")";
//			var_dump($query);
			if ($this->is_dq_enabled())
			{
				$this->dq_cache[$this->hash($query)]=
					['type' => 'insert', 'table' => $table, 'key_field' => $key_field, 'key_value' => $key_value, 'values' => $values, 'query' => $query];									
			}
				
			return $query;
		}
		
		var $table_cache;
		
		function is_table_cached($table)
		{
			return isset($this->table_cache[$table]);
		}
		
		function get_table_cache($table)
		{
			return $this->table_cache[$table];
		}
		
		function table_cache($table, $primary, $columns, $conditions=[])
		/*
		 * this will cache the table contents here
		 * columns must be an array and contains primary key, otherwise this will not work
		 */
		{
			$result=[];
			
			foreach ($columns as $col)
				$cols[]=$col == '*' ? $col : '`'.$this->SQLSafeString($col).'`';
		
			$cols_string=implode(', ', $cols);
				
			$query="SELECT ".$cols_string." FROM ".$this->SQLSafeString($table);
			
			$query_id=$this->simple_query($query);
			while ($row=$this->next_result($query_id))
			{
				$result[$row[$primary]]=$row;
			}
			
			$this->table_cache[$table]=$result;
			
			return $result;
		}		
	}
		
	class table_reindexer extends junction
	/*
	 * This is specialised reindexer that will reindex database table.
	 * For now done manually on source code, but later can be made for user choice reindexing..
	 */
	{
		var $list;
		
		function form_title($form_title='')
		{
			return parent::form_title('Table Reindexer');
		}
		
		static function form_caption($form_title='')
		{
			return 'Table Reindexer';
		}
		
		function __construct($credentials, $input, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
			
			$this->list=$this->get_extension($input['extension'])->get_list($input['table'], $input['facility']);
			$this->list->db_fill();
			
			$this->form=$this->miniform_range($input, ['facility', 'extension', 'table'], [max => $this->list->count()-1, submit => 'save']);						
		}
		
		function toHTML()
		{
			$result='Data Count: '.$this->list->count();
			$result.=$this->form.'<br>';
			
			$result.=$this->open_html_table([], 'Sequence');
			if ($this->form->on_click('save'))
			{				
				foreach ($this->list->get_sequence($this->form->select('range')['begin'], $this->form->select('range')['end']) as $e)
				{					
					$e->update();
					
					/*
					 * Sequence number is different than data number, it will be 1 number bigger
					 */
					$result.=$this->add_html_table_row($e->seq_number());					
				}
			}
			$result.=$this->close_html_table();
			
			return $result;
		}
	}	
?>