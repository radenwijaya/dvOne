<?php
	include_once("lib_baseclass.php");
	
	class advanced_list extends simple_list
	/*
	 * Additional database functionality to a simple_list
	 */
	{
		var $range;
			
		var $indices;
		var $addon_indices;
		
		var $element_class;
		var $element_class_input;
		var $element_id_field;
		 
		var $default_dbfill_condition;

		var $missing_data;
		
		var $pointer;		
		
		var $table_hr_name;
		var $list_owner;
		var $dm;
		
		/*
		 * Element variables is an array that contains pointer to variables needed by the elements
		 * the difference between this and additional variables is additional variables will be
		 * stored by element on the databases, but this will not.
		 * 
		 * This can be huge in size, such as another list.
		 */
		var $element_variables;
		
		var $cache_asArray;
		
		const DEFERRED_DIRECT = 0;
		const DEFERRED_LOAD = 1;
		
		function set_element_variables($name, $value)
		/*
		 * This are variable that will be used on elements
		 */
		{
			$this->element_variables[$name]=$value;
		}
		
		function __construct($credentials='', $secure_db='', $readonly_db='', $primarytable='', $range=array(), $deferred_load=advanced_list::DEFERRED_DIRECT)
		{
			$this->credentials=$credentials;
			$this->secure_db=$secure_db;
			$this->readonly_db=$readonly_db;
			$this->range=$range;
			$this->primaryTable=$primarytable;
			$this->element_variables=[];
				
			$this->elements=array();
			
			$this->indices=array();
			$this->addon_indices=[];
			
			$this->pointer=-1;
			$this->element_class=null;
			$this->element_class_input=[];
			
			$this->default_dbfill_condition=[];;
			
			$this->element_id_field='uniqueid';
			
			$this->missing_data=[];
			
			$this->set('deferred_loading', $deferred_load);
		}
		
		function enable_deferred_loading()
		{
			$this->set('deferred_loading', advanced_list::DEFERRED_LOAD);
		}
				
		function new($uniqueid='') : record
		/*
		 * Create new element, according to the specfication of this list
		 * with specifiable uniqueid and blank data  
		 * 
		 * If no element class is specified, e.g. use recorded_entity then
		 * it will ignore element class input!
		 *
		 * please use this when creating new element for this list to avoid
		 * any incompatibilities or errors
		 */
		{
			if ($this->element_class!='record')
			/*
			 * If not record, but a subclass of record
			 */
			{
				$element_class=self::decode_namespace_class($this->element_class, $this->namespace);
									
				$element=new $element_class($this->credentials, $this->secure_db, $this->readonly_db, ...$this->element_class_input);
				$element->primaryTable=$this->primaryTable;
				$element->set_element_variables($this->element_variables);
			}
			else
			{
				$element_class='record';
				
				/*
				 * Not adding uniqueid on top to avoid autoload, but data is stored if existed
				 */
				$element=new $element_class($this->credentials, $this->credentials->primary(), $this->secure_db, $this->readonly_db, [],
					$this->primaryTable, '', $this->element_id_field);				
			}
			
			if ($element instanceof record)
				$element->set_owner($this);
				
			if ((isset($uniqueid) && ($uniqueid!=='')))
			{				
				$element->uniqueid($uniqueid);				
				$element->addData($this->element_id_field, $uniqueid);				
			}
						
			$this->instill_element_indices($element);
						
			return $element;
		}
		
		function demand($uniqueid,  &$is_new=false)
		/*
		 * Thiw method will select Check if not exists, then create new and flush it!
		 *
		 * hence anything made form select_or_new must be updated and not flushed!
		 */
		{
			if (!$element=$this->db_select($uniqueid))
			{
				$element = $this->new($uniqueid);
				$element->flush();
				
				$is_new=true;
			}
			
			return $element;
		}
		
		function select_or_new($uniqueid, &$is_new=false)
		{
			return $this->demand($uniqueid, $is_new);
		}
		
		//change to ..$param and pass ..$param on the function name once PHP has
		//advances to 5.6
		function elements_function($function_name, ...$params)
		/*
		 * This will return execute elements function for each of the elements 
		 */
		{
			$result=[];
			foreach ($this->elements as $element)
			{
				if (method_exists($element, $function_name))
					$result[$element->getIndex()]=$element->$function_name(...$params);
			}
			
			return $result;
		}
	
		function asCSV($no_csv)
		/*
		 * Simpler than to CSV and intended to work for 
		 */
		{
			$no_csv['_hidden']=true;
			
			$line=[$this->column_name_asArray($no_csv)];
			
			foreach ($this->elements as $element)
			{
				if ($element)
					$showing=$this->showcheck($element);
					
				if ($showing)
				{
					$result=$element->toCSV($this->display, $this->format_display, $no_csv);

					$line[]=$result;
				}
			}
			
			return $line;
		}
		
		function toCSV($no_csv, $filename='data.csv')
		{
			$this->csv_output=true;
			
			$no_csv['_hidden']=true;
			
			header("Content-Type: text/csv; charset=utf-8");
			header("Content-Disposition: attachment; filename=$filename");
	
			// create a file pointer connected to the output stream
			$output = fopen('php://output', 'w');
	
			// output the column headings
			fputcsv($output, $this->column_name_asArray($no_csv));
	
			// fetch the data
			// loop over the rows, outputting them
			foreach ($this->elements as $element)
			{
				if ($element)
					$showing=$this->showcheck($element);
					
				if ($showing)
				{
					$result=$element->toCSV($this->display, $no_csv);
					
					if ((method_exists($element, 'concludeCSV')) && (!empty($this->shortform_conclusion)))
						$result=array_merge($result, $element->concludeCSV($this->shortform_conclusion, $this->display));
					
					if (is_array(current($result)))
					{					
						foreach ($result as $single_line)
							fputcsv($output, $single_line);
					}
					else 
					{
						fputcsv($output, $result);
					}
				}
			}
			
			fclose($output);
		}
		
		function instill_element_indices(record $element)
		/*
		 * This method is used to instill indexing in an element 
		 */
		{
			if (!empty($this->addon_indices))
			{				
				foreach ($this->addon_indices as $key => ['db_index' => $db_index,
					'pepper' => $pepper, 'nonce' => $nonce,
					'is_function' => $is_function, 'params' => $params])
				{
					if ($db_index)
						$element->db_add_index($key, $pepper, $nonce, $is_function, ...$params);
					else
						$element->add_index($key, $is_function, ...$params);							
				}
			}
		}
	
		function add($element)
		{			
			if (!$this->element_class) $this->element_class=get_class($element);
			$this->element_id_field=$element->id_field;
			$this->elements[$element->index()]=$element;
			
			if (method_exists($element, 'set_seq_number'))
				$element->set_seq_number(count($this->elements));
			
			if (($element instanceof grouping_container) && (!empty($this->showcheck_params)))
			{
				$element->show_condition=$this->showcheck_params;
			}
			
/*			foreach (array_keys($this->indices) as $key)
			{
				$value=$element->select($key);
				
				$this->indices[$key][$value]=$element->index();								
			}*/		
			
			if (!empty($this->addon_indices))
			{
				$this->instill_element_indices($element);
				$e_key=$element->getIndex();
				
				foreach ($this->addon_indices as $key => ['is_function' => $is_function, 'params' => $params])
				{
					if ($is_function)
						$s_key=$element->$key(...$params);
					else
						$s_key=$element->select($key);
								
					if ((is_int($s_key)) || (is_string($s_key)))
					{
						$this->indices[$key][$s_key][$e_key]=$e_key;
								
						$this->indices[$e_key][$key]=$s_key;
					}
				}
			}
		}
		
				
		function add_element_class_input(...$input)
		/*
		 * Used to automatically adjust element class additional input while using
		 * db lookup or db_fill
		 */		
		{
			$this->element_class_input=$input;
		}
		
		function add_default_condition($new_condition)
		/*
		 * Add function => parameter db_fill condition
		 */
		{
			$this->default_dbfill_condition=$new_condition;
		}
		
		function remove_default_condition(...$conditions)
		/*
		 * Enters the function to remove
		 */
		{
			foreach ($conditions as $condition)
				foreach ($this->default_dbfill_condition as $key => [f => $f])
					if ($f==$condition)
						unset($this->default_dbfill_condition[$key]);			
		}
		
		function db_export_fill($conditions=[], $addon_indices=[], $from_archive=false, $limit=PHP_INT_MAX)
		/*
		 * This is db_fill, and exports to another list, but that new list will not have the ability
		 * as the original list
		 * 
		 * This funciton is not well made, and must be remade by creating a new class, and then 
		 * calling using db_fill instead.
		 * 
		 * This is differernt than recast.  Recast only worked when the database has been filled and then 
		 * recasting it to a new class.
		 * 
		 */
		{
			$result = new smart_list();
			
			$settings=$this->get_global('settings');
			
			if ($this->default_dbfill_condition)
				$conditions=array_merge($this->default_dbfill_condition, $conditions);
				
			$table_name=$settings->select($this->list_owner, $this->table_hr_name);
				
			$element_class=$this->element_class;
				
			if (!$from_archive)
				$database=$this->secure_db;
			else
				$database=$this->secure_db->archive_db;
						
			$qr=$database->query("SELECT * FROM ".$table_name);
						
			$count=0;
			while (($row=$database->next_result($qr)) && ($count<$limit))
			{
				$element=$this->new();
									
							/*				$element=new $element_class($this->credentials, $database, $this->readonly_db,
							 ...$this->element_class_input);*/
							
							/*
							 * If the function load returns something, then use the return value from load
							 * it might be different class
							 */
				$detailed_element=$element->load($row[$element->id_field], $row);
				$element_class=get_class($element);
				if (($detailed_element) && ($detailed_element instanceof $element_class))
					$element=$detailed_element;
								
				$store_list=true;
				foreach ($conditions as [f => $function, p => $params])
				{
					if (!is_array($params)) $params=[$params];
									
					$r=$element->{$function}(...$params);
									
					if (($r===false) || ($r===null))
					{
						$store_list=false;
						break;
					}
				}
								
				if ($store_list)
				{
					$count++;
					$element->set_seq_number($count);
					$this->add($element);
					$result->add($element);
				}
						
			}
						
			foreach ($addon_indices as $addon_index)
			{
				$result->add_index($addon_index);
				$this->add_index($addon_index);
			}
										
			return $result;
		}
		
		function db_index_fill($db_indices=[], $conditions=[], $from_archive=false, $limit=PHP_INT_MAX)
		/*
		 * This is a general list function that will load from database using database column
		 * 
		 * fill the column name condition on db_indices => values_array
		 * 
		 * for example array ['condition1' => 'value1', 'condition2' => 'value2']
		 * 
		 * This is useful to add nonce on the values to add more entrophy on to it
		 * 
		 * IMPORTANT: $db_indices treated index as AND, for OR index, you can call this function twice.
		 */
		{
			$settings=$this->get_global('settings');
			
			if ($this->default_dbfill_condition)
				$conditions=array_merge($this->default_dbfill_condition, $conditions);
				
			$table_name=$settings->select($this->list_owner, $this->table_hr_name);
				
			if (!$from_archive)
				$database=$this->secure_db;
			else
				$database=$this->secure_db->archive_db;
						
			$where=[];
			foreach ($db_indices as $condition => $values)
			{
				/*
				 * Make Values Array
				 */
				$values=is_array($values) ? $values : [$values];
				
				$values_array=[];
				foreach ($values as $value)
				{
					foreach ($this->hash_nonce($value, $this->addon_indices[$condition]['pepper'], $this->addon_indices[$condition]['nonce']) as $v)
						$values_array[]="'".$v."'";
				}
				
				$where[]='`'.$this->hash($condition, $this->addon_indices[$condition]['pepper']).'`'." IN (".implode(', ', $values_array).")";				
			}
			
			$query="SELECT * FROM ".$table_name;
			
			if (!empty($where))
			{
				$where_string='WHERE '.implode(' AND ', $where);
				$query.=' '.$where_string;
			}			
			
			$qr=$database->query($query);
						
			$elements=$this->db_element_load($database, $qr, $conditions, $limit);
			
			return $elements;
		}
				
		function db_fill($conditions=[], $addon_indices=[], $from_archive=false, $limit=PHP_INT_MAX, &$result=[]): advanced_list
		/*
		 * General list function to fill the database with conditions and indices, and returns this database again
		 * 
		 * $result is the resulting element
		 */
		{			
			$settings=$this->get_global('settings');
			
			if ($this->default_dbfill_condition)
				$conditions=array_merge($this->default_dbfill_condition, $conditions);
							
			$table_name=$settings->select($this->list_owner, $this->table_hr_name);
												
			if (!$from_archive)
				$database=$this->secure_db;
			else
				$database=$this->secure_db->archive_db;
			
			$qr=$database->query("SELECT * FROM ".$table_name);
			
			$result=$this->db_element_load($database, $qr, $conditions, $limit);
			
			foreach ($addon_indices as $addon_index)
				$this->add_index($addon_index);
			
			return $this;
		}
		
		function db_element_load(dbm $database, int $qr, $conditions=[], $limit=PHP_INT_MAX)
		{			
			$element_class=$this->element_class;
			
			$result=[];
			
			$count=0;
			while (($row=$database->next_result($qr)) && ($count<$limit))
			{
				$element=$this->new();
									
/*				$element=new $element_class($this->credentials, $database, $this->readonly_db,
					...$this->element_class_input);*/
				
				$store_list=true;
				if ((!empty($conditions)) || ($this->get("deferred_loading")==advanced_list::DEFERRED_DIRECT))
				{
					/*
					 * If the function load returns something, the return value from load
					 * it might be different class so it must be checked
					 */
					$detailed_element=$element->load(isset($row[$element->id_field]) ? $row[$element->id_field] : null, $row);
					$element_class=get_class($element);
					if (($detailed_element) && ($detailed_element instanceof $element_class))
						$element=$detailed_element;					
						
					foreach ($conditions as [f => $function, p => $params])
					{
						if (!is_array($params)) $params=[$params];
						
						$r=$element->{$function}(...$params);
						
						if (($r===false) || ($r===null))
						{						
							$store_list=false;
							break;
						}
					}
				}
				else
				{
					$element->_deferred_load($row[$element->id_field], $row);
					/*
					 * Everything from deferred load will be checked
					 */
				}
				
				if ($store_list)
				{
					$count++;
					$element->set_seq_number($count);
					$this->add($element);
					$result[]=$element;
				}
			}
							
			return $result;
		}
		
		function resequence_number()
		{			
			$count=0;
			foreach ($this->elements as $element)
			{
				$count++;
				$element->set_seq_number($count);				
			}
		}
		
		function get_sequence($begin, $end)
		/*
		 * This method will returrn element 0 to end
		 */ 
		{
			$keys=array_keys($this->elements);
			
			for ($i=$begin; $i<=$end; $i++)
			{
				yield $this->elements[$keys[$i]];
			}
		}
		
		function db_field_lookup($field, ...$values)
		/*
		 * This function will lookup values in a field and returns an array of uniqueid and elements
		 *
		 * db_lookup always load regrdless of its deffered status because lookukp
		 * will be assumed the data will be used immediately
		 * 
		 * values is array of value, no nonce no hashing value, and this method will grab the value needed
		 */
		{
			$result=[];
			
			if (($field!=='primary') && (!isset($this->addon_indices[$field])))
				throw throw new Exception("ERROR: index ".$field." does not exist");
			
			if (count($values)>=1)
			{
				$table_name=$this->primaryTable;
				
				$normal_uid=[];
				
				$db_uniqueids=[];
				$query_field=($field=='primary') ? $this->secure_db->SQLSafeString($this->element_id_field) : 
					$this->hash($field, $this->addon_indices[$field]['pepper']); 
					
				foreach ($values as $identity)
				{
					if (!isset($this->missing_data[$field][$identity]))
						// Logic conflict, missing data is only working with unique identity alias primary
						if (!$element=$this->select($identity, ($field==$this->element_id_field) ? 'primary' : $field))
							/*
							 * Only reload when its not already existed
							 */
						{
							if ($field=='primary')
								$queries_id=[$this->secure_db->SQLSafeString($identity)];
							else
								$queries_id=$this->hash_nonce($identity, $this->addon_indices[$field]['pepper'], 
									$this->addon_indices[$field]['nonce']);
							
							foreach ($queries_id as $query_id)
								$db_uniqueids[]="'".$query_id."'";
//							$query_id=($field==$this->element_id_field) ? $this->secure_db->SQLSafeString($identity) : $this->hash($identity);
						}
						else
						{
							if ($field=='primary')
								$result[$element->get($this->element_id_field)]=$element;
							else
								foreach ($element as $e)									
									$result[$e->get($this->element_id_field)]=$e;								
						}
				}
				
				if (!empty($db_uniqueids))
				{
					$query='SELECT * FROM '.$table_name.' WHERE '.$query_field.' IN ('.implode(', ', $db_uniqueids).')';
					$qr=$this->secure_db->query($query);
					
					while ($row=$this->secure_db->next_result($qr))
					{
						$element=$this->new();
						//					$element=new $this->element_class($this->credentials, $this->secure_db, $this->readonly_db,
						//						...$this->element_class_input);
						$element->load($row[$this->element_id_field], $row);
						
						$this->add($element);
						
						$normal_uid[$element->get($this->element_id_field)]=1;
						
						$result[$element->get($this->element_id_field)]=$element;
					}
					
					if ($this->secure_db->is_table_archived($table_name))
					{
						$db_uniqueids=[];
						foreach ($values as $identity)
							/*
							 * Logic conflict, this will not be exhaustive!
							 */
							if ((!isset($normal_uid[$identity])) && (!$this->select($identity)) && (!isset($this->missing_data[$field][$identity])))
							{
								if ($field=='primary')
									$queries_id=[$this->secure_db->SQLSafeString($identity)];
								else 
									$queries_id=$this->hash_nonce($identity, $this->addon_indices[$field]['pepper'],
										$this->addon_indices[$field]['nonce']);
								
								foreach ($queries_id as $query_id)
									$db_uniqueids[]="'".$query_id."'";
										
								/*
								 * Presume Missing
								 */
								$this->missing_data[$field][$identity]=true;
							}
						
						if (!empty($db_uniqueids))
						/*
						 * Loading from archive
						 */
						{
							$query='SELECT * FROM '.$table_name.' WHERE '.$query_field.' IN ('.implode(', ', $db_uniqueids).')';
							$qr=$this->secure_db->archive_query($query);
							
							//							var_dump($normal_uid, $db_uniqueids);echo("<BR><BR>");
							while ($row=$this->secure_db->archive_next_result($qr))
							{
								$element=$this->new();
								$element->load($row['uniqueid'], $row);
								$this->add($element);
									
								$this->missing_data[$field][$row['uniqueid']]=false;
								
								$result[$element->get($this->element_id_field)]=$element;
							}
						}
					}
				}
			}

			return $result;			
		}
		
		function db_lookup(...$uniqueids)
		/*
		 * This function will lookup uniqueids and returns its values
		 * 
		 * db_lookup always load regardless of its deffered status because lookukp
		 * will be assumed the data will be used immediately 
		 */
		{
			return $this->db_field_lookup('primary', ...$uniqueids);
		}		
			
		function db_select($indices, $key='primary')
		/*
		 * Index can now be array (horray)
		 * 
		 * And the result can also be array, especially when key is not primary
		 */
		{
			if (!is_array($indices))
			{
				if ($key=='primary')
				/*
				 * Primary is always unique, so it will return singular result, always when there
				 * just 1 indices
				 */
				{
					if (!$result=$this->select($indices, $key))					
						if (($lookup_array=$this->db_lookup($indices)) && (!empty($lookup_array)))
							$result=array_pop($lookup_array);
						
					$results=$result;
				}				
				else					
				{
					$results=$this->db_field_lookup($key, $indices);					
				}
			}
			else
			{
				$results=[];
				$lookups=[];
				foreach ($indices as $index)
				{				
					if (!$result=$this->select($index, $key))
					{
						$lookups[]=$index;				
					}
					else 
					{
						$results[]=$result;
					}
				}
				
				if ($key=='primary')
				{				
					foreach ($this->db_lookup(...$lookups) as $element)
						$results[]=$element;
				}			
				else
				{
					foreach ($this->db_field_lookup($key, ...$lookups) as $element)
						$results[]=$element;											
				}
			}
			
			return $results;
		}
				
		function db_lock(...$index)
		/*
		 * This will change any update that occured on the particular object
		 */
		{
			if (count($index)>0)
				foreach ($index as $element_id)
					$this->select($element_id)->db_lock();
			else
				foreach ($this->elements as $element)
					$element->db_lock();
		}		
		
		function db_unlock(...$index)
		/*
		 * This will change any update that occured on the particular object
		 */
		{
			if (count($index)>0)
				foreach ($index as $element_id)
					$this->select($element_id)->db_unlock();
			else 
				foreach ($this->elements as $element)
					$element->db_unlock();
		}
		
		function select($index, $key='primary')
		/*
		 * When the key is primary, it always returrns singular
		 * 
		 * However when its not primary, it will return multiple  
		 */
		{
			if ($key=='primary') 
			{
				$result=$this->elements[$index] ?? null;
				if (($result instanceof \record) && (!is_null($result)) && ($result->_is_deferred()))
					$result->_activate();
				
				$results=$result;
			}
			else
			{				
				foreach ($this->indices[$key][$index] ?? [] as $uniqueid)
					$results[]=$this->select($uniqueid, 'primary');
			}
							
			return $results;
		}
		
		function remove($index)
		{			
			foreach (array_keys($this->indices) as $key)
			{
				/*
				 * First: unsetting index on that element that contains reference to the removed element
				 */
				unset($this->indices[$key][$this->indices[$index][$key]][$index]);
				
				/*
				 * Second: unsetting index of the element contents
				 */
				unset($this->indices[$index][$key]);
			}
			
			$element=$this->elements[$index];
			
			/*
			 * Third: unset the element for removal
			 */
			unset($this->elements[$index]);			
			
			return $element;
		}
		
		function asArray($criteria=[])
		{
			$elements=[];
			
			foreach ($this->elements as $element)
			{
				$excepted=false;
				
				foreach ($criteria as $function => $value)
				{
					if (!is_array($value))
						$value=[$value];
						
					if (!$element->$function(...$value))
					{
						$excepted=true;
						break;
					}
				}

				if (!$excepted)
					$elements[$element->uniqueid()]=$element;
			}
			
			return $elements;
		}
		
		function add_index($key, $is_function=false, ...$params)
		/*
		 * Assumed to be unique, if not unique then will override
		 */
		{
			/*
			 * Initialise value container
			 */
			$this->indices[$key]=[];
			
			if ($is_function)
				$this->addon_indices[$key]=['db_index' => false, 'is_function' => $is_function, 'params' => $params, 
					'pepper' => '', 'nonce' => 0];
			else
				$this->addon_indices[$key]=['db_index' => false, 'is_function' => $is_function, 'params' => [], 
					'pepper' => '', 'nonce' => 0];
					
			foreach ($this->elements as $e_key => $element)
			{
				if ($is_function)
					$s_key=$element->$key(...$params);
				else
					$s_key=$element->select($key);
								
				/*
				 * This is not unique, hence record more
				 */
				$this->indices[$key][$s_key][$e_key]=$e_key;
				
				/*
				 * This is guaranteed to be unique
				 */
				$this->indices[$e_key][$key]=$s_key;
				
				$element->add_index($key, $is_function, ...$params);
			}
		}						
		
		function add_db_index($key, $nonce=0, $is_function=false, ...$params)
		/*
		 * Add Index, but for database too.  
		 * 
		 * Key is assumed to be unique, in case not, it will crash override
		 * 
		 * Pepper is always table_hr_name appended with list_owner
		 */
		{
			$this->add_index($key, $is_function, ...$params);
			
			$this->addon_indices[$key]['db_index']=true;
			$this->addon_indices[$key]['pepper']=$this->table_hr_name.'_'.$this->list_owner;
			$this->addon_indices[$key]['nonce']=$nonce;
			
			foreach ($this->elements as $element)
				$element->enable_db_index($key, $this->table_hr_name.'_'.$this->list_owner, $nonce);
			
			$this->dm->add_index($this->table_hr_name, $this->list_owner, $key);
		}
		
		function asJSON()
		{
			$result_array=array();
			foreach ($this->elements as $element)
			{
				$result_array[$element->index()]=$element->asArray();				
			}
			
			return json_encode($result_array);
//			return json_encode($this->asArray());
		}

		function sort($sort_function, ...$sort_base)
		/*
		 * normal sort
		 */
		{
			$this->gsort($sort_function, 'asort', ...$sort_base);
		}
		
		function rsort($sort_function, ...$sort_base)
		/*
		 * Reverse sort
		 */
		{
			$this->gsort($sort_function, 'arsort', ...$sort_base);
		}
		
		var $sort_tree;
		function build_tree(&$sort_tree, $tree_keys, $index)
		{
			$key=array_pop($tree_keys);
			if (empty($tree_keys))
			{
				$sort_tree[$key][]=$index;
			}
			else
			{
				$this->build_tree($sort_tree[$key], $tree_keys, $index);
			}
		}
		
		function sort_tree(&$sort_tree, $sort_detail)
		{
			$result=[];
			if (!empty($sort_detail))
			{
				$algo=array_pop($sort_detail)['a'];
				if (is_array($sort_tree))
				{
					switch ($algo)
					{
						default: case 'ksort':
							$algo = 'ksort';
							break;
						case 'krsort':
							$algo = 'krsort';
							break;
					}
					
					$algo($sort_tree, SORT_NATURAL);
					
					foreach (array_keys($sort_tree) as $key)
						$result=[...$result, ...$this->sort_tree($sort_tree[$key], $sort_detail)];						
				}
			}
			else 
			{
				$result=$sort_tree;			
			}
			
			return $result;
		}
		
		function xsort($orders, $index='primary')
		/*
		 * This function will sort the elements according to the order (array) 
		 * and to the to the select function of the index 		  
		 */
		{
			if ($index!='primary')
				$this->add_index($index);
			
			$result=[];
			foreach ($orders as $order)
			{
				$e=$this->select($order, $index);
				$result[$e->select('uniqueid')]=$e;
			}
			
			$this->elements=$result;
			
			return $result;
		}
		
		function msort(...$sort_functions)
		/*
		 * Multi sort, using multiple sort_functions as its base / column
		 * Algo: ksort and krsort only, if not then ksort
		 * function: can be anything
		 * params: can be anything
		 */
		{
			$this->sort_tree=[];
			foreach ($this->elements as $index => $element)
			{
				$tree_keys=[];
				foreach ($sort_functions as [f => $function, p => $params])
				{
					if (!is_array($params))
						$params=$params ? [$params] : [];
					if ($this->showcheck($element))
					{						
						$tk=$element->$function(...$params);
						$tree_keys[]="$tk";
					}
				}				

				if (!empty($tree_keys))
				{
					$tree_keys=array_reverse($tree_keys);
					$this->build_tree($this->sort_tree, $tree_keys, $index);
				}
//				var_dump($this->sort_tree);
			}
			
			$sort_detail=array_reverse($sort_functions);
			$e_key=$this->sort_tree($this->sort_tree, $sort_detail);
			
			$result=[];
			foreach ($e_key as $index)
				$result[$index]=$this->elements[$index];

			$this->elements=$result;
				
			return $result;				
		}
		
		function gsort($sort_function, $sort_mode, ...$sort_base)
		/*
		 * General sort algorithm, 
		 * input
		 * sort_function : usually put select, but can be function
		 * sort_mode : native php sort function such as arsort, rsort
		 * sort_base : parameter for sort function 
		 */
		{
			$element_mode=null;
			$element_index=array();
			
			foreach ($this->elements as $index => $element)
			{
				if ($this->showcheck($element))
				{
					$element_index[$index]=$element->$sort_function(...$sort_base);
					 
					if ($element_mode!=gettype($element_index[$index]))
						if (!$element_mode)
							$element_mode=gettype($element_index[$index]);
						else 
							$element_mode='mixed';
				}
			}
			
			switch ($element_mode)
			{
				case 'integer': case 'double': case 'float':
					$sort_flags=SORT_NUMERIC;
					break;
				case 'mixed': case 'string': default:
					$sort_flags=SORT_NATURAL;
					break;
			}			
			
			$sort_mode($element_index, $sort_flags);
			
			$result=array();
			$e_key=array_keys($element_index);
			$i=1;
			foreach ($e_key as $index)
			{
				$result[$index]=$this->elements[$index];
				
				if ($result[$index] instanceof numerator)
					$result[$index]->set_seq_number($i);
				
				$i++;
			}
			
			$this->elements=$result;
			
			return $result;
		}
		
/*		function sort($sort_function, ...$sort_base)
		{
			$element_index=array();
			foreach ($this->elements as $index => $element)
			{
				$element_index[$index]=$element->$sort_function(...$sort_base);
			}
			asort($element_index);
			
			$result=array();
			$e_key=array_keys($element_index);
			foreach ($e_key as $index)				
			{
				$result[$index]=$this->elements[$index];
			}
			
			$this->elements=$result;
			
			return $result;
		}*/
		
		function fill_by_list($list, $identifier, ...$criteria)
		/*
		 * This method will fill this list from another list as its reference
		 */
		{
			$ids=[];
			
			foreach ($list as $element)
			{				
				$pass=true;
				foreach ($criteria as [f => $function, p => $params])
					if (!$element->{$function}(...(is_array($params) ? $params : [$params])))
					{
						$pass=false;
						break;
					}

				if (($pass) && ($element->select($identifier)))
				{					
					$ids[]=$element->select($identifier);
				}
			}
			
			if (!empty($ids))
				$this->db_select($ids);			
		}
		
		function assosiative_list_lookup($list, $variable)
		/*
		 * Prefilling list with variable extracted from this list, 
		 * so limiting db lookups
		 */
		{
			$list_elements=$this->extract($variable);
			
			$list->db_lookup(...$list_elements);
			
			return $list;
		}
				
		function initialise($list_name, $element_class, $list_owner, $table_hr_name, ...$element_class_input)
		/*
		 * Untested
		 */
		{					
			$this->table_hr_name=$table_hr_name;
			$this->element_class=$element_class;
			$this->list_owner=$list_owner;
			
			$this->add_element_class_input(...$element_class_input);
			
			$this->to_global($list_name, $this);
			
			return $this;
		}
				
		function purge($conditions=[])
		/*
		 * This will remove all elements on the database.  
		 * 
		 * Not using truncate database because of elements conditions must be first sastisfied when calling
		 * the purge  
		 */
		{
			foreach ($this->elements as $element)
			{
				$delete=true;
				foreach ($conditions as [f => $function, p => $params])
				{
					if (!is_array($params)) $params=[$params];
					
					$r=$element->{$function}(...$params);
					
					if (($r===false) || ($r===null))
					{
						$delete=false;
						break;
					}
				}
				
				if ($delete)
				{
					$element->delete();
				}
			}
		}
		
		function check_duplicate($key, ...$values)
		/*
		 * Used to check if there is duplicates in the values
		 */
		{
			$result=false;

			$lookups=$this->db_field_lookup($key, ...$values);
			
			if (!empty($lookups))
				$result=s_lib::first($lookups);						
					
			return $result;
		}
		
		function clear_element_data(...$caches)
		{
			foreach ($this->elements as $element)
				foreach ($caches as $cache)
					$element->remove($cache);
		}
		
		function group_by($function='select', ...$params) : Array
		{
			$result=[];
			
			$class_name=get_class($this);
			
			foreach ($this->elements as $element)
			{
				$value=$element->$function(...$params);
				
				$result[$value]=isset($result[$value]) ? $result[$value] : new $class_name();
				
				$result[$value]->add($element);
			}
			
			return $result;
		}
		
		function get_lowest($variable, $count)
		{
			$array=[];
			
			foreach ($this->elements as $e)
			{
				if ($v=$e->select($variable))
					$array[$e->select($this->element_id_field)]=$v;
			}
			
			asort($array);
			
			return array_slice($array, 0, $count, true);
		}
		
		function extract($variable='length', $criteria=array())
		/*
		 * Get an information with certain name and criteria of all elements
		 */
		{
			$result=array();
			if (!is_array($variable)) $variable=[$variable];
			
			foreach ($this->elements as $element)
			{
				$is_eligible=true;
				foreach ($criteria as $function => $value)
				{
					if (!is_array($value)) $value=[$value];
					if (!$element->$function(...$value))
					{
						$is_eligible=false;
						break;
					}
				}
				if ($is_eligible)
				{
					$result[$element->getIndex()]=$element->select(...$variable);
				}
			}
			
			return $result;
		}
		
		function get_sub_list($criteria=[], $limit=PHP_INT_MAX)
		/*
		 * Old style using [key => value] as function and and params,
		 * must convert to new style before executing, so recast can
		 * be worked
		 */
		{
			$c=[];
			foreach ($criteria as $key => $params)
				$c[]=[f => $key, p => $params];
				
				return $this->recast(get_class($this), $c, $limit);
		}
		
		function sub_list($criteria=[], $limit=PHP_INT_MAX)
		/*
		 * New style using [f =>, p =>]
		 */
		{
			return $this->recast(get_class($this), $criteria, $limit);
		}
		
		function recast($new_class=null, $criteria=[], $limit=PHP_INT_MAX)
		/*
		 * Recast this list into a new class of list
		 */
		{
			$new_class=$new_class ?: get_class($this);
			
			$result=new $new_class($this->credentials, $this->secure_db, $this->readonly_db, $this->primaryTable);
			
			$result->list_owner=$this->list_owner;
			$result->table_hr_name=$this->table_hr_name;
			$result->element_class_input=$this->element_class_input;
			$result->element_id_field=$this->element_id_field;
			$result->element_class=$this->element_class;
			$result->namespace=$this->namespace;
			
			$result->addon_indices=$this->addon_indices;
			
			$count=0;
			foreach ($this->elements as $element)
			{
				$is_eligible=true;
				foreach ($criteria as [f => $function, p => $value])
				{
					if (!is_array($value)) $value=array($value);
					
					if (!$element->$function(...$value))
					{
						$is_eligible=false;
						break;
					}
				}
				if ($is_eligible)
				{
					$result->add($element);
					$count++;
				}
				
				if ($count>=$limit) break;
			}
			
			return $result;
		}		
	}
	
	class smart_list extends advanced_list
	/*
	 * This is additional html capabilities for advanced_list to output html capable
	 */
	{
		use lib_html;
		use list_form_trait;
		
		var $display; //the list of variable that is about to be displayed
		var $display_buffercheck; //a buffer check to check if two of the same variable are being counted
		
		var $output_capture;
				
		var $closing_display; //closing footer for the display
		var $format_display;
		var $processing_display;
		
		var $shortform_conclusion;
		
		var $to_conclude; //list of conclusion list and the required result
		
		var $element_shortform;	//basically an abstraction to inlineHTML
		var $element_shortform_conclusion;
		
		var $value_cache;
		var $conclusion_cache;
		
		var $value_type;
		var $display_range;
		
		var $total_array;
		var $footer_cache;
		var $showcheck_function;
		var $showcheck_params;
		
		function __construct($credentials='', $secure_db='', $readonly_db='', $primarytable='', $range=array())
		{
			parent::__construct($credentials, $secure_db, $readonly_db, $primarytable, $range);
			
			$this->element_shortform='toShortform';
			$this->element_shortform_conclusion='inlineHTMLConclusion';
			
			$this->display=array();
			$this->display_buffercheck=array();

			$this->output_capture=[];			
			
			$this->closing_display=array();
			
			$this->shortform_conclusion=array();
			
			$this->total_array=array();
			
			$this->format_display=array();
			$this->processing_display=array();
			
			$this->value_cache=array();
			$this->footer_cache=array();
			
			$this->display_range=[];
			
			$this->add_showcheck();
			$this->add_showing_condition();
		}
	
		function column_name_asArray($no_csv)
		{
			$result=array();
			foreach ($this->display as $column)
			{
				if (!$no_csv[$column['display']])
					$result[]=$column['display'];
			}
			return $result;
		}
	
		function addDisplay($mode, $display, $value, ...$params)
		//only if the item does not existed in display_buffercheck then it will be added 
		//to the list of display
		{			
			$uniqueid='';
			
			if (!array_key_exists($mode.'_'.$display, $this->display_buffercheck))
			{			
				$uniqueid=hash('adler32', $mode.'_'.$display);
				$this->display_buffercheck[$mode.'_'.$display]=$uniqueid;
				
				$this->display[$uniqueid]=['mode' => $mode, 'display' => $display, 'value' => $value, 
										   'params' => $params, 'capture_output' => false];
			}
			else  
			{								
				$uniqueid=$this->display_buffercheck[$mode.'_'.$display];
				if ($display==$this->display[$uniqueid]['display'])
				{
					$this->display[$uniqueid]['value']=$value;				
					$this->display[$uniqueid]['params']=$params;
				}
			}
			
			$this->closing_display[$uniqueid]=array();
		}
		
		function add_output_capture($mode, $display)
		/*
		 * This function is used to capture output format from add_display
		 * 
		 * This will capture raw format only.
		 */
		{
			$uniqueid=$this->display_buffercheck[$mode.'_'.$display];
			if ($display==$this->display[$uniqueid]['display'])
			{
				$this->display[$uniqueid]['output_capture']=true;
			}
		}
		
		function get_output_capture($mode, $display)
		{
			$result = null;
			$uniqueid=$this->display_buffercheck[$mode.'_'.$display];
			
			if (($display==$this->display[$uniqueid]['display']) && ($this->display[$uniqueid]['output_capture']))
			{
				$result=$this->output_capture[$uniqueid];
			}
			
			return $result;
		}
		
		function addShortformConclusion($columns)
		//concusion function, column will follow the column with value is the wanted variable 
		//will be output in vertical format
		//column contains the column and value to put on the column as an array
		{
			$column_string=json_encode($columns);
			$uniqueid=hash('adler32', $column_string);
			$this->shortform_conclusion[$uniqueid]=$columns;
		}
		
		function set_range($start, $size)
		{
			$this->display_range['start']=$start;
			$this->display_range['size']=$size;
		}
		
		function get_range()
		/*
		 * This is for paginator
		 */
		{
			return array_slice($this->elements, $this->display_range['start'], $this->display_range['size'], true);						
		}
		
		function as_range_list()
		/*
		 * This is used to get range but as list output
		 */
		{
			$new_class=get_class($this);
			$result=new $new_class($this->credentials, $this->secure_db, $this->readonly_db, $this->primaryTable);
			
			$result->display=$this->display;
			$result->display_buffercheck=$this->display_buffercheck;
			$result->closing_display=$this->closing_display;
			$result->shortform_conclusion=$this->shortform_conclusion;
			$result->format_display=$this->format_display;
			$result->processing_display=$this->processing_display;
			
			$result->elements=$this->get_range();
			
			return $result;
		}
		
		function get_type($item)
		{
			$type=gettype($item);
			if ($type=='string')
			{				
				if (is_numeric($item))
					$item=1*$item;				
			}
			$type=gettype($item);
			
			return $type=='object' ? get_class($item) : $type;
		}

		function preprocess_shortform($element, $shortform_value)
		{
			$result=array();
			foreach ($shortform_value as $key => $value)
			{
				if (array_key_exists($key, $this->preprocess_display ?? []))
				{
					$f_function=$this->preprocess_display[$key]['function'];
					$f_params=$this->preprocess_display[$key]['params'];
					
					if (method_exists($element, $f_function))
						$result[$key]=$element->$f_function($value, ...$f_params);
					else
						if (function_exists($f_function))
							$result[$key]=$f_function($value, ...$f_params);
						else
							$result[$key]=$value;
				}
				else
					$result[$key]=$value;
					
//				$this->display[$key]['v_type'][$this->get_type($value)]=true;
			}
			
			return $result;
		}
		
		function format_shortform($element, $shortform_value, $is_conclusion=false)
		{
			$result=array();
			foreach ($shortform_value as $key => $value)
			{
				if (array_key_exists($key, $this->format_display))
				{
					$f_function=$this->format_display[$key]['function'];
					$f_params=$this->format_display[$key]['params'];
										
					if ((!$is_conclusion) || ($this->display[$key]['v_type'][$this->get_type($value)]))
					{
						if (method_exists($element, $f_function)) 
							$result[$key]=$element->$f_function($value, ...$f_params);
						else 
							if (function_exists($f_function))
								$result[$key]=$f_function($value, ...$f_params);
							else
								$result[$key]=$value;
					}
					else 						
						$result[$key]=$value;
				}
				else
					$result[$key]=$value;
												
				$this->display[$key]['v_type'][$this->get_type($value)]=true;
			}
				
			return $result;
		}
		
		function values_median($key, ...$values)
		{
			$data=array();
			foreach ($values as $value)
				if ((is_numeric($value)) && (!is_nan($value)) && (!is_infinite($value)))
					$data[]=$value;
					
			$this->closing_display[$key]['result']=$this->stat_median($data);
			$this->closing_display[$key]['format']='float';
					
			return $this->closing_display[$key]['result'];					
		}
		
		function values_average($key, ...$values)
		{
			$data=array();
			foreach ($values as $value)
				if ((is_numeric($value)) && (!is_nan($value)) && (!is_infinite($value)))
					$data[]=$value;

			$this->closing_display[$key]['result']=$this->stats_average($data);
			$this->closing_display[$key]['format']='float';
			
			return $this->closing_display[$key]['result'];
		}
		
		function values_mean($key, ...$values)
		{
			$data=array();
			foreach ($values as $value)
				if ((is_numeric($value)) && (!is_nan($value)) && (!is_infinite($value)))
					$data[]=$value;
					
			$this->closing_display[$key]['result']=$this->stats_mean($data);
			$this->closing_display[$key]['format']='float';
					
			return $this->closing_display[$key]['result'];
		}		
		
		function values_percentage($key, ...$values)
		{
			$p=0;
			$this->closing_display[$key]['format']='float';
			foreach ($this->closing_display[$key]['c_params'] as ['value' => $criteria])
			{
				foreach ($values as $value)
					if ($value==$criteria) $p++;
			}
			
			$this->closing_display[$key]['result']=count($values)==0 ? 0 : 100.0*($p/count($values));
			
			return $this->closing_display[$key]['result'];			
		}
		
		function values_sum($key, ...$values)
		{
			$data=array();
			$all_integer=true;
			
			foreach ($values as $value)			
				if (is_numeric($value))
				{
					if (!is_integer($value)) $all_integer=false;
					
					$data[]=$value;
				}
				else
				{
					if (is_array($value))
						$data[]=array_sum($value);
				}
				
			$this->closing_display[$key]['result']=array_sum($data);
			$this->closing_display[$key]['format']=$all_integer ? 'integer' : 'float';
			
			return $this->closing_display[$key]['result'];
		}
		
		function values_count($key, ...$values)
		{
			$data=array();
			foreach ($values as $value)
				if ($value)
					$data[]=$value;
			
			$this->closing_display[$key]['format']='integer';
			$this->closing_display[$key]['result']=count($data);
			
			return $this->closing_display[$key]['result'];
		}
		
		function echo($key, ...$values)
		/*
		 * Directs writing whatever is written on the parameter
		 * 
		 * ...values is disregarded by this functio
		 *  
		 */
		{
			$this->closing_display[$key]['format']='string';
			$this->closing_display[$key]['result']=$this->closing_display[$key]['c_params'][0];
			
			return $this->closing_display[$key]['result'];
		}
		
		function cat($key, ...$values)
		/*
		 * Cat will select and get value from selectable data
		 */
		{
			$result=$this->get(...($this->closing_display[$key]['c_params'] ?? []));

			if (!is_numeric($result))
				$this->closing_display[$key]['format']='string';
			else
				$this->closing_display[$key]['format']='integer';
			
			$this->closing_display[$key]['result']=$result;
			
			return $this->closing_display[$key]['result'];			
		}
		
		function write_string($key, ...$values)		
		/*
		 * Needs refactor, too confusing!
		 */
		{						
			return $this->echo($key, ...$values);			
		}
		
		function read_html_cache($row, $mode, $display)
		{
			$column=$this->get_display_id($mode, $display);
			
			return $this->value_cache[$row][$column];
		}
			
		function read_html_footer($row, $mode, $display)
		{
			$column=$this->get_display_id($mode, $display);
			
			return $this->footer_cache[$column];			
		}
		
		function add_formatting($mode, $display, $function, ...$params)
		/*
		 * Format the output to human readable format
		 */
		{
			$uniqueid=$this->get_display_id($mode, $display);
			
			$this->format_display[$uniqueid]['function']=$function;						
			$this->format_display[$uniqueid]['params']=$params;
		}
		
		function add_preprocessor($mode, $display, $function, ...$params)
		/*
		 * Process the output, if the output is san array, or wanted to display
		 * in percentage, e.g. 0.7 => to 70, 
		 * 
		 * this should be done before formatting 
		 */
		{
			$uniqueid=$this->get_display_id($mode, $display);
			
			$this->preprocess_display[$uniqueid]['function']=$function;
			$this->preprocess_display[$uniqueid]['params']=$params;			
		}
		
		function get_display_id($mode, $display)
		{
			return hash('adler32', $mode.'_'.$display);
		}
		
		function add_conclusion($mode, $display, $conclusion_function, ...$params)
		{
			$uniqueid=$this->get_display_id($mode, $display);
			$this->closing_display[$uniqueid]['enabled']=true;
			$this->closing_display[$uniqueid]['c_function']=$conclusion_function;
			$this->closing_display[$uniqueid]['c_params']=$params;			
		}
		
		function add_conclusion_display($mode, $display, $conclusion_function, ...$params)
		{
			$this->add_conclusion($mode, $display, $conclusion_function, ...$params);
		}
		
		function cross_conclusion_display($uniqueid, $process_function, $conclusion_function, $secondary)
		
		{
			$this->closing_display[$uniqueid]['enabled']=true;
			$this->closing_display[$uniqueid]['secondary']=$secondary;
			$this->closing_display[$uniqueid]['p_function']=$process_function;
			$this->closing_display[$uniqueid]['c_function']=$conclusion_function;
		}
		
		function xf_multiply_fields($primary_value, $shortform_result, $secondary)
		{
			return $shortform_result[$secondary]*$primary_value;
		}		
		
		function xf_value_divide($key, $secondary, ...$values)
		{
			$data=array();
			
			foreach ($values as $value)
				if (is_numeric($value))
				{					
					if (!is_nan($value))
						$data[]=$value;
				}
						
			$secondary_value=$this->closing_display[$secondary]['result'];
			
			$this->closing_display[$key]['format']='float';
			if ($secondary_value>0)
				$this->closing_display[$key]['result']=array_sum($data)/$secondary_value;
			else
				$this->closing_display[$key]['result']=0;
			
			return $this->closing_display[$key]['result'];
		}
		
		function add_to_conclude($function_name, $callback, $conclude_function, ...$params)
		{
			$this->to_conclude[$function_name]=array();
			$this->to_conclude[$function_name]['callback']=$callback;
			$this->to_conclude[$function_name]['conclude_function']=$conclude_function;
			$this->to_conclude[$function_name]['params']=$params;

			//too hook it with toInlineHTML in elements, it must be added as hidden value
			//will add some complexity and will add some extra calculation but otherwise fine
			//e.g. some function might needs to be called twice with different display mode
			//the some function called twice with different display mode no longer applies
			//as some of the concluded function will be called right after it is displayed
			$this->addDisplay($function_name, '_hidden', $callback);			
		}
	
		function conclude($function_name='')
		{
			$result=false;
				
			if ($function_name)
			{
				$this->rewind();
				$element=$this->current();
				$conlcude_function=$this->to_conclude[$function_name]['conclude_function'];
				if (method_exists($element, $conlcude_function))
					$result=$element->$conlcude_function($this->to_conclude[$function_name]);
			}
				
			return $result;
		}
	
		/*function elements_function($function_name, ...$params)
		{
			foreach ($this->elements as $element)
			{
				if (method_exists($element, $function_name))
					$element->$function_name($params);
			}
		}*/

/*		function asDropdownArray($source_function=array('value' => 'getIndex', 'display' => 'getName'), $sort='', $criteria=array(), $limit=0)
		{
			$cache_condition=json_encode($source_function).'_'.$sort.'_'.json_encode($criteria).'_'.$this->count();
			$refresh=($cache_condition!=$this->cache_condition);
			$result=array();
					
			if ($refresh) $this->cache_asArray=array();
					
			if (empty($this->cache_asArray))
			{
				$unsorted_result=array();
				$sortkey_index=array();
				$needs_sort=(($sort) && ($source_function[$sort]));
						
				if (count($this->elements)>0)
					foreach ($this->elements as $item)
					{
						$excepted=false;
						foreach ($criteria as $function => $value)
						{
							if (!is_array($value))
								$value=[$value];
								if (!$item->$function(...$value))
								{
									$excepted=true;
									break;
								}
						}

						if (!$excepted)
						{
							$function_result=array();
							foreach ($source_function as $source => $function_params)
							{
								if (is_array($function_params))
								{
									$function=$function_params[f];
									$params=$function_params[p];
									$function_result[$source]=$item->$function(...$params);
								}
								else
								{
									$function=$function_params;
									$function_result[$source]=$item->$function();
								}
							}
							
							if ($needs_sort)
							{
								$f_index=$source_function[$sort];
								$sortkey_index[$item->$f_index()]=$function_result['value'];
							}
									
							$f_index=$source_function['value'];
							$unsorted_result[$item->$f_index()]=$function_result;
						}
						//			$result[$item->getIndex()]=array('value' => $item->getIndex(), 'display' => $item->getName());
					}
					
				if ($needs_sort)
				{				
					if (ksort($sortkey_index, SORT_NATURAL))
					{										
						foreach ($sortkey_index as $value)
						{
							$result[$value]=$unsorted_result[$value];
						}									
					}
					else
					{
						$result=$unsorted_result;
					}
				}
				else
				{
					$result=$unsorted_result;
				}
						
				$this->cache_asArray=$result;
				$this->cache_condition=$cache_condition;
			}
			else
			{
				$result=$this->cache_asArray;
			}
					
			return $limit==0 ? $result : array_slice($result, 0, $limit);
		}*/
						
		function add_showcheck(...$f_showcheck)
		/*
		 * format input multiple array, seperated by comma 
		 */
		{			
			if (empty($f_showcheck))
			{
				$f_showcheck='is_showing';			
				$this->showcheck_function[]=$f_showcheck;
			}
			else
			{
				$this->showcheck_function=$f_showcheck;
			}			
		}
		
		function add_showing_condition(...$conditions)
		/*
		 * can accept multiple condition with function name as the array key 
		 * if none of the keys are existed, then will accept as is and arrayed
		 */
		{			
			$this->showcheck_params=$conditions;
		}
		
		function add_showchecks($showchecks)
		/*
		 * this will add showchecks and work according, please use this instead
		 * the pair of add_showcheck and add_showing_condition in the future
		 * ['function' => ['param1', 'param2', 'etc']
		 */
		{
			$this->showcheck_function=[];
			$this->showcheck_params=[];
			
			foreach ($showchecks as $function => $params)
			{
				$this->showcheck_function[]=$function;
				$this->showcheck_params[$function]=$params;
			}
		}
		
		function showcheck($element)
		{
			$result=true;
			foreach ($this->showcheck_function as $f_showchecks)
			{
				$show_condition=$this->showcheck_params[$f_showchecks] ?? $this->showcheck_params;
				
				if (!$result=$element->$f_showchecks(...$show_condition))
					break;
			}
			
			return $result;			
		}						
	}
					
	class mp_list extends smart_list
	{
		use fibre;
		use fibre_list;
		
		function db_lookup(...$uniqueids)
		{
			$result=[];
			
			if (count($uniqueids)>=1)
			{
				$table_name=$this->primaryTable;
				
				$normal_uid=[];
				
				$db_uniqueids=[];
				foreach ($uniqueids as $uniqueid)
				{
					if (!$this->missing_data[$uniqueid])
						if (!$element=$this->select($uniqueid))
							/*
							 * Only reload when its not already existed
							 */
							$db_uniqueids[]=$this->element_id_field."='".$this->secure_db->SQLSafeString($uniqueid)."'";
						else
						{
							$result[$element->select($this->element_id_field)]=$element;
							if ($this->fb_has_fibre('db_read'))
								$this->fb_flush('', 'db_read');
						}
				}
				
				if (!empty($db_uniqueids))
				{
					$query='SELECT * FROM '.$table_name.' WHERE '.implode(' OR ', $db_uniqueids);
					$qr=$this->secure_db->query($query);
					
					while ($row=$this->secure_db->next_result($qr))
					{
						$element=$this->new();
						//					$element=new $this->element_class($this->credentials, $this->secure_db, $this->readonly_db,
						//						...$this->element_class_input);
						$element->load($row[$this->element_id_field], $row);
						
						$element->set_seq_number($this->count());
						$this->add($element);
						$normal_uid[$element->select($this->element_id_field)]=1;
						
						$result[$element->select($this->element_id_field)]=$element;
						if ($this->fb_has_fibre('db_read'))
							$this->fb_flush('', 'db_read');							
					}
					
					if ($this->secure_db->is_table_archived($table_name))
					{
						$db_uniqueids=[];
						foreach ($uniqueids as $uniqueid)
							if ((!isset($normal_uid[$uniqueid])) && (!$this->select($uniqueid)) && (!$this->missing_data[$uniqueid]))
							{
								$db_uniqueids[]=$this->element_id_field."='".$this->secure_db->SQLSafeString($uniqueid)."'";
								
								/*
								 * Presume Missing
								 */
								$this->missing_data[$uniqueid]=true;
							}
						
						if (!empty($db_uniqueids))
						{
							$query='SELECT * FROM '.$table_name.' WHERE '.implode(' OR ', $db_uniqueids);
							$qr=$this->secure_db->archive_query($query);
							//							var_dump($normal_uid, $db_uniqueids);echo("<BR><BR>");
							while ($row=$this->secure_db->archive_next_result($qr))
							{
								$element=$this->new();
								$element->load($row['uniqueid'], $row);
								$this->add($element);
								
								$this->missing_data[$row['uniqueid']]=false;
								
								$result[$element->select($this->element_id_field)]=$element;
								if ($this->fb_has_fibre('db_read'))
									$this->fb_flush('', 'db_read');
							}
						}
					}
				}
			}
			
			return $result;
		}		
		
		function db_fill($conditions=[], $addon_indices=[], $from_archive=false, $limit=PHP_INT_MAX, &$result=[]): mp_list
		/*
		 * Unused, needs updating 
		 * 
		 * General list functionto lad the database to this
		 * 
		 * Warning: this process loads the whole database, and only put on the list with conditions.
		 *  
		 * It is a slow process.  
		 */
		{
			$settings=$this->get_global('settings');
			
			if ($this->default_dbfill_condition)
				$conditions=array_merge($this->default_dbfill_condition, $conditions);
				
			$table_name=$settings->select($this->list_owner, $this->table_hr_name);
				
			$element_class=$this->element_class;
				
			if (!$from_archive)
				$database=$this->secure_db;
			else
				$database=$this->secure_db->archive_db;
						
			$qr=$database->query("SELECT * FROM ".$table_name);
						
			$count=0;
			while (($row=$database->next_result($qr)) && ($count<$limit))
			{
				$element=$this->new();
				/*				$element=new $element_class($this->credentials, $database, $this->readonly_db,
				 ...$this->element_class_input);*/
							
				/*
				 * If the function load returns something, then use the return value from load
				 * it might be different class
				 */
				$detailed_element=$element->load($row[$element->id_field], $row);
				$element_class=get_class($element);
				if (($detailed_element) && ($detailed_element instanceof $element_class))
					$element=$detailed_element;
								
				$store_list=true;
				foreach ($conditions as [f => $function, p => $params])
				{
					if (!is_array($params)) $params=[$params];
								
					$r=$element->{$function}(...$params);
									
					if (($r===false) || ($r===null))
					{
						$store_list=false;
						break;
					}
				}
								
				if ($store_list)
				{
					$count++;
					$element->set_seq_number($count);
					$this->add($element);
					
					if ($this->fb_has_fibre('db_read'))
						$this->fb_flush('', 'db_read');						
				}
			}
						
			foreach ($addon_indices as $addon_index)
				$this->add_index($addon_index);
							
			return $this;
		}
		
		function dynamic_foreach(&$elements)
		/*
		 * This generator function is useful to be attached in for loop where
		 * the for iterated elements is added within the loop.
		 *  
		 */
		{
			$count=0;
			$total_elements=count($elements);
			$keys=array_keys($elements);
			
			while (count($elements) > $count)
			{									
				if ($count>=$total_elements)
				{
					$keys[$count]=array_key_last($elements);
					$total_elements++;
				}
				
				yield $elements[$keys[$count]];

				$count++;				
			}
		}
		
		function render()
		{
			return [type => 'html', 'value' => $this->toHTML()];
		}
		
		function toHTML()
		{
			$tableheader="";
			foreach ($this->display as $displayitem)
			{
				if ($displayitem['display']!='_hidden')
					$tableheader.="<th>".$displayitem['display']."</th>";
			}
			$result="<table><tr>$tableheader</tr>";
			
			if (empty($this->display_range))
				$elements=&$this->elements;
			else
				$elements=array_slice($this->elements, $this->display_range['start'], $this->display_range['size'], true);
				
			foreach ($this->dynamic_foreach($elements) as $element)
			{				
				//				var_dump($element);
				$showing=false;
						
				/*				if ($element)
				 if ($element->{$this->showcheck_function}(...$this->showcheck_params))
				 $showing=true;*/
				if ($element)
				 	$showing=$this->showcheck($element);
						 	
			 	if ($showing)
			 	{
			 		$shortform=$this->element_shortform;
			 		$shortform_result=$element->$shortform($this->display, $this->to_conclude, $this->output_capture);
						 		//					var_dump($shortform_result);
			 		$preprocessed_shortform=$this->preprocess_shortform($element, $shortform_result);
			 		$format_shortform=$this->format_shortform($element, $preprocessed_shortform);
			 		$result.=$this->add_html_table_row(...array_values($format_shortform));
						 		
			 		$this->value_cache[$element->select('uniqueid')]=$preprocessed_shortform;
						 		
			 		foreach ($preprocessed_shortform as $key => $value)
			 			if	(!array_key_exists('secondary', $this->closing_display[$key]))
			 				$this->closing_display[$key]['params'][]=$value;
		 				else
		 				{
		 					$secondary=$this->closing_display[$key]['secondary'];
		 					$p_function=$this->closing_display[$key]['p_function'];
		 					$this->closing_display[$key]['params'][]=$this->$p_function($value, $preprocessed_shortform, $secondary);
		 				}
						 				
						 				//					var_dump($element->toShortform($this->display, $this->to_conclude));
		 				
	 				if ((method_exists($element, $this->element_shortform_conclusion)) && (!empty($this->shortform_conclusion)))
	 				{
	 					//if its implemented
	 					$after_shortform=$this->element_shortform_conclusion;
	 					$result.=$element->$after_shortform($this->shortform_conclusion, $this->display);	 					
	 				}
	 				if ($this->fb_has_fibre('output'))
	 					$result=$this->fb_flush($result, 'output'); 					
			 	}
			}
					
			$footer=array();
			$show_footer='';
					
			foreach ($this->closing_display as $key => $display_footer)
			{
				if (array_key_exists('enabled', $display_footer))
				{
					$c_function=$display_footer['c_function'];
					$c_params=$display_footer['params'] ?? [];
							
					if (!$display_footer['secondary'])
						$this->$c_function($key, ...$c_params);
					else
						$this->$c_function($key, $display_footer['secondary'], ...$c_params);
									
					$footer[$key]=$this->closing_display[$key]['result'];
									
					$show_footer=true;
				}
				else
					$footer[$key]='';
			}
					
			if (($show_footer) && (!empty($this->elements)))
			{
				$element=$this->elements[array_rand($this->elements)];
				$this->footer_cache=$footer;
				if ($element)
					$footer=$this->format_shortform($element, $footer, true);
				$result.=$this->add_html_table_header(...array_values($footer));
			}
					
			$result.=$this->close_html_table();
			
			if ($this->fb_has_fibre('output'))
				$result=$this->fb_flush($result, 'output');
				
			return $result;
		}		
	}
	
	trait list_form_trait
	/*
	 * This trait will needs to be imported for converting list form to HTML format
	 * 
	 * Be it HTML Table or HTML Dropdown
	 */
	{
		function render()
		/*
		 * Render the list, it could be as HTML or as CSV, or could be as anything.
		 */
		{
			return [type => 'html', 'value' => $this->toHTML()];
		}
		
		function toHTML()
		{
			$tableheader="";
			foreach ($this->display as $displayitem)
			{
				if ($displayitem['display']!='_hidden')
					$tableheader.="<th>".$displayitem['display']."</th>";
			}
			$result="<table><tr>$tableheader</tr>";
			
			if (empty($this->display_range))
				$elements=$this->elements;
			else
				$elements=array_slice($this->elements, $this->display_range['start'], $this->display_range['size'], true);
			
			foreach ($elements as $element)
			{
						//				var_dump($element);
				$showing=false;
						
						/*				if ($element)
						 if ($element->{$this->showcheck_function}(...$this->showcheck_params))
						 $showing=true;*/
				if ($element)
					/*
					 * Checking if this element should be visible
					 */
				 	$showing=$this->showcheck($element);
						 	
			 	if ($showing)
			 	{
			 		$shortform=$this->element_shortform;
			 		
			 		/*
			 		 * Shortform return the displayed elements  
			 		 * 
			 		 * Most element uses function toShortform, but it can be customed
			 		 */
			 		$shortform_result=$element->$shortform($this->display, $this->to_conclude, $this->output_capture);
						 							
			 		$preprocessed_shortform=$this->preprocess_shortform($element, $shortform_result);
			 		
			 		/*
			 		 * Formatting each and every elements
			 		 */
			 		$format_shortform=$this->format_shortform($element, $preprocessed_shortform);
			 		
			 		/*
			 		 * Converting to HTML Row
			 		 */
			 		$result.=$this->add_html_table_row(...array_values($format_shortform));
						 		
			 		$this->value_cache[$element->select('uniqueid')]=$preprocessed_shortform;
					
			 		foreach ($preprocessed_shortform as $key => $value)
			 			if	(!array_key_exists('secondary', $this->closing_display[$key]))
			 				$this->closing_display[$key]['params'][]=$value;
		 				else
		 				{
		 					$secondary=$this->closing_display[$key]['secondary'];
		 					$p_function=$this->closing_display[$key]['p_function'];
		 					$this->closing_display[$key]['params'][]=$this->$p_function($value, $preprocessed_shortform, $secondary);
		 				}
						 				
						 				//					var_dump($element->toShortform($this->display, $this->to_conclude));
						 				
					if ((method_exists($element, $this->element_shortform_conclusion)) && (!empty($this->shortform_conclusion)))
		 			{
		 					//if its implemented
		 				$after_shortform=$this->element_shortform_conclusion;
		 				$result.=$element->$after_shortform($this->shortform_conclusion, $this->display);
		 			}
			 	}
			}
			
			$footer=array();
			$show_footer='';
					
			foreach ($this->closing_display as $key => $display_footer)
			{
				if (array_key_exists('enabled', $display_footer))
				{
					$c_function=$display_footer['c_function'];
					$params=$display_footer['params'] ?? [];
									
					if (!$display_footer['secondary'])
						$this->$c_function($key, ...$params);
					else
						$this->$c_function($key, $display_footer['secondary'], ...$params);
									
					$footer[$key]=$this->closing_display[$key]['result'];
									
					$show_footer=true;
				}
				else
					$footer[$key]='';
			}
					
			if (($show_footer) && (!empty($this->elements)))
			{
				$element=$this->elements[array_rand($this->elements)];
				$this->footer_cache=$footer;
				if ($element)
					$footer=$this->format_shortform($element, $footer, true);
					$result.=$this->add_html_table_header(...array_values($footer));
			}
					
			$result.=$this->close_html_table();
					
			return $result;
		}
		
		function to_dropdown_data($criterion=[])
		/*
		 * This will return all index and prepared in a similar format to asDropdownArray 
		 * 
		 * This is useful for qr as dropdown because you don't need to see whats beneeth it
		 * 
		 * This will make things exceptionally fast with deferred loading set on the list
		 * 
		 * for example qr_loom_dismount_form
		 * 
		 * only modern type is used
		 */
		{
			$result=[];
			foreach ($this->elements as $element)
			{
				$included=true;
				
				foreach ($criterion as $criteria)
				{
					if (($criteria[f]) && (method_exists($element, $criteria[f])))
					{
						$func=$criteria[f];
						/*
						 * Standarise as Array to make it unpackable
						 */
						if (!is_array($criteria[p])) $criteria[p]=[$criteria[p]];
						if (!$element->$func(...$criteria[p]))
						{
							$included=false;
							break;
						}
					}
					else
						$included=false;						
				}
				
				if ($included)
				{
					$index=$element->getIndex();
					$result[$index]=[value => $index, display => $index];
				}
			}
				
			return $result;
		}
		
		function asDropdownArray($source_function=[value => 'getIndex', display => 'getName'], $sort='', $criteria=[], $limit=0)
		/*
		 * Criteria format is f=>[p], function and params,
		 *
		 * For source function 
		 *  
		 * this is modern approach where you can actually use
		 * [f => function, p => params] for the function name on display and index
		 * e.g. [value => 'getIndex', display => [f => vanity_name, p => 'short']
		 * 
		 * for both value, display and sort
		 */
		{
			$cache_condition=json_encode($source_function).'_'.$sort.'_'.json_encode($criteria).'_'.$this->count().'_'.$limit;
			$refresh=($cache_condition!=$this->cache_condition);
			$result=array();
			
			if ($refresh) $this->cache_asArray=array();
			
			if (empty($this->cache_asArray))
			{
				$unsorted_result=array();
				$sortkey_index=array();
				$needs_sort=(($sort) && ($source_function[$sort]));
				
				$count=0;
				
				if (count($this->elements)>0)
					foreach ($this->elements as $element)
					{
						if ($element->_is_deferred())
							$element->_activate();
							
						$excepted=false;
						foreach ($criteria as $function => $value)
						{
							if (method_exists($element, $function))
							/*
							 * Classic function => value execution
							 */
							{
								/*
								 * Standarise as Array to make it unpackable
								 */
								if (!is_array($value)) $value=[$value];
								if (!$element->$function(...$value))
								{
									$excepted=true;
									break;
								}
							}
							else 
							/*
							 * Modern using f => function params p =>  params
							 */
							{									
								if (($value[f]) && (method_exists($element, $value[f])))
								{
									$func=$value[f];
									/*
									 * Standarise as Array to make it unpackable
									 */
									if (!is_array($value[p])) $value[p]=[$value[p]];									
									if (!$element->$func(...$value[p]))
									{
										$excepted=true;
										break;
									}
								}
								else
									$excepted=true;
							}
						}
						
						if (!$excepted)
						{
							$count++;
							
							$function_result=[];
							foreach ($source_function as $source => $function_params)
							{
								if (is_array($function_params))
								/*
								 * function params means function & params 
								 * this is modern approach where you can actually use
								 * [f => function, p => params] for the function name on display and index
								 * e.g. [value => 'getIndex', display => [f => vanity_name, p => 'short'] 
								 */
								{
									$function=$function_params[f];
									$params=is_array($function_params[p]) ? $function_params[p] : [$function_params[p]];
									$function_result[$source]=$element->$function(...$params);
								}
								else
								/*
								 * Classical approach, no params
								 */
								{ 
									$function=$function_params;
									$function_result[$source]=$element->$function();
								}
							}
							
							if ($needs_sort)
							{								
								switch ($sort)
								{
									case display: 										
										$sortkey_index[$function_result[display]]=$function_result[value];
										break;
									case value:
										$sortkey_index[$function_result[value]]=$function_result[value];
										break;
									case sort:
										$sort_fp=$source_function[$sort];
										
										$f_index=$sort_fp;
										$f_param=[];
										
										if (is_array($sort_fp))
										{
											$f_index=$sort_fp[f];
											$f_param=is_array($sort_fp[p]) ? $sort_fp[p] : [$sort_fp[p]];
										}
										
										$sortkey_index[$element->$f_index(...$f_param)]=$function_result[value];
										break;
								}								
							}
							
							$unsorted_result[$function_result[value]]=$function_result;
						}
						//			$result[$item->getIndex()]=array('value' => $item->getIndex(), 'display' => $item->getName());
						
						if (($limit!=0) && ($count==$limit))
							break;
					}
				
				if ($needs_sort)
				{
					ksort($sortkey_index, SORT_NATURAL);
					foreach ($sortkey_index as $value)
					{ 
						$result[$value]=$unsorted_result[$value];							
					}
				}
				else
				{
					$result=$unsorted_result;
				}
				
				$this->cache_asArray=$result;
				$this->cache_condition=$cache_condition;
			}
			else
			{
				$result=$this->cache_asArray;
			}
			 
			return $result;
		}
	}
?>