<?php	
	/*
	 * This trait is used to enable PHP fiber 
	 */

	trait fibre
	/*
	 * Fibre to avoid confusion with native Fiber of PHP
	 * 
	 * Use this trait in needed class, e.g. list or form to make it support fibre
	 * 
	 * But you have change in the junction that contains the class to output promise
	 * in to HTML method 
	 */
	{
		var $_fibres;
		
		function fb_attach_fibre(fiber $fibre, $x)
		/*
		 * Attaching fibre to this class with this method
		 */
		{
			$this->_fibres[$x]=$fibre;
			$this->set_fibre_id($x);
			
//			if (!$fibre->isStarted())
//				return $fibre->start($x);
		}
		
		function fb_resume($message, $x=null)
		/*
		 * resume fibre execution, when x is empty will resume 
		 * resume every other fibre
		 * 
		 * otherwise will resume only $x
		 */
		{			
			return $this->_fibres[$x ?? $this->fb_default_id]->resume($message);
		}
						
		function fb_flush($output, $x=null)
		/*
		 * Call this method during output step (0, 1, 2, 3)
		 * 
		 * how to call $result=$this->fb_flush($result); 
		 * 
		 * do it one by one for each fibre, not all fibre
		 * 
		 */
		{									
			return $this->_fibres[$x ?? $this->fb_default_id]->suspend($output);
		}
		
		function fb_fibre_enabled($x=null)
		/*
		 * If there is a fibre, then this class is fibre enabled
		 */
		{
			return $x ? isset($this->_fibres[$x]) : count($this->_fibres ?? [])>0;
		}
		
		function fb_has_fibre($x)
		{
			return isset($this->_fibres[$x]);
		}
				
		function fb_finished($x=[])
		/*
		 * Check if all fibre has been terminated
		 */
		{	
			$result=true;

			if (empty($x))
				$x=array_keys($this->_fibres ?? []);
			foreach ($x as $fid)
				if (!$this->_fibres[$fid]->isTerminated())
				{
					$result=false;
					break;
				}

			return $result;
//			$this->_fibres[$this->fb_default_id]->isTerminated();
		}
		
		function set_fibre_id($x)
		/*
		 * Setting default fiber id
		 */
		{
			$this->fb_default_id=$x;
		}
		
		function fb_process_functions($fp)
		/*
		 * This method will process the functions when the fibre starts, and especially useful
		 * when starting 2 sequential databaases
		 */
		{
			foreach ($fp as [f => $f, p => $p])
			{
				$this->{$f}(...array_values($p));				
			}
		}
		
		function fb_create_fibre($x, $function_params=[])
		/*
		 * When creating fibre will be executed sequentially, so order is matter.
		 * 
		 * It is best to add fb_flush right after step_number started
		 * 
		 * function_params are the functions that is going to be executed when the 
		 * fibre starts.  
		 * 
		 * format [['f' => 'function', 'p' => [params]]]
		 *  
		 * It can accept multiple functions and each with multiple params
		 * 
		 * supported fibre type ($x) are currently 'output' and 'db_read'
		 */
		{
			$fibre=match ($x)
			{
				'output' => new Fiber(function($fp) {$this->fb_process_functions($fp);}),																		
				'db_read' => new Fiber(function($fp) {$this->fb_process_functions($fp);}),
				'db_write' => new Fiber(function($fp) {$this->fb_process_functions($fp);})
			};
			
			$this->_fibres[$x]=$fibre;
			$this->_fibres_functions[$x]=$function_params;
			
			if (!$this->fb_default_id)
				$this->set_fibre_id($x);
		}
						
		function fb_loop($x=[])
		/*
		 * Start all the fibre here, and 
		 * and then will run into fibre loop
		 * 
		 * Call this from foreach and only yield output from output fibre  
		 */
		{
			if (empty($x))
				$x=array_keys($this->_fibres);
				
			foreach ($x as $fid)
			{
				$f_p=$this->_fibres_functions[$fid];				
				$r=$this->_fibres[$fid]->start($f_p);
				if ($fid=='output')
					yield $r;
			}
			
			while (!$this->fb_finished($x))
			{
				foreach ($x as $fid)
				{
					if ($this->_fibres[$fid]->isSuspended())
						$r=$this->_fibres[$fid]->resume();
					switch ($fid)
					{
						case 'output':
							yield $r;
							break;
					}					
				}
			}
		}
	}
	
	trait fibre_list
	{
		use \fibre;

		/*
		 * Perhaps this trait can be combined to smart_list directly to make fibre_aware traits
		 * on smart_list hence loading process can be done step by step, but it is job for monday
		 */
		
		function available_data()
		/*
		 * return whatever is available on the database, use this if you wanted to sort the data
		 * after its loaded.
		 */		
		{
			foreach ($this->elements as $element)
				yield $element;
		}
		
		function fb_db_fill($conditions=[], $addon_indices=[], $from_archive=false, $limit=PHP_INT_MAX)		
		/*
		 * Do not MIX with DB Fill from smart_list, even if looks so similar.
		 * 
		 * This method use fibre_enabled detection when working, if this list is fibre_enbaled, 
		 * then it behave using yield, otherwise same as regular db_fill.
		 * 
		 * However on fibre aware list it will NOT directly load the whole table when its needed 
		 * too like db_fill, as it will keep yielding hence stopped the process  
		 *  
		 * instead if wante
		 */
		{
			$settings=$this->get_global('settings');
			
			if ($this->default_dbfill_condition)
				$conditions=array_merge($this->default_dbfill_condition, $conditions);
				
			$table_name=$this->get_global('data_module')->select($this->list_owner, $this->table_hr_name) ?? $settings->select($this->list_owner, $this->table_hr_name);
				
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
				foreach ($conditions as ['f' => $function, 'p' => $params])
				{
					if (!is_array($params)) $params=[$params];
//					var_dump($conditions);
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
					
					if ($this->fb_fibre_enabled())
						yield $element;
				}
			}
						
			foreach ($addon_indices as $addon_index)
				$this->add_index($addon_index);
							
			return $this;							
		}
		
		function fb_db_lookup(...$uniqueids)
		/*
		 * This should not be combined with regular db_lookup, same reason as above
		 */
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
							
							if ($this->fb_fibre_enabled())
								yield $element;
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
						
						if ($this->fb_fibre_enabled())
							yield $element;
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
								
								if ($this->fb_fibre_enabled())
									yield $element;
							}
						}
					}
				}
			}
			
			return $result;
		}	
		
		function fb_toHTML($fill_function='', $fill_params=[])
		/*
		 * Filling from database to HTML
		 */
		{
			$tableheader="";
			foreach ($this->display as $displayitem)
			{
				if ($displayitem['display']!='_hidden')
					$tableheader.="<th>".$displayitem['display']."</th>";
			}
			$result="<table><tr>$tableheader</tr>";
			$result=$this->fb_flush($result);
			
			foreach ($this->{$fill_function}(...$fill_params) as $element)
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
			 		$result=$this->fb_flush($result);
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
			$result=$this->fb_flush($result);
			return $result;					
		}
	}
?>