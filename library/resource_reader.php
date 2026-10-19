<?php
	/*
	 * This will be resource / csv cache for the whole system.
	 *  
	 * $_resources variable will add $_resources global variable for the whole system.  
	 * Please do not use $_resources variable anywhere else.
	 * 
	 */
	$_resources=['name' => 'Resource Reader'];

	trait resource_reader
	{
		var $_resources;
		
		final function resource_exists($uri, $format='csv')
		{
			if ($uri)
			{
				$file=new \lib_file_handler($uri, $format, 'r');
			
				$result=$file->exists();
			}
			else
				$result=false;
			
			return $result;
		}
		
		final function resource_read($name, $uri, $format='csv', $refresh=false)
		/*
		 * This method will check for global resources first, if not existed before reading.
		 * 
		 * Then will read it when not yet loaded.  Otherwise will ignore it.
		 * 
		 */
		{
			global $_resources;
			
			if (!isset($this->_resources)) $this->_resources=[];
			
			if (($refresh) || (!isset($_resources[$uri])))
			{
				$file=new \lib_file_handler($uri, $format, 'r');
				switch ($format)
				{
					case 'csv': default:
						$handler='csv_handler';
						break;
					case 'ssv':
						$handler='ssv_handler';
						break;
					case 'json':
						$handler='json_handler';						
						break;
				}
				if ($file->read($handler, $format))
				{
					$resource=$file->get($handler);
								
					$_resources[$uri]=$resource;
					$this->_resources[$name]=$_resources[$uri];
				}
			}
			else
			{
				if (isset($_resources[$uri]))
				{
					$this->_resources[$name]=$_resources[$uri];
					$resource=$_resources[$uri];
				}
				else
				{
					/*
					 * Unlikely, but might happen during accident.
					 */
					throw new Exception('Resource '.$uri.' did not exists');					
				}
			}
			
			return $resource;
		}
		
		final function resource_get($name)
		{
			if (isset($this->_resources[$name]))
			{
				return $this->_resources[$name];
			}
			else 
			{
				throw new Exception('Resource '.$name.' did not exists');				
			}
		}
		
		final function resource_lookup($value, $name, $field, ...$target)
		/*
		 * Lookup resources from table
		 *
		 * $field = field on the array
		 * $value = the target value
		 * $target = what is wanted from the row, can be array for multiple value
		 */
		{
			$result=[];
			$resource=$this->_resources[$name]['data'];
			
			foreach ($resource as $row)
			{
				if ($row[$field]==$value)
				{
					foreach ($target as $t)
						$result[]=$row[$t];
						break;
				}
			}
			
			return $result;
		}
		
		function resource_translate($value, $name, $field, $target)
		/*
		 * Translate value using resource table
		 *
		 * $value = the target value
		 * $name = the name of resource
		 * $field = field on the array
		 * $target = the wanted field from array
		 */
		{
			$result='';
			$resource=$this->_resources[$name]['data'];
			
			foreach ($resource as $row)
			{
				if ($row[$field]==$value)
				{
					$result=$row[$target];
					break;
				}
			}
			
			return $result;
		}
	}
?>