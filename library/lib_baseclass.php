<?php
/*
 * rf_ : reserved function will be invoked when calling hidden function 
 * cf_ : conclude function -> will be invoked on conclusion
 * df_ : data function will be called only when required with data
 */
	include_once("accent.php");
	include_once("lib_symbols.php");
    include_once("lib_constants.php");
    include_once('lib_fibre.php');
    include_once('lib_datetime.php');
    include_once('lib_math.php');
    			
	abstract class s_lib
	{
		use t_datetime;
		use statistics;
		
		var $namespace;
		var $globals;
		
		static protected final function _get($root, String|Int $index, String|Int ...$indices)
		/*
		 * This function will return a value in a root array with index and indices recursively
		 *
		 * index ...indices is a hack of the same thing, only used to improve performance of the function from array
		 * manipulation
		 *
		 * This funciton is marked as private, as this is not supposed to be called, but only called by p_store and t_store
		 *
		 * And enables data manipulation is fashion of trees, instead of linear
		 */
		{
			$result=null;
			
			if (!empty($indices))
			{
				if (isset($root[$index]))
				{
					$result=self::_get($root[$index], ...$indices);
				}
			}
			else
			{
				
				$result=isset($root[$index]) ? $root[$index] : null;
			}
			
			return $result;
		}
		
		static protected final function _set(&$root, $value, $index, ...$indices)
		/*
		 * This function will set in a root array with index and indices recursively
		 *
		 * index ...indices is a hack of the same thing, only used to improve performance of the function from array
		 * manipulation
		 *
		 * This funciton is marked as private, as this is not supposed to be called, but only called by p_store and t_store
		 *
		 * And enables data manipulation is fashion of trees, instead of linear
		 */
		{
			if (!empty($indices))
			{
				if (!isset($root[$index]))
					$root[$index]=[];
					
				$root[$index]=self::_set($root[$index], $value, ...$indices);
			}
			else
			{
				$root[$index]=$value;
			}
			
			return $root;
		}
		
		static protected final function _rem(&$root, String|Int $index, String|Int ...$indices)
		/*
		 * Deletion of a branch of leaf, returns the result
		 *
		 * This function is marked as private and should not be called by itself from outside class.
		 *
		 * Use remove to call this!.
		 */
		{
			$result=null;
			
			if (!empty($indices))
			{
				if (isset($root[$index]))
				{
					$result=self::_rem($root[$index], ...$indices);
				}
			}
			else
			{
				if (isset($root[$index]))
				{
					$result=$root[$index];
					unset ($root[$index]);
				}
				else
					$result=null;
			}
			
			return $result;
		}
		
		function fb_fibre_enabled()
		{
			return false;
		}
		
		static function find_namespace($object)
		{
			return self::find_object_location($object)['namespace'];
		}
		
		static function find_interface($object)
		{
			return self::find_object_location($object)['object'];			
		}
		
		static function find_object_location($object)
		{
			$x=explode('\\', get_class($object));
			$if=array_pop($x);
			
			return ['namespace' => implode('\\', $x), 'object' => $if];
		}
		
		function start_time(...$id)
		{
			$time=microtime(true);
			if (empty($id))
				$id[]=0;
			foreach ($id as $i)
				$this->rv_start_time[$i]=$time;
		}
		
		function exec_time($id=0, $caption='')
		{
			$e_time=microtime(true)-$this->rv_start_time[$id];
			return $caption.' ('.$e_time.')';
		}
		
		function to_global($name, $value=null)
		{
			global $global_variables;
			
			if ($value)
				$global_variables[$name]=$value;
			else
				$global_variables[$name]=$this;
		}
		
		function get_expansion($name): ?extensions
		/*
		 * Alias to get extension, but its a copy because the contents might be diffeernt in the future 
		 */
		{
			$result=$this->get_global($name);
			
			if ($result instanceof extensions)
				return $result;
			else
				return null;					
		}
		
		function get_extension($name): ?extensions
		{
			$result=$this->get_global($name);
			
			if ($result instanceof extensions)
				return $result;
			else
				return null;
		}
		
		function get_global($name)
		{
			global $global_variables;
			
			if ($this->is_global($name))
				$this->globals[$name]=$global_variables[$name];
			else
			{
				$settings=$global_variables['settings'];
				if ($params=$settings->is_promised($name))
				{
					$extension=new $name(...$params);
					$settings->add_extension($extension);
						
					$settings->associate_extensions();
						
					$this->globals[$name]=$extension;
				}
				else
					$this->globals[$name]=null;
			}
				
			return $this->globals[$name];
		}
		
		function is_global($name, $index='')
		{
			global $global_variables;
			
			if ($index=='')
			{
				if (isset($global_variables[$name]))
					$result=true;
				else
					$result=false;
			}
			else
			{
				if (isset($global_variables[$name][$index]))
					$result=true;
				else
					$result=false;
			}
			
			return $result;
		}
		
/*		function associate_variable($name, &$variable)
		{
			$this->{$name}=&$variable;
		}*/
		
		function encrypt($input, #[\SensitiveParameter] $password, #[\SensitiveParameter] $iv)
		{
			$password=base64_encode($password->getValue());
			$iv = base64_encode($iv->getValue());
			$compressed=gzcompress($input);
			
			return openssl_encrypt($compressed, encryption_chiper, $password, false, $iv);
			//			return $input;
		}
		
		function decrypt($input, #[\SensitiveParameter] $password, #[\SensitiveParameter] $iv)
		{
			$password=base64_encode($password->getValue());
			$iv = base64_encode($iv->getValue());
			$decrypted=openssl_decrypt($input, decryption_chiper, $password, false, $iv);
			
			if ($decrypted)
				return gzuncompress($decrypted);
			else
				return $input;
					//			return $input;
		}
		
		static function cs_algo($mode)
		{
			switch ($mode)
			{
				case 1:
					$result=['algo' => 'sha3-256', 'function' => 'hash_hmac'];
					break;
				default:
					$result=['algo' => 'sha256', 'function' => 'hash'];
			}
			
			return $result;
		}
		
		static function add_data_integrity($cs_algo, $data)
		{				
			$algo=self::cs_algo($cs_algo)['algo'];
			
			/*
			 * Add a random value to increase data entropy
			 */
			$data['_randval']=self::random_uniqueid(32);		
			$data['_checksum']=hash_hmac($algo, json_encode($data), $data['_randval']);
			
			return $data;
		}
		
		static function check_data_integrity($cs_algo, $data)
		{		
			$algo=self::cs_algo($cs_algo)['algo'];
			
			$checksum=$data['_checksum'];
			$rehash_checksum=null;
			if ($checksum)
			{
				array_pop($data); //pop checksum
				
				$rehash_checksum=hash_hmac($algo, json_encode($data), $data['_randval']);
				array_pop($data); //pop randval
			}
			
			return hash_equals($checksum, $rehash_checksum) ? $data : [];
		}		
		
		function currency_output($value, $format=true, $output_locale=false)
		{
			return finance::money_format($output_locale ? $output_locale : set_locale(LC_MONETARY), $value);			
			
/*			$result=$value;
			
			$current_locale=setlocale(LC_MONETARY, 0);
			
			if ($output_locale)
				$invoice_locale=$output_locale;
			else
				$invoice_locale=$current_locale;
					
			if ($format)
			{
				if ($current_locale==$invoice_locale)
				{
					$result=money_format('%i', $value);
				}
				else
				{
					setlocale(LC_MONETARY, $invoice_locale);
					$result=money_format('%i', $value);
					setlocale(LC_MONETARY, $current_locale);
				}
			}
			
			return $result;*/
		}
		
		function currency_value($value, $format=true, $output_locale=false)
		{
			$result=$value;
			
			$current_locale=setlocale(LC_MONETARY, 0);
			
			if ($output_locale)
				$invoice_locale=$output_locale;
			else
				$invoice_locale=$current_locale;
					
			if ($format)
			{
				$decimal=2;
				if ($invoice_locale=='ja_JP')
					$decimal=0;
							
				if ($current_locale==$invoice_locale)
				{
					$result=number_format($value, $decimal);
				}
				else
				{
					setlocale(LC_MONETARY, $invoice_locale);
					$result=number_format($value, $decimal);
					setlocale(LC_MONETARY, $current_locale);
				}
			}
					
			return $result;
		}
		
		static function string_equals($string1, $string2)
		{
			return $string1==$string2;
		}
		
		static function string_similar($string1, $string2)
		{
			if (!is_array($string1))
				return levenshtein($string1, $string2)<=1;
			else
				return 0;
		}
		
		static function number_lt($param1, $param2, $equal=false)
		{
			return $equal ? $param1<=$param2 : $param1<$param2;
		}
		
		static function number_gt($param1, $param2, $equal=false)
		{
			return $equal ? $param1>=$param2 : $param1>$param2;
		}
		
		function get_closest_value($target, $array, $field, $compare_function)
		{
			$result=reset($array);//get the first or only element
			
			switch ($field)
			{
				case 'date':
					//					$min=abs($this->$compare_function(date('1970-01-01'), $target));
					$min='';
					foreach ($array as $key => $value)
					{
						if (!$min)
						{
							$min=abs($this->$compare_function($key, $target));
							$result=$value;
						}
						else if (abs($this->$compare_function($key, $target))<=$min)
						{
							$min=abs($this->$compare_function($key, $target));
							$result=$value;
						}
					}
					break;
					
				case 'date_field':
					//					$min=abs($this->$compare_function(date('1970-01-01'), $target));
					$min='';
					foreach ($array as $key => $value)
					{
						if (!$min)
						{
							$min=abs($this->$compare_function($value['date'], $target));
							$result=$value;
						}
						else if (abs($this->$compare_function($value['date'], $target))<=$min)
						{
							$min=abs($this->$compare_function($value['date'], $target));
							$result=$value;
						}
					}
					break;
			}
			
			return $result;
		}
		
		
		function associative_multi_sort($master, ...$slaves)
		/*
		 * use only on numerical array
		 */
		{
			if (isset($this->data[$master]))
			{
				asort($this->data[$master]);
			
				foreach ($slaves as $slave)
				{
					$new_slave=array();
					$master_key=array_keys($this->data[$master]);
					foreach ($master_key as $key)
						$new_slave[]=$this->data[$slave][$key];
						
					$this->data[$slave]=$new_slave;
				}
				
				$this->data[$master]=array_values($this->data[$master]);
			}
		}
				
		static function quick_hash($data)
		/*
		 * Use quick hash for quick hashing in user interface or something else 
		 * to make array keys of string possible
		 * 
		 * Do not use on database storage or other important functions.
		 */
		{
			return hash('haval128,3', $data ?? '');
		}
		
		static function hash_nonce($data, $pepper='', $nonce=0)
		/*
		 * Returns an array of string which consisted all data nonce set to the value
		 * e.g. will append  the nonce behind salted and peppered data and add nonce, from 0 to nonce 
		 * (inclusive)
		 */
		{
			$sp_data=$data;
			
			if ($pepper!='')
				$sp_data=$sp_data.$pepper;
				
			$result=[];
			if ($nonce>0)
				for ($i=0; $i<=$nonce; $i++)
					$result[]=hash('sha3-256', $sp_data.' '.$i);
			else
				$result[]=hash('sha3-256', $sp_data.' '.$nonce);
			
			return $result;
		}
		
		static function hash($data, $pepper='', $nonce=0)
		/*
		 * Standard used hash on this dv1
		 * 
		 * Suitable for database storage and session storage
		 * 
		 * nonce is used to increases entropy of the stored data, hence increasing 
		 * the entropy of the hashed value to make it unguessable
		 * 
		 * as a rule of thumb, nonce shall be less than or equal than 10
		 * 
		 * only use nonce in data that is suspectible to be guessed, e.g. date, uniqueid of 
		 * that refer to other record hence repetitive pattern can be detected
		 * 
		 * due to how random_int worked, nonce will of 10 will have 11 output, 0 to 10 
		 * inclusive
		 */
		{
			$sp_data=$data;
			
			if ($pepper!='')
				$sp_data=$sp_data.$pepper;
			
			if ($nonce>0)
				$sp_data=$sp_data.' '.random_int(0, $nonce);
			else
				$sp_data=$sp_data.' '.$nonce;
			
			return hash('sha3-256', $sp_data);
		}
				
		static function sha512(#[\SensitiveParameter] $data)
		{
			return self::sha512iteration($data, 1000);
		}
		
		static function sha512iteration(#[\SensitiveParameter] $data, $iteration)
		{
			return self::hashiteration($data, $iteration);
		}
		
		static function random_uniqueid($length = 16)
		{
			$cstrong=true;
			
			return bin2hex(openssl_random_pseudo_bytes($length, $cstrong));
		}
		
		static function hashiteration(#[\SensitiveParameter] $data, $iteration, $algorithm='sha512')
		{
			$result=$data;
			if ($iteration>0)
			{
				for ($i=0; $i<$iteration; $i++)
				{
					$result=hash($algorithm, $result);
				}
			}
			else
			{
				$result=hash($algorithm, $result);
			}
			return $result;
		}
		
		function toShortform($display, &$to_conclude, &$output_capture)
		/*
		 * Default shortform making function for standard object
		 * 
		 * to_conclude is an empty array
		 * output_capture is an empty array
		 */
		{
			$result=array();
			foreach ($display as $key => $to_see)
			{
				$reserved=$to_see['mode'];
				$value=$this->$reserved($to_see['value'], ...$to_see['params']);
				
				if (((isset($to_see['display'])) && ($to_see['display']!='_hidden')) || (!isset($to_see['display'])))
					$result[$key]=$value;
				
				if (((isset($to_see['output_capture'])) && ($to_see['output_capture'])==true))
					$output_capture[$key][$this->uniqueid]=$value;
				
				if (($to_conclude) && (is_array($to_conclude[$reserved])))
				{
					if ($to_see['value']==$to_conclude[$reserved]['callback'])
						$to_conclude[$reserved]['values'][]=$value;
					else
						$to_conclude[$reserved]['values'][]=$this->$reserved($to_conclude[$reserved]['callback'],
								...$to_conclude[$reserved]['params']);
				}
			}
			
			return $result;
		}
		
		function toInlineHTML($display, $prefix='<td>', $suffix='</td>', &$to_conclude=array())
		{
			$result='';
			foreach ($display as $to_see)
			{
				$reserved=$to_see['mode'];
				$value=$this->$reserved($to_see['value'], ...($to_see['params'] ?? []));
				
				if ($to_see['display']!='_hidden')
					$result.=$prefix.$value.$suffix;
					
				if ((isset($to_conclude[$reserved])) && (is_array($to_conclude[$reserved])))
				{
					if ($to_see['value']==$to_conclude[$reserved]['callback'])
						$to_conclude[$reserved]['values'][]=$value;
					else
						$to_conclude[$reserved]['values'][]=$this->$reserved($to_conclude[$reserved]['callback'],
								...$to_conclude[$reserved]['params']);
				}
			}
			
			return $result;
		}
		
		static function implode($value, $glue='<br>')
		/*
		 * Alias to implode, that is with compatible ordering with with dv1 display arguments, e.g. value and glue
		 */
		{
			return implode($glue, $value);
		}
		
		static function boolean_format($value, $display=[true => 'true', false => 'false'])
		/*
		 * This will convert boolean (true or false) to other value, e.g. 'Yes' or 'No
		 */
		{
			return $display[$value] ?: null;
		}
		
		function seconds_to_hour_format($value)
		{
			return 
				$this->date($this->begin_of_day(0, 'timestamp')+$value, 'H:i');
		}
		
		static function number_format(null|float|int|String $value, $decimal=2, $unit=null, $null_zero=true)
		/*
		 * Alias to number format, but with null_zero
		 */
		{
			if (($value=='') || (is_null($value)) || (!is_numeric($value)))
			{
				if ($null_zero)
					$result=number_format(0, $decimal);
				else
					$result='';
			}
			else
			{
				$result=number_format($value, $decimal);
			}
			return $result.$unit ?: $unit;
		}
		
		static function numbers_format($values, $decimal=2, $null_zero=true, $padding=3)
		/*
		 * Format numbers in an array, and and aware of integer / float value
		 *
		 * if integer then no add decimal behind it
		 */
		{
			$result=[];
			foreach ($values as $key => $value)
			{
				if (is_integer($value))
					$result[$key]=$value;
				else
					$result[$key]=self::number_format($value, $decimal, $null_zero);
			}
			
			return $result;
		}		
		
		static function number_unformat($value, $output='float')
		/*
		 * This method will unformat number
		 */
		{
			$conv=localeconv();
			
			$d_point=$conv['decimal_point'];
			$k_sep=$conv['thousands_sep'];
			if (!$k_sep)
			{
				if ($d_point=='.')
					$k_sep=',';
				else
					$k_sep='.';
			}
			
			$value=str_replace($k_sep, '', $value);
			$value=str_replace($d_point, '.', $value);
			
			switch ($output)
			{
				case 'float':
					$result=(float) $value;
					break;
				case 'int': default:
					$result=(int) $value;
					break;
			}
			return $result;
		}
		
		static function percentage_format($value, $decimal=2, $null_zero=true)
		{
			if (($value=='') || (is_null($value)))
			{
				if ($null_zero)
					$result=number_format(0, $decimal);
				else
					$result='';
			}
			else
			{
				$result=number_format($value, $decimal);
			}
			return $result.'%';
		}
		
		function wrap_number_format($value, $wrapper, $decimal=2, $null_zero=true)
		{
			$wrap_value=$this->{$wrapper}($value);
			
			$result='';
			if (is_numeric($wrap_value))
				$result=$this->number_format($wrap_value, $decimal, $null_zero);
				
			return $result;
		}
		
		static function sum_array($array1, $array2)
		/*
		 * Used to sum two arrays with corresponding keys ahnd store it in array2
		 */
		{			
			foreach ($array1 as $key => $value)
				$array2[$key]+=$value;
			
			return $array2;
		}
		
		static function array_sum($array=[])
		/*(
		 * Alias to array_sum
		 */
		{
			return is_array($array) ? array_sum($array) : ($array ?: 0);
		}
		
		static function array_count($array=[])
		/*
		 * Alias to count
		 */
		{
			return is_array($array) ? count($array) : ($array ? 1 : 0);
		}
		
		function convert_number_format($value, $units)
		{
			return number_format($this->get_global($units['converter'])->{$units['function']}($units['from'], $units['to'], $value), 2);
		}

		function currency_format($value)
		{
			return $this->currency_output($value);
		}

		static function _echo($value)
		{
			return $value;
		}

		function first($array=[])
		{
			return $array[array_key_first($array)];
		}

		function last($array)
		{
			return $array[array_key_last($array)];
		}
		
		static function percent_diff($dividend, $divisor, $absolute=true)
		{
			if ($divisor)
			{
				$result=($dividend-$divisor)/$divisor * 100;
				
				return $absolute ? abs($result) : $result;
			}
		}
		
		function get_nearest($needle, $haystack = [], $diff_function='difference')
		/*
		 * Used to find the key nearest to haystack value
		 * 
		 * worked for integer, use the default diff_function of difference
		 * 
		 * for string, will need to rewrite a function but perhaps levenshtein
		 * or similar_text can do the job
		 */
		{
			$diff=PHP_INT_MAX;
			foreach ($haystack as $key => $value)
			{
				$check=$this->{$diff_function}($value, $needle);
				if ($diff>$check)
				{
					$result=$key;
					$diff=$check;
				}
			}
			
			return $result;
		}		
		
		static function difference($a, $b)
		/*
		 * Get the absolute difference of two values
		 */
		{
			return abs($a-$b);
		}
		
		function dynamic_foreach(&$elements)
		/*
		 * This generator function is used by attached in foreach loop where
		 * the the number of iterated elements is dynamically added within the loop.
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
						
		static function in($x, $value, $y)
		{
			return (($x<=$value) && ($value<=$y));
		}
		
		static function in_range($value, $pivot, $pct)
		/*
		 * Finds if a value in the pivot +- range.
		 * PCT is percent in whole number, e.g. 8, not 0.08
		 */
		{
			$range=$pivot*$pct/100;
			
			return self::in($pivot-$range, $value, $pivot+$range);
		}
		
		static function pipe($value)
		/*
		 * this method is used to pipe value, use with mcc and an expression as parameters
		 * however it will not delay execution, so cannot be used with reference except
		 * using it with exec which is not secure
		 * 
		 */
		{
			return $value;
		}
		
		static function var_dump(...$values)
		{
			foreach ($values as $v)
			{
				var_dump($v);
				echo("<BR><BR>");
			}
		}
		
		static function decode_namespace_class(String $class_name, ?String $namespace)
		{
			if ($class_name[0]=='\\')
				/*
				 * Global Class Only
				 */
				$result=substr($class_name, 1);
			else
				$result=$namespace ? $namespace.'\\'.$class_name : $class_name;
									
			return $result;
		}
		
		static function array_key_intersect($array1, $array2)
		/*
		 * This method will return the intersecting key of both array, with value from array 2
		 */
		{
			$result=[];
			
			foreach (array_keys($array1) as $key)
				if (isset($array2[$key]))
					$result[$key]=$array2[$key];
				
			return $result;
		}
	}
					
	class entity extends s_lib
	//entity contains the customer or supplier information or can be used to represent a person
	{
		var $uniqueid;//really unique ID obtained from random data for
		var $data;
		var SensitiveParameterValue $password;
		var SensitiveParameterValue $iv;
	
		var $mirror;
		var $preserve_mirror_no;
		var $preserve_mirror;
		
		var $id_field;
		var $name_field;
	
		var $valid;
	
		var $secondary_references;
		
		var $unique_field_values;
		var $unique_field;
		
		var $blockchain_reserved;
		var $blockchain_exempt;
		var $temp_data;
		
		/*
		 * Transaction that happened when the class is loaded and then flushed
		 * This is saved to identify blockchain data
		 * 
		 * available types 
		 * 's' => storage or overwriting, call function store
		 * 'r' => removal call function remove
		 * 'm' => merging call function merge
		 * 
		 * all transaction when transacted should give the same result
		 * since the same snapshot 
		 */
		var $transaction;
			
		static function stardate($date=null)
		{
			return $date ? date('ymd', $date ): (date('ymd'));
		}
		
		function is(String $uniqueid)
		/*
		 * This is used to check if the id_field is the same to the uniqueid 
		 */
		{
			return hash_equals($this->{$this->id_field}, $uniqueid);
		}
	
		function uniqueid()
		{
			return $this->uniqueid;
		}
		function asArray()
		{
			return $this->data;
		}
		function asJSON()
		{
			return json_encode($this->asArray());
		}
		
		function is_showing(...$variables)
		{
			return true;
		}
	
		function revert($name='')
		//if no name is present then will overwrite all the data with the mirror
		{
			if ($name)
				$this->data[$name]=$this->mirror[$name];
			else
				array_replace($this->data, $this->mirror);
		}
	
		function toString()
		{
			return json_encode($this->data);
		}
	
		function equals($a)
		{
//			return strcmp(json_encode($this->data), $a->toString())==0;
			return $a->data===$this->data;
		}
	
		function save_state($name='')
		//this function is used to save the state of an array so it will be on mirror to be
		//it is better to flush before calling save state!
		{
			if ($name)
				$this->mirror[$name]=$this->data[$name];
			else
				array_replace($this->mirror, $this->data);
		}
		
		function sort($name, $sort_function='', $sort_flags=SORT_REGULAR)
		{
			if (is_array($this->data[$name]))
			{
				if (!$sort_function)
				{
					krsort($this->data[$name]);
				}
				else
				{
					switch ($sort_function)
					{
						case 'ksort': 
							$sort_function($this->data[$name], $sort_flags);
							break;
						default:
							uksort($this->data[$name], $sort_function);
					}					
				}
			}
		}
		
		function trimData($name, $count)
		{
			if (is_array($this->data[$name]))
			{
				$this->data[$name]=array_slice($this->data[$name], -$count, $count, true); 
			}
		}
		
		function merge($name, $value)
		/*
		 * This will merge a value to a name.  
		 * 
		 * Index is disabled
		 */
		{
			$values=$this->get($name) ?? [];
			
			$values[]=$value;
			
			$this->set($name, $values);
		}
		
		function add_unique($name, $value, ...$indices)
		/*
		 * Add to an array, and make sure its unique
		 * 
		 * will return false if it is not unique, otherwise will return true
		 */
		{
			$result=false;
			
			$original_indices=$indices;
			array_pop($original_indices);
			
			$originals=$this->get($name, ...$original_indices);
			
			switch (true)
			{
				case (is_array($originals)):
					/*
					 * if originals is an array and is unique, then will store according to the index
					 */
					{
						if (array_search($value, $originals)===false)
						{
							$result=true;
							
							$this->set($name, $value, ...$indices);
						}
						break;						
					}
					
					/*
					 * If original is a value / ordinal / not array then
					 */
				case (is_null($originals)):
					{
						/*
						 * Originals is null then store according to the index and return true
						 */
						if (empty($indices))
							$this->set($name, [$value]);
						else
							$this->set($name, $value, ...$indices);
						
						$result=true;
						break;
					}
				case ($originals!=$value):
					/*
					 * If it is anything that is not equal to value, or perhaps another ordinals / value
					 * convert it to an array with whatever index and add the value to with original index
					 */
					{
						$this->set($name, [$originals], ...$original_indices);
						
						$this->set($name, $value, ...$indices);
						$result=true;
						
						break;
					}
					
			}
			
			return $result;
		}
						
		function set(string $name, $value, ...$indices)
		/*
		 * An alias to addData, all addData will be transformed to set
		 */
		{
			if ($this->get($name, ...$indices)!==$value)
				$this->store($name, $value, ...$indices);				
		}
		
		function push(string $name, $value, ...$indices)
		/*
		 * Pushing to store the value, if after the last index is not array, then it will
		 * be pushing it to the array.
		 *
		 * otherwise it will make the last vvalue to array
		 */
		{
			$values=$this->get($name, ...$indices);
			if (is_array($values))
			/*
			 * It is already array, and pushing there will simply append it to the back of the array
			 */
			{
				$values[]=$value;
				$this->set($name, $values, ...$indices);
			}
			else
			/*
			 * It is not array, and will push the value as an array at the indices
			 */
			{
				$this->set($name, [$value], ...$indices);
			}
		}

		function addData($name, $value, ...$indices)
		/*
		 * This function will check if a variable is already existed.
		 * If it is same, then it will not rewrite otherwise it will rewrite 
		 * the variable;  
		 */
		{		
			$this->set($name, $value, ...$indices);
		}
		
		function addCache($name, $value, ...$indices)
		{
			if ($this->is_temporal($name))
				$this->t_store(\date::today('timestamp'), $name, $value, ...$indices);
			else
				$this->p_store($name, $value, ...$indices);					
		}
		
		function blockchain_exempt($name)
		{
			$exempts=($this->temp_data ?? [])+($this->blockchain_exempt ?? [])+($this->blockchain_reserved ?? []);
			
			return (array_search($name, $exempts)===false);
		}
		
		function store($name, $value, ...$indices)
		{
			if ($this->is_temporal($name))
				$this->t_store(\date::today('timestamp'), $name, $value, ...$indices);
			else
				$this->p_store($name, $value, ...$indices);
			
			if (!$this->blockchain_exempt($name))
				$this->transaction[]=['t' => 's', 'n' => $name, 'v' => $value, 'i' => [...$indices]];
		}
		
		function _data_indices_valid(...$indices)
		{
			$result=true;
			foreach ($indices as $index)
			{				
				if (!((is_string($index)) || (is_numeric($index))))
				{
					$result=false;
					break;
				}
			}
			
			return $result;
		}
		
		function p_store($name, $value, ...$indices)
		/*
		 * Store value to array according to these rules
		 * If storage is array:
		 * Non indexed 
		 * String / Integer = will be on next element
		 * Array = will merge the array
		 * 
		 * To override the original value, please remove it
		 * 
		 * Indexed
		 * Any value, be it array / string, will be stored according to the index
		 * 
		 * If storage is not array
		 * Will override according to whether its indexed or not
		 * If storing an array, then storage will become an array 
		 */
		{							
			//if (($this->data[$name]) && (is_array($this->data[$name])))
			if ((isset($this->data[$name])) && (is_array($this->data[$name])))
			{
				if (empty($indices))
				/*
				 * Index is null, it will override the array with the value form caller
				 */
				{										
					if ($this->issetUnique($name))
					{
						if ($this->isUnique($name, $value))
							if (is_array($value))
								/*
								 * Append the data to array
								 */
								$this->data[$name]=$value+$this->data[$name];
							else
								/*
								 * Add to next index
								 */
								$this->data[$name][]=$value;
								 
						$this->unique_field_values[$name][$value]=1;
					}
					else
					{
						if (is_array($value))
							/*
							 * Append the input array to this value
							 */
							$this->data[$name]=$value+$this->data[$name];
						else
							/*
							 * Add to the next index
							 */
							$this->data[$name][]=$value;
					}
				}
				else
				{					
					if (!$this->_data_indices_valid(...$indices))
					{
						throw new Exception("ERROR: Index is Undefined");
					}
					
					if (!$this->_get($this->data[$name], ...$indices))//save the value on mirror
					{
						$this->mirror[$name]=[];

						$this->_set($this->mirror[$name], $value, ...$indices);
					}
					
					$this->data[$name]=$this->_set($this->data[$name], $value, ...$indices);
				}
			}
			else
			{
				if (empty($indices))
				{
					if (!isset($this->data[$name]))//save the original value on mirror
					{
						$this->mirror[$name]=$value;//changed to save null to represent it is originally null
					}
	
					$this->data[$name]=$value;					
				}
				else
				{
					if (!$this->_data_indices_valid(...$indices))
					{
						throw new Exception("ERROR: Index is Undefined");
					}
					
					//Initialising the data
					if (isset($this->data[$name]))
						$this->data[$name]=[$this->data[$name]];
					else
						$this->data[$name]=[];
					
					$this->data[$name]=$this->_set($this->data[$name], $value, ...$indices);
					
//					$this->data[$name][$index]='';
//					$this->p_store($name, $value, $index);
				}
			}			
		}
		
		function remove_cache($name, $index)
		{
			$value=$this->select($name, $index);
			if (is_array($this->data[$name]))
			{
				if ($index==='')
				{
					$this->mirror[$name]=$this->data[$name];
					unset($this->data[$name]);
				}
				else
				{
					$this->mirror[$name][$index]=$this->data[$name][$index];
					unset($this->data[$name][$index]);
				}
			}
			else
			{
				$this->mirror[$name]=$this->data[$name];
				unset($this->data[$name]);
			}
			
			return $value;
		}
				
		function remove($name, ...$indices)
		/*
		 * This is not deletion, but removal of entire branch.
		 */
		{
			if (empty($indices))
			{
				$result=$this->data[$name];
				
				unset($this->data[$name]);
				
				$this->mirror[$name]=$result;
			}
			else
				if ($result=$this::_rem($this->data[$name], ...$indices))
				{
					/*
					 * Backup to the mirror
					 */
					$this->_set($this->mirror[$name], $result, ...$indices);
					
					if (!$this->blockchain_exempt($name))
						$this->transaction[]=['t' => 'r', 'n' => $name, 'i' => $indices];
				}
				
			return $result;
			//if (($this->data[$name]) && (is_array($this->data[$name])))
/*			$value=$index ? $this->select($name, $index) : $this->select($name);
			if (isset($this->data[$name]))
			{
				if (is_array($this->data[$name]))
				{				
					if ($index===null)
					{					
						$this->mirror[$name]=$this->data[$name];
						unset($this->data[$name]);					
					}
					else
					{	
						$this->mirror[$name][$index]=$this->data[$name][$index];
						unset($this->data[$name][$index]);
					}
				}
				else
				{
					$this->mirror[$name]=$this->data[$name];
					unset($this->data[$name]);
				}
				
				if (!$this->blockchain_exempt($name))				
					$this->transaction[]=['t' => 'r', 'n' => $name, 'i' => $index];
			}
			
			return $value;*/
		}
		
		function replace_data($name, $value)
		/*
		 * Overwrite data, alias 
		 */
		{
			$this->store($name, $value);
		}
		
		function merge_data($name, $value)
		{
			if ((is_array($this->data[$name])) && (is_array($value)))
				$this->data[$name]=array_merge($this->data[$name], $value);
			
			if (!$this->blockchain_exempt($name))
				$this->transaction[]=['t' => 'm', 'n' => $name, 'v' => $value];				
		}
		
		function delete($name)
		{
			$this->set($name, '');
		}
		
		function preserve_mirror()
		{
			$this->preserve_mirror_no++;
			$this->preserve_mirror[$this->preserve_mirror_no]=$this->mirror;
		}
						
		function compact()
		{
			$this->preserve_mirror();
			
			$this->mirror=$this->data;
			$new_data=array();
			foreach ($this->data as $key => $element)
			{
				if ($element)
				{
					$new_data[$key]=$element;
				}
			}
			
			unset($this->data);
			$this->data=$new_data;
		}
				
		function getData(String|Int $name)
		/*
		 * datafunction or df_ is deprecated and scheduled for removal!
		 */
		{
			if ((method_exists($this, $name)) && (substr($name, 0, 3)=='df_'))
				$result=$this->$name();
			else
				$result=$this->data[$name] ?? null;
			
			return $result;
		}
		
		function select(String|Int $name, String|Int...$indices)
		/*
		 * an alias for get, but not strongly typed
		 * because I might forgot to use select and integer
		 */
		{			
			return $this->get($name, ...$indices);
		}
		
		function get(String|Int $name, String|Int ...$indices)
		{
			return $this->retrieve($name, ...$indices);
		}
	
		function retrieve(String|Int $name, String|Int ...$indices)
		/*
		 * Select will be renamed to get
		 */
		{
			if ((isset($this->data[$name])) && (is_array($this->data[$name])))
			{
				if (!empty($indices))
				{
					$result=$this->_get($this->data[$name], ...$indices);
				}
				else
				{
					$result=$this->getData($name);//return the whole array
				}
			}
			else
			{
				if (!empty($indices))
				{
					$result=null;
				}
				else
				{
					$result=$this->getData($name);
				}
			}
			
			return $result;
		}
						
		function encrypt($input, #[\SensitiveParameter] $password=null, #[\SensitiveParameter] $iv=null)
		{
			return parent::encrypt($input, $password ?? $this->get_password(), $iv ?? $this->get_iv());
		}
		
		function decrypt($input, #[\SensitiveParameter] $password=null, #[\SensitiveParameter] $iv=null)
		{
			return parent::decrypt($input, $password ?? $this->get_password(), $iv ?? $this->get_iv());
		}
			
		function encrypt_data($data)
		/*
		 * This method will prepare the data to increase its integrity and 
		 * This method will add random value and checksum to ensure its integrity
		 * on encryption process 
		 */
		{
			$data['_cs_algo']=1;
			
			$algo=self::cs_algo($data['_cs_algo']);
			if ($algo['function']=='hash')
			{
				$data['_randval']=$this->{$this->id_field};//add initialisation vector
				$data['_checksum']=hash($algo['algo'], json_encode($data));
			}
			else
			{
				$data[$this->id_field]=$this->{$this->id_field};
//				$data['_randval']=$this->random_uniqueid(8);
//				$data['_checksum']=hash_hmac($algo['algo'], json_encode($data), $data['_randval']);
				$data=self::add_data_integrity($data['_cs_algo'], $data);
			}			
			
			return $this->encrypt(json_encode($data));//encrypt it
		}
	
		function do_decrypt($input)
		/*
		 * Obsolete, and replaced by row_decoder
		 */
		{
			$string=$this->decrypt($input);
			$data=json_decode($string, true);
	
			$checksum=$data['_checksum'];
			if (!$checksum) $checksum='';
			
			array_pop($data);
			
			$algo=self::cs_algo($data['_cs_algo']);
			if ($algo['function']=='hash')
				$rehash_checksum=hash($algo['algo'], json_encode($data));
			else 
				$rehash_checksum=hash_hmac($algo['algo'], json_encode($data), $data['_randval']);
			
			if ($data['_randval'])
				array_pop($data);
			if ($data['_cs_algo'])
				array_pop($data);
					
			if (hash_equals($rehash_checksum, $checksum))
				return $data;
			else
				return array();
		}
	
		function integrity_OK($data)
		/*
		 * Checking if data integrity is good based on checksum and the database primary key 
		 * with the key in the storage
		 */
		{
			$checksum='';
			$rehash_checksum='';
			$integrity_failed=false;
			
			if (!is_null($data))
			{
				$checksum=$data['_checksum'];
				$rehash_checksum=null;
				if ($checksum)
				{
					array_pop($data); //checksum

					$algo=self::cs_algo($data['_cs_algo'] ?? 0);
					if ($algo['function']=='hash')
						$rehash_checksum=hash($algo['algo'], json_encode($data));
					else
					{
//						var_dump($this->{$this->id_field}); echo("<BR>");echo("<BR>");
//						var_dump($this->data); echo("<BR>");echo("<BR>");echo("<BR>");
						
						$rehash_checksum=hash_hmac($algo['algo'], json_encode($data), $data['_randval']);
					}
											
					if (isset($data['_randval']))
						array_pop($data);
					
					if (isset($data['_cs_algo']))
						array_pop($data);
						//var_dump($data[$this->id_field], $this->{$this->id_field});
					if ((isset($data[$this->id_field])) && (!hash_equals((String) $data[$this->id_field], $this->{$this->id_field})))
					/*
					 * id_field on data row must match the id_field on database primary key to prevent the 
					 * data switched out
					 */
					{	
						$integrity_failed=true;
//						$this->uniqueid=$data[$this->id_field] ?? $this->uniqueid;
					}
				}
				else
				{
					/*
					 * Old integrity method
					 */
					$checksum=$data['checksum'];
					if ($checksum)
					{
						array_pop($data);
						$rehash_checksum=hash("sha256", json_encode($data));
					}
				}
												
			}
			
			if (!$checksum) $checksum='';
			if (!$rehash_checksum) $rehash_checksum='';
			
			return ['data' => $data, 'result' => $integrity_failed ? false : (hash_equals($checksum, $rehash_checksum))];
		}
		
		function set_iv(#[\SensitiveParameter] $iv)
		{
			$this->iv=$iv;
		}
		
		function set_password(#[\SensitiveParameter] $password)
		{
			$this->password=$password;
		}
		
		function get_iv() : SensitiveParameterValue
		{
			return isset($this->iv) ? $this->iv : enc_iv;
		}
		
		function get_password() : SensitiveParameterValue
		{
			return isset($this->password) ? $this->password : enc_passwd;
		}
		
		function __construct($data = array(), $uniqueid = '')
		{			
			$this->preserve_mirror_no=0;
			$this->preserve_mirror=array();			
			$this->mirror=array();
	
			$this->temp_data=[];
			$this->blockchain_reserved=['_archive', '_antispam', '_deleted'];
			$this->blockchain_exempt=[];
			
			$this->input($data);
	
			$this->id_field='uniqueid';
			$this->name_field='name';
	
			$this->unique_field=[];
			$this->unique_field_values=[];
			
			$this->secondary_references=array();
			
			$this->transaction=[];				
	
			$this->uniqueid=$uniqueid ?: $this->select($this->id_field) ?? $this->random_uniqueid();
		}
		
		function input($data, ...$fields)
		{
			if (is_array($data))
			{
				$this->data=$data;
				$this->mirror=$data;
				$this->valid=true;
			}
			else
			{				
				$data=json_decode($this->decrypt($data), true);				
				$integrity=$this->integrity_OK($data);
				
				$this->valid=$integrity['result'];
				$this->data=$integrity['data'];
				$this->mirror=$integrity['data'];
			}
		}
	
		function setField($id_field, $name_field)
		{
			$this->id_field=$id_field;
			$this->name_field=$name_field;
		}
	
		function getIndex()
		{
			if ($this->id_field=='uniqueid')
				return $this->uniqueid;
			else
				return $this->get($this->id_field) ?? $this->{$this->id_field};
		}
	
		function index()
		{
			return $this->getIndex();
		}
	
		function add_secondary_reference($field)
		{
			$this->secondary_references[]=$field;
		}
		function secondary_reference()
		{
			return $this->secondary_references;
		}
	
		function getName()
		{
			return $this->data[$this->name_field];
		}
	
		function recursive_array_diff_assoc($a, $b)
		{
			$result=array_diff_assoc($a, $b);
			foreach ($a as $key => $check)
			{
				if (is_array($check))
				{
					if ($b[$key])
						$result[$key]=$this->recursive_array_diff_assoc($check, $b[$key]);
					else
						$result[$key]=$check;
				}
			}
			return $result;
		}
	
		function getUpdates()
		{
			$difference=array_diff_assoc($this->data, $this->mirror);
			foreach ($this->data as $key => $check)
			{
				if (is_array($check))
				{
					if ($check!=$this->mirror[$key])
						$difference[$key]=$check;
				}
			}
				
			return $difference;
			//return $this->recursive_array_diff_assoc($this->data, $this->mirror);
		}
	
		function append($newdata)
		{
			$this->data=array_merge($this->data, $newdata);
		}
		
		function field_exists($field)
		{
			return array_key_exists($field, $this->data);
		}
		
		function update(...$fields)
		//input array of fields for each field
		{
			if (!empty($fields))
			{
				foreach ($fields as $field)
				{
					$this->data[$field[0]]=$field[1];
				}
			}
		}
		
		function select_count($name)
		/*
		 * Return if 0 if it is not set
		 * Return count of array if it is set
		 * Return null if it is just a variable;
		 */
		{
			$result=null;
			if (!isset($this->data[$name]))
				$result=0;
			else				
				if (is_array($this->data[$name]))
					$result=count($this->data[$name]);
				
			return $result;
		}
		
		function makeArray($name, $unique=true, ...$values)
		/*
		 * When unique is set to true, then only receive unique value to avoid repeatitions
		 */
		{
			if (empty($values)) $values=array();
			
			if (!isset($this->data[$name]))
				$this->addData($name, $values);
			else
				foreach ($values as $value)
					$this->addData($name, $value);
				
			if ($unique)
				$this->setUnique($name);
		}	
		
		function setUnique($name)
		/*the value on this field will always be unique*/
		{
			$this->unique_field[$name]=true;
			$this->unique_field_values[$name]=[];
		}
		
		function issetUnique($name)
		{
			return $this->unique_field[$name] ?? false;
		}
		
		function isUnique($name, $value)
		/*
		 * This is used to check if the name and value is unique
		 */
		{
			$result=true;
			
			if (isset($this->unique_field_values[$name]))
				if (isset($this->unique_field_values[$name][$value]))
					if ($this->unique_field_values[$name][$value]==1)
						$result=false;
					
			return $result;
		}
		
		function sortby(...$function_params)
		{
			$result=[];
			foreach ($function_params as ['f' => $function, 'p' => $params])
			{
				$result[]=$this->{$function}(...($params ?? []));
			}
			
			return implode(' ', $result);
		}
		
		var $temporal_values;
		function make_temporal(...$fields)
		{
			foreach ($fields as $field)
				$this->temporal_values[$field]=true;
		}
		
		function is_temporal($field)
		{
			return $this->temporal_values[$field] ?? false;
		}
				
		function find_epoch($time, $field)
		{
			$t_epoch='_t_epoch_'.$field;
			$epochs=$this->select($t_epoch) ?? [];
			
			$result['status']=0;
			$result['epoch']=array_key_last($epochs);
			
			if ($epochs)
			{
				krsort($epochs, SORT_NUMERIC);
				$next_epoch=null;
				
				foreach ($epochs as $epoch => ['b' => $begin, 'e' => $end])
				{
					if (($begin<=$time) && ($time<$end))
					{
						if ($next_epoch)
							$result['next_epoch']=$next_epoch;
						$result['epoch']=$epoch;
						$result['status']=($begin==$time) ? 1 : 2;
						
						break;
					}
					
					$next_epoch=$epoch;
				}
			}
			
			return $result;
		}
				
		function is_on_epoch($time, $epoch, $field)
		{
			$result=0;
			$t_epoch='_t_epoch_'.$field;
			
			if (($this->select($t_epoch, 'b')<=$time) && ($time<$this->select($t_epoch, 'e')))
			{
				$result=($this->select($t_epoch, 'b')==$time) ? 1 : 2;
			}
			
			return $result;
		}
		
		function t_select($time, $field, ...$indices)
		/*
		 * Begin is the main timeline
		 * 
		 * returns the latest value if timeline is not existed, i.e. works as select   
		 */
		{
			$result=null;
			
			if (($this->is_temporal($field)) && ($time))
			{
				/*
				 * differential search
				 */								
				$t_epoch='_t_epoch_'.$field;
				$t_data='_t_data_'.$field;
				$epochs=$this->select($t_epoch) ?? [];
				krsort($epochs, SORT_NUMERIC);

				$d_field=null;					
				foreach ($epochs as $epoch => ['e' => $end])
					if ($time<$end)
					/*
					 * If the data is in the epoch
					 */
					{												
						$d_field=$this->select($t_data)[$epoch];
						
						if ($r=$this->_get($d_field, $field, ...$indices))
							$result=$r;												
					}
					else
					/*
					 * No point in continuing because its the end of time already
					 */
						break;							

				if (!$result)
					/*(
					 * If result is not found, then find the latest data anyway
					 * This might be funny...
					 */
					$result=$this->get($field, ...$indices);
					
							
			}
			else 
				$result=$this->get($field, ...$indices);
		
			return $result;
		}
				
		function t_store($time, $field, $value, ...$indices)
		/*
		 * This method is used to store temporal data
		 */
		{
			if ($this->get($field, ...$indices))
			{
				$t_epoch='_t_epoch_'.$field;
				$t_data='_t_data_'.$field;
				
				$e=$this->find_epoch($time, $field);
				switch ($e['status'])
				{
					case 0:
					/* create new epoch */
/*						if (empty($indices))
							$c_data=$this->get($field);
						else*/
						$c_data=$this->get($field, ...$indices);
						
						/*
						 * Store null value to preserve originality
						 */
						$prev_epoch=(float) $e['epoch'];
						$epoch=(float) $e['epoch']+1;
						
						if ($e['epoch'])
							$begin_time=$this->data[$t_epoch][$prev_epoch]['e'];
						else
							$begin_time=PHP_INT_MIN;
						
						$this->data[$t_epoch]["$epoch"]['b']=$begin_time;
						$this->data[$t_epoch]["$epoch"]['e']=$begin_time==$time ? $time+1 : $time;
						
/*						if (empty($indices))
							$this->data[$t_data]["$epoch"]=$c_data;
						else*/
						$this->data[$t_data]["$epoch"]=$this->_set($this->data[$t_data]["$epoch"], $c_data, $field, ...$indices);
						
						$this->p_store($field, $value, ...$indices);
						break;
					case 1:
						/* 
						 * join or override data/index 
						 * use really caerfully  or this will cause anomaly
						 * on the data
						 */
/*						if ($index==='')
							$c_data=$this->select($field);
						else
							$c_data=$this->select($field, $index);*/
						$c_data=$this->get($field, ...$indices);
						
						$epoch=$e['epoch'];
							
/*						if (empty($indices))
							$this->data[$t_data]["$epoch"]=$value;
						else*/ 
						$this->data[$t_data]["$epoch"]=$this->_set($this->data[$t_data]["$epoch"], $c_data, $field, ...$indices);
							
						$this->p_store($field, $value, ...$indices);
							
						break;
					case 2:
						/* 
						 * split epoch 
						 * use really carefully or this will cause anomaly on the data
						 * */								
						$next_epoch=(float) $e['next_epoch'];
						$epoch=(float) $e['epoch'];
						$new_epoch=($next_epoch+$epoch) / 2;
						
						$this->data[$t_epoch]["$epoch"]['e']=$time;
						$this->data[$t_epoch]["$new_epoch"]['b']=$time;
						$this->data[$t_epoch]["$new_epoch"]['e']=$this->data[$t_epoch]["$next_epoch"]['b'];
						
/*						if (empty($indices))
							$this->data[$t_data]["$new_epoch"]=$c_data;
						else*/
							$this->data[$t_data]["$new_epoch"]=$this->_set($this->data[$t_data]["$new_epoch"], $value, $field, ...$indices);;
						break;
				}
			}
			else			
				$this->p_store($field, $value, ...$indices);
		}		
		
		function toCSV($display, $format_display=[], $no_csv=[])
		/*
		 * standarised way to get csv for an entity
		 * display is smart list display: it will not format value for CSV
		 * $no_csv is exception
		 */
		{
			$result=array();
			foreach ($display as $key => $to_see)
			{
				$reserved=$to_see['mode'];
				$value=$this->$reserved($to_see['value'], ...$to_see['params']);
				
				if ($to_see['display']!='_hidden')
				{
					if (array_key_exists($key, $format_display))
					{
						$f_function=$format_display[$key]['function'];
						$f_params=$format_display[$key]['params'];
						
						if (method_exists($this, $f_function))
							$result[$key]=$this->$f_function($value, ...$f_params);
						else
							if (function_exists($f_function))
								$result[$key]=$f_function($value, ...$f_params);
							else
								$result[$key]=$value;										
					}
					else
						$result[$key]=$value;
				}
			}
			
			return $result;
		}
		
		static function blank()
		{
			return entity::pipe("&nbsp"); 
		}
		
		function format_bool($bool, $true, $false)
		{			
			return $bool ? $true : $false;
		}
		
		function data_size($type='raw')
		{
			switch ($type)
			{
				case 'xxx':
					if (strlen(json_encode($this->data))<10000)
						$size=json_encode($this->raw_data);
					else 
						$size=strlen(json_encode($this->data));
					break;
				case 'active':
					$size=strlen(json_encode($this->data));
					break;
				default: case 'raw':
					$size=strlen(json_encode($this->raw_data));
					break;
			}
				
			return $size;
		}
		
		function add_signature($name, $credentials, $seal)
		{
			$this->addData($name, $credentials->username, 'auth');
			$this->addData($name, $seal, 'seal');
			$this->addData($name, \date::today('timestamp'), 'date');			
		}
		
		function value_not_null($field)
		{
			return !(is_null($this->select($field)));
		}
		
		function copy(record $source, ...$names)
		/*
		 * Copy from source to this record
		 */
		{
			foreach ($names as $name)
				$this->set($name, $source->get($name));
		}
		
		function E($name, ...$values)
		/*
		 * This will return if a name is an element of a values
		 */
		{
			return array_search($this->get($name), $values)!==false;
		}
		
		private advanced_list $_owner;
		
		function update_iv()
		{
			if (isset($this->_owner))
				switch ($this->_owner->get('list_version'))
				{
					case dbm::dbm_record_1:
						{
							$iv_len=12; //iv length is character for aes-128-cbc.
							$this->iv=new SensitiveParameterValue(substr(hash_hmac('sha3-256', $this->index(), enc_iv->getValue()), 0, $iv_len));
							
							break;
						}
					case dbm::dbm_record_legacy: default :
						{
							/*
							 * Do nothing, change nothing to force IV usign default iv
							 */
							
							break;
						}
				}			
		}
		
		function set_owner(advanced_list $list)
		{
			$this->_owner=$list;			
//			$list->get_owner()->db_list_get_version($list_name);
		}
		
		function get_owner(): advanced_list
		{
			return $this->_owner;
		}		
	}
		
	class simple_list extends s_lib implements Iterator
	/*
	 * List with basic function 
	 */
	{
		var $elements;
		var $credentials;
		var $secure_db;
		var $readonly_db;
		var $primaryTable;		
		
		var $cache_condition;
		
		var $count;
		
		/*
		 * Later move to data management trait once the DMT of 
		 * has been moved
		 */
		
		var $data;
		
		public function rewind(): void
		{
			reset($this->elements);
		}
		
		public function current(): mixed
		{
			$var = current($this->elements);
			return $var;
		}
		
		public function key(): mixed
		{
			$var = key($this->elements);
			return $var;
		}
		
		public function next(): void
		{
			next($this->elements);
//			return $var;
		}
		
		public function valid(): bool
		{
			$key = key($this->elements);
			$var = ($key !== NULL && $key !== FALSE);
			return $var;
		}
		
		function execute_element_function($index, $function_name, ...$parameters)
		{
			$result='';
			if ($element=$this->db_select($index))
			{				
				if (method_exists($element, $function_name))
				{
					$result=$element->{$function_name}(...$parameters);
				}
			}
			
			return $result;
		}		
		
		function clone(&$new_list)
		{
			if ($new_list instanceof simple_list)
			{
				$this->elements=$new_list->elements;
			}
		}
		
		function group_count($check_function, ...$params)
		/*
		 * do for one check function and group the result 
		 * 
		 */
		{
			$result=[];
			
			foreach ($this->elements as $element)
			{
				$return=$element->{$check_function}(...$params);

				if (!is_array($return))
					$return=[$return];
				
				foreach ($return as $key)
					$result[$key]++;				
			}
				
			return $result;				
		}
		
		function count(...$check_functions)
		/*
		 * multiple array each like this 
		 * ['function' => 'has_finishing_process', 'params' => []]
		 */
		{
			$result=0;
			
			if (!empty($check_functions))
			{				
				foreach ($this->elements as $element)
				{
					$check=true;
					foreach ($check_functions as $check_function)
					{						
						if (!$element->{$check_function[f]}(...$check_function[p]))
						{
							$check=false;
							break;
						}
					}
					
					if ($check)
						$result++;
				}
			}
			else
				$result=count($this->elements);
			
			return $result;
		}
		
		function is_empty()
		{
			return empty($this->elements);
		}
		
		function empty()
		{
			$this->elements=[];
		}
		
		final function set($name, $value, ...$indices)
		/*
		 * Setting list setting 
		 */
		{
			if (empty($indices))
				$this->data[$name]=$value;
			else
				$this->data[$name]=$this->_set($this->data[$name], $value, ...$indices);
			
//			$this->data[$name]=$value;
		}
		
		final function get($name, ...$indices)
		/*
		 * This will get list setting
		 */
		{
			if (empty($indices))
				$result=$this->data[$name];
			else
				$result=$this->_get($this->data[$name], ...$indices);
			
			return $result;
//			return $this->data[$name];
		}
		
		function first($array=[])
		/*
		 * Do not use on array!
		 */
		{
			return parent::first($this->elements);
		}
		
		private extensions | components $_owner;
		
		function set_owner(extensions | components $owner)
		{			
			$this->_owner=$owner;
		}
		
		function get_owner(): extensions | components
		{
			return $this->_owner;			
		}
	}
	
	class record extends entity
	/* 
	 * record contains every entity that is recorded on the system.implements 
	 * 
	 * this class actually has 3 layer
	 * 1. Data layer -> the actual data that is select, addData    
	 * 2. Encoded data ->  
	 *  	a. raw_data is the sequence of data complete with lock and meta data information
	 *  	b. enabling of differential data can be done
	 * 3. Encrypted data -> data is encrypted and ready for storage in database
	 * 
	 * Encrypted data is on PHP level when use in different Database Server, the traffic
	 * flows encrypted hence can't be intercepted
	 */
	{
		use \lib_html;
		use \numerator;
		
		var $secure_db;
		var $readonly_db;
		var $primaryTable;
		var $exists;
		var $active_credential;
		var $credentials;
		var $db_dataholder;
				
		/*
		 * raw_data is hard data, that is data about to be written to database 
		 */
		var $raw_data;
		
		/*
		 * Deferred load data is the data direct from the database 
		 */
		var $_deferred_load_data;
		var $_deferred_load;
		
		/*
		 * temp_data is not temporal data, temp data is not saved and will be
		 * deleted during update or flush
		 * 
		 * temp_data never stayed on blockchain
		 */
		var $temp_data;
		
		/*
		 * Blockchain reserved is data that is reserved such as _delete, _archieve etc 
		 * Blockchain exempt is data that its change is exempted from blockchain in 
		 * particular class
		 */
		var $blockchain_reserved;
		var $blockchain_exempt;
		var $blockchain;
		var $date_field;
		
		var $indices;
		var $stored_indices;
		/*
		 * Sequence number when this is on list, to be filled by list
		 */		
		var $element_variables;		
		function set_element_variables($element_variables)
		/*
		 * This is used to carry element variables that should not be changed such as
		 * the one used by racking system
		 */
		{
			$this->element_variables=$element_variables;			
		}
				
		function mark_temp_data(...$values)
		{
			$this->temp_data=[...$this->temp_data, ...$values];
		}
		
		/*
		 * this function is used to set uniqueid
		 */
		static function make_uniqueid($hashID)
		{
			return hash('sha256', $hashID);
		}
		
		function set_uniqueid(...$hash)
		/*
		 * Set human readable uniqueid to table uniqueid  
		 */
		{		    
//			$this->uniqueid=$this->make_uniqueid($hash[0]);
			
/*			if ($this->id_field)
				$this->{$this->id_field}=$this->uniqueid;
			
			$this->addData($this->id_field, $this->uniqueid);*/

			$uniqueid=$this->make_uniqueid($hash[0]);
									
			return $this->uniqueid($uniqueid);
		}
		
		/*
		 * This function is used to set and get unique id depending on the parameter
		 * it might not work on two uniqueid field recorded_entity 
		 * please use only on new code only
		 */
		function uniqueid($new_id='')
		{
			if ((isset($new_id) && (''!==$new_id)) && ($new_id!=$this->{$this->id_field}))
			{
				$this->{$this->id_field}=$new_id;
				$this->p_store($this->id_field, $new_id);
				
				$this->update_iv();
			}
			
			return $this->{$this->id_field};
		}
		
		function conclude()
		{
			return (($this->valid) && ($this->exists));
		}
		
		function row_decoder($row_data)
		/*
		 * $row_data is encrypted raw data from database
		 */
		{
			$data=json_decode($this->decrypt($row_data), true);
			$integrity=$this->integrity_OK($data);
			
			$this->raw_data=$integrity['data'];
			if (isset($this->raw_data['meta']))
			{
				switch ($this->raw_data['meta']['version'] ?? 0)
				{
					case 2:
						/*
						 * c_data is soft data, from data variable
						 */
						$c_data=$this->raw_data['data'];
						break;
					case 1:
						/*
						 * version 1 is non differential data
						 * 
						 * c_data is the soft data, not raw data
						 */
						$seq_id=array_pop($data['meta']['seq']); //last sequence of data
						
						/*
						 * Backup the algo before update to version 2
						 */
						$this->raw_data['meta'][$seq_id]['algo']=$this->raw_data['meta']['algo'];
						$c_data=$this->raw_data[$seq_id];
						break;
					default:
						if (isset($this->raw_data['meta']))						
							$c_data=$this->raw_data['data'];
						else
							$c_data=$data;
						break;
				}
			}
			else
			{
				$c_data=$data;
			}
			
			$this->valid=$integrity['result'];
			$this->data=$c_data;
			
			$this->mirror=$integrity['data'];
			
			return $integrity['result'];
		}
	
		function get_sequence_contents($seq_id)
		{
			return $this->raw_data[$seq_id];
		}
		
		function blockchain_last($seq_id)
		{
			switch ($this->raw_data['meta']['version'] ?? 0)
			{
				case 2: case 1:
					$result=hash_equals($this->raw_data['meta'][$seq_id]['uid'] ?? '', $this->raw_data['meta'][$seq_id]['seq'] ?? '');
					break;
				default:
					$result=true;
					break;
			}
			
			return $result;
		}
		
		function blockchain_destroy()
		/*
		 * destroys the blockchain that has grown too large on this class
		 * should never be used
		 */
		{
			$this->raw_data=$this->data;

			$this->update();
		}
		
		function valid_sequence($seq_id)
		{
			$algo=$this->raw_data['meta'][$seq_id]['algo'] ?? $this->get_global('settings')->select('blockchain', 'algo');
			
			if ($this->blockchain_last($seq_id))
			{
				/*
				 * Check against self
				 */
				$data=$this->raw_data[$seq_id];
				$data['seq_id']=$seq_id;
				
				$hash=$this->credentials->hash_blockchain($data, $this->raw_data['meta'][$seq_id]['nonce'], $algo);
			}
			else
			{
				/*
				 * Check previous record for its data, and assume its always valid
				 */
				$re=new record($this->credentials, $this->credentials->primary(), $this->secure_db,
					$this->readonly_db, [], $this->raw_data['meta'][$seq_id]['table'], '', $this->raw_data['meta'][$seq_id]['id_field']);
				$re->load($this->raw_data['meta'][$seq_id]['uid']);
				
				$this->prev_block=$re;
				
				$data=$this->raw_data[$seq_id];
				// get hash from previous data and append to this data
				$data['seq_id']=$re->raw_data['meta'][$this->raw_data['meta'][$seq_id]['seq']]['hash'];
				
				// make new hash to confirm the integrity of this data
				
				$hash=$this->credentials->hash_blockchain($data, $this->raw_data['meta'][$seq_id]['nonce'], $algo);				
			}
//			var_dump($this->raw_data['meta'][$sequence]);echo("<BR>");
			$result=hash_equals($hash, $this->raw_data['meta'][$seq_id]['hash'] ?? '');
			
			return $result;
		}
		
		function valid_record()
		/*
		 * This method is used to check if all the updates in the record is valid, not just one sequence
		 */
		{
			$result=$this->valid;
			if ($result)
				switch ($this->raw_data['meta']['version'] ?? 0)
				{											
					case 1: case 2:
						foreach ($this->raw_data['meta']['seq'] as $seq_id)
						{ 
							if (!($result=$this->valid_sequence($seq_id)))
								break;
						}
						break;
					default:
						$result=$this->valid;
						break;
				}		
			
			return $result;
		}
		
		function row_encoder($data, $credentials)
		/*
		 * $data input is always soft data, never input raw data here!
		 * 
		 * algo is now unrelated to verrsion number and can change whenever needed
		 */
		{			
			$to_encrypt=[];
			switch ($this->raw_data['meta']['version'] ?? 0)
			{
				case 2:
					/*
					 * Version 2 is transactional update to save space 
					 * 
					 * In case version 3 is available in the future,
					 * then upgrade to new version, just like case 1
					 * 					 *
					 * Uptodate data is stored in 'data' subvariable, however
					 * sequence transaction
					 */
					$to_encrypt=$this->raw_data;
					break;
				case 1: 
					/*
					 * Version 1 updates to version 2 
					 */
					$to_encrypt=$this->raw_data;					
															
					$to_encrypt['meta']=['version' => 2];
					break;
				default:
					/*
					 * Non blockchain, update to version direclty 2
					 *
					 * algo is used to track the previous algorithm used
					 */
					$algo=$this->get_global('settings')->select('blockchain', 'algo');
					
					if ($this->blockchain==1)
						/*
						 * If blockchain is enabled, upgrade to version 2 directly
						 */
						$to_encrypt['meta']=['version' => 2];
					else
						$to_encrypt['meta']=['version' => 0];
					
					break;
			}
		
			$algo=$this->get_global('settings')->select('blockchain', 'algo');
			
			/*
			 * Make core data updates first
			 */
			$to_encrypt['data']=$data;
			
			if ((!empty($this->transaction)) && ($this->blockchain==1))
			/*
			 * For transaction that has happened, and blockchain is enabled for this class
			 */
			{
				$updates=['trx' => $this->transaction];
				
				$bc_info=$credentials->get_blockchain_id($this->select('uniqueid'), 
					$this->primaryTable, $this->id_field, $updates);
				
				/*
				 * Store the actual uptodate data here
				 */
	//			var_dump($to_encrypt['meta']);
				/*
				 * store the sequence ID and updates
				 */
				$seq_id=$this->quick_hash($bc_info['id']);
				$to_encrypt[$seq_id]=$updates;
				$to_encrypt['meta']['seq'][]=$seq_id;
				
				/*
				 * this values are used to address previous data on blockchain
				 */
				$to_encrypt['meta'][$seq_id]['table']=$bc_info['table'];
				$to_encrypt['meta'][$seq_id]['id_field']=$bc_info['id_field'];
				$to_encrypt['meta'][$seq_id]['uid']=$bc_info['uid'];
				$to_encrypt['meta'][$seq_id]['seq']=$bc_info['seq'];
				
				/*
				 * Record algorithm for this iteration
				 */
				$to_encrypt['meta'][$seq_id]['algo']=$algo;
							
				/*
				 * this values are used for next iteration 
				 */
	//			var_dump($bc_info['id']);
				$to_encrypt['meta'][$seq_id]['nonce']=$bc_info['nonce'];
				$to_encrypt['meta'][$seq_id]['hash']=$bc_info['id'];
	//			var_dump(array_keys($to_encrypt['meta']));
				
				$credentials->store_blockchain_info($this->primaryTable, $this->{$this->id_field}, 
					$seq_id, $this->id_field);
			}

			return ['raw' => $to_encrypt, 'encrypted' => $this->encrypt_data($to_encrypt)];
		}
		
		final function set(string $name, $value, ...$indices)
		{
			if ($this->_is_deferred())
				$this->_activate();
			
			parent::set($name, $value, ...$indices);
		}
		
		final function get($name, ...$indices)
		{
			if ($this->_is_deferred())
				$this->_activate();
//			if ($name=='row_name') var_dump($this->data['uniqueid']);
			return parent::get($name, ...$indices);
		}
		
		function _activate()
		/*
		 * This will activate the data by loading it using standard load function
		 * and remove deffered_load_data
		 */
		{			
			$this->load($this->{$this->id_field}, $this->_deferred_load_data);
		}
		
		function _deferred_load($uniqueid, $data)
		/*
		 * This will store deferred load data only, not verification
		 */
		{
			$this->{$this->id_field}=$uniqueid;
			$this->p_store($this->id_field, $uniqueid);
			
			$this->_deferred_load_data=$data;
			$this->_deferred_load=1;			
			
			foreach (array_keys($this->indices) as $key)
			{
				if ((isset($this->indices[$key]['db_index'])) && ($this->indices[$key]['db_index']))
				/*
				 * If it is a database index then make the hash of it
				 */
				{
					$h_key=$this->hash($key, $this->indices[$key]['pepper']);
				
					if (isset($data[$h_key]))
						$this->stored_indices[$key]=$data[$h_key];
				}
			}
		}
		
		function _is_deferred()
		{
			return $this->_deferred_load==1;
		}

		function load($uniqueid='', $data=array())
		/*uniqueid and data will override*/
		{
			if ((null===$uniqueid) || (''===$uniqueid))
				$uniqueid=$this->secure_db->SQLSafeString($this->{$this->id_field});

			$row=$data;
			if (empty($data))
			{
				$query="SELECT * FROM ".$this->primaryTable." WHERE $this->id_field='$uniqueid'";
					
				$this->secure_db->query($query);
				if ($this->secure_db->num_rows()>0)
				{
					$row=$this->secure_db->next_result();
				}
			}
//			if ($uniqueid=='linsony') var_dump($this->iv->getValue());
			if (!empty($row))
			{
				$this->exists=true;
//				$this->valid=true;
				
				if (($this->exists) && ($this->valid))
					$this->uniqueid($uniqueid);
									
				if ($row['data'])
					$this->valid=$this->row_decoder($row['data']);				
			}
			else
			{				
				$this->exists=false;
				$this->valid=false;
			}
							
			if ($this->_is_deferred())
			{
				$this->_deferred_load=0;
				
				$this->_deferred_load_data='';				
			}
			else
			{
				foreach (array_keys($this->indices) as $key)
				{
					$h_key=$this->hash($key, isset($this->indices[$key]['pepper']) ? $this->indices[$key]['pepper'] : '');
					
					if (isset($row[$h_key]))
					{
						$this->stored_indices[$key]=$row[$h_key];						
					}
				}
			}
		}
		
		function make_flush_query($uniqueid, $data)
		{
			$flusher=[$this->db_dataholder => $data, ...$this->db_indices_scrambler()];
			
			$query=$this->secure_db->make_flush_query($this->primaryTable, $this->id_field, $uniqueid, $flusher);
			
			return $query;
		}
		
		function flush($debug=false)
		{
			$result=false;
			
			if ($this->is_archive()=='start')
				$query_function="archive_query";
			else
				$query_function="query";
				
//			if ($this->credentials->check($this->active_credential)) //Turn this on for more strict checking
			{
				foreach ($this->temp_data as $temp_data)
					$this->remove($temp_data);
					
				$uniqueid=$this->secure_db->SQLSafeString($this->{$this->id_field});
				
				$d=$this->row_encoder($this->data, $this->credentials);
				$data=$this->secure_db->SQLSafeString($d['encrypted']);
				
//				var_dump($this->make_flush_query($uniqueid, $data));
				$query=$this->make_flush_query($uniqueid, $data);
//				$query="INSERT IGNORE INTO ".$this->primaryTable." ($this->id_field, $this->db_dataholder) VALUES ('$uniqueid', '$data')";
//				echo("<BR><BR>"); var_dump($query);
				if ($debug)
				{
//					var_dump($query);
//					echo("<BR><BR>");
//					var_dump(json_last_error(), $this->credentials->valid_input_session());
				}
				else if ((json_last_error()==JSON_ERROR_NONE) && ($this->credentials->valid_input_session()))
				{						
					$this->secure_db->{$query_function}($query);					
					$this->credentials->shuffle_input_session();

					$this->raw_data=$d['raw'];
					
					$this->transaction=[];
					
					$result=true;
				}
				
				$this->exists=true;
			}
			
			return $result;
		}
		
		function db_indices_scrambler()
		{
			$result=[];
			
			foreach ($this->db_indices_value() as $key => $value)
				$result[$this->hash($key, $this->indices[$key]['pepper'])]=
					$this->hash($value, $this->indices[$key]['pepper'], $this->indices[$key]['nonce']);
			
			return $result;
		}
		
		function make_update_query($uniqueid, $data)
		{			
			$updatee=[$this->db_dataholder => $data, ...$this->db_indices_scrambler()];
			
			$query=$this->secure_db->make_update_query($this->primaryTable, $this->id_field, $uniqueid, $updatee);
			
			return $query;
		}
	
		function update(...$fields)
		/*
		 * Standard method to update or flush data.  
		 * 
		 * It will only flush when the data is new, which is marked by 
		 * 
		 * fields is mostly obsolete
		 */
		{		
			if (!$this->exists)
				$result=$this->flush();
			else
			{
				$result=false;
				
				if ($this->is_archive()=='ok')
					$query_function="archive_query";
				else
					$query_function="query";
//					var_dump($this->credentials->primary(), $this->active_credential);					
//				if ($this->credentials->check($this->active_credential)) //turn this on for more strict settings
				{
					foreach ($this->temp_data as $temp_data)
						$this->remove($temp_data);
						
					$uniqueid=$this->secure_db->SQLSafeString($this->{$this->id_field});
					
					$d=$this->row_encoder($this->data, $this->credentials);
					$data=$this->secure_db->SQLSafeString($d['encrypted']);

					$query=$this->make_update_query($uniqueid, $data);
										
//					$query="UPDATE ".$this->primaryTable." SET ".$this->db_dataholder."='$data' WHERE $this->id_field='$uniqueid'";
//					echo("<BR><BR>"); var_dump($query);
	//				var_dump('JLE', json_last_error(), $this->credentials->valid_input_session(), $this->is_db_locked(), get_class($this));
					if ((!$this->is_db_locked()) && (json_last_error()==JSON_ERROR_NONE) && 
						($this->credentials->valid_input_session()))
					{								
						$this->secure_db->{$query_function}($query);
						$this->credentials->shuffle_input_session();
						
						$this->raw_data=$d['raw'];
	
						$this->transaction=[];
						
						$result=true;
					}
					
					$this->exists=true;				
				}
				
				if (!empty($fields))
				{
					parent::update(...$fields);
					$this->update_dbfield(false, true, ...$fields);
				}
			}
			
			return $result;
		}
		
		function dispose($debug=false, $override=false)
		/*
		 * this function is archive aware and will only dispose only from main database
		 * and not archieve database
		 */
		{
			$result=false;
			
			if ($this->is_archive()=='ok')
				$query_function="archive_query";
			else
				$query_function="query";
					
			if ($this->credentials->check($this->active_credential))
			{
				$query="DELETE FROM ".$this->primaryTable." WHERE $this->id_field='".
						$this->secure_db->SQLSafeString($this->{$this->id_field})."'";
															
				if (($this->credentials->valid_input_session()) || ($override))
				{
					$result=true;
					if ($debug)
						var_dump($query);
					$this->secure_db->{$query_function}($query);
					$this->credentials->shuffle_input_session();
				}
			}
			
			return $result;
		}
		
		function delete($name=null)
		/*
		 * This function is archive aware, as it will delete and move the information to archive.
		 * It will update only when the file is not archive
		 */
		{
			if ($name) parent::delete($name);
			else
				if ($this->credentials->check($this->active_credential))
				{
					$this->addData('_deleted', $this->credentials->username, 'user');
					$this->addData('_deleted', \date::today('timestamp'), 'time');
					
					if (!$this->is_archive())
						$this->to_archive();
					else 
						$this->update();
				}
		}		
		
		function is_deleted()
		{
			return $this->select('_deleted');
		}
	
		function exists($autoload=false)
		/*
		 * When autolaod is true, it will load from database and remove existing modification to the
		 * data
		 */
		{
			if ($this->uniqueid)
			{
				if ($autoload)
				{
					$this->load($this->uniqueid);
				}
				else
				{
					$uniqueid=$this->secure_db->SQLSafeString($this->uniqueid);
					$query="SELECT * FROM ".$this->primaryTable." WHERE $this->id_field='$uniqueid'";						
					
					$this->secure_db->query($query);
					$this->exists=($this->secure_db->num_rows()>0);
				}
			}
			return $this->exists;
		}
	
		function __construct($credentials, $active_credential, $secure_db, $readonly_db, $data, $table='', $uniqueid='', $idfield='uniqueid')
		/*
		 * While invoking, on most cases, active_credential should be input as $credentials->primary()
		 * 
		 * Caution: using active_credential on constructor from any other class that inherits from this class
		 * is not compatible with advanced_list new method.  Therefore it is advisable to automatically 
		 * input as $credentials->primary() while calling this constructor from any class that inherit this
		 */
		{
			parent::__construct($data, $uniqueid);
			$this->db_dataholder='data';
			$this->secure_db=$secure_db;
			$this->readonly_db=$readonly_db;
			$this->primaryTable=$this->secure_db->SQLSafeString($table);
			$this->active_credential=$active_credential;
			$this->credentials=$credentials;
			$this->date_field='date';
			
			$this->indices=[];
						
			if ($idfield!='uniqueid')
			{
				$this->id_field=$idfield;
				$this->{$this->id_field}=$this->uniqueid;
			}
	
			$this->exists=false;
			$this->valid=true;
			
			$this->blockchain=$this->get_global('settings')->select('blockchain', 'enabled');
			
			if (($credentials) && ($active_credential) && ($credentials->check($active_credential)))
			{
				if ((empty($this->data)) && ($uniqueid))
				{					
					$this->uniqueid=$uniqueid; //it is not necesssary
					$this->{$this->id_field}=$uniqueid; //it is not necesssary
					$this->load();
				}
				/*				else
					{
					$this->uniqueid=$id;
					$this->data=$data;
					$this->mirror=$data;
					}*/
			}
/*			else
			{
				$this->valid=false;
			}*/
	
			if (!$this->select($this->id_field))
				$this->addData($this->id_field, $this->uniqueid);
		}	
		
		function update_dbfield($debug=false, $write=true, ...$fields)
		//fields is a set of variable argument length each consisting of pairs of array  
		{
			$result=false;
			
			if (!$this->is_archive())
			{
				$field_string='';
				foreach ($fields as $field)
				{
					$field_string.=$this->secure_db->SQLSafeString($field[0])."='".$this->secure_db->SQLSafeString($field[1])."' ";
				}
				$result='';
							
				$query="UPDATE ".$this->primaryTable." SET ".$field_string.
					   " WHERE ".$this->id_field."='".$this->select($this->id_field)."'";
				
				if ($debug)
					echo($query);
				if ($write)
					$result=$this->secure_db->query($query);						
			}

			return $result;
		}
				
		function clone($source, ...$exceptions)
		{			
			$result=false;
			
			$e_array=[];
			$exceptions[]='_lock';
			foreach ($exceptions as $exception)
				$e_array[$exception]=1;
			
			if (get_class($this)==get_class($source))
			{
				$result=true;
				foreach ($source->data as $key => $value)
				{
					if (($key!=$this->id_field) && (!$e_array[$key]))
					{
						$this->data[$key]=$value;
					}
					else if (!$e_array[$key])
					{
						$this->data['_source_'.$this->id_field]=$value;
					}
				}
			}
			
			return $result;
		}
		
		function short_id($length=6)
		{						
			return hash('crc32', $this->{$this->id_field}); 
		}
		
		function is_showing(...$conditions)
		{
			return !$this->select('_deleted');
		}
		
		function familiar_name()
		{
			return $this->short_id();
		}
		
		function get_changes($input)
		{
			$name=$input['is_liveedit'];
			return $this->familiar_name().' from '.$this->mirror[$name].' to '.$this->data[$name];
		}
		
		function archiveable_record()
		/*
		 * Do not change this on recorded_entity
		 * not all recorded entity is archiveable, change it on actual
		 * entity
		 */
		{
			return false;
		}
			
		private function move_to_archive()
		/*
		 * this function should not be invoked on its own, please use to_archive instead
		 */
		{							
			$settings=$this->get_global('settings');					
			
			if ($this->credentials->valid_input_session())
			{
				$antispam=$settings->select("_anti_spam");
				$settings->addData('_anti_spam', 'off');
								
				$this->secure_db->archive($this);
				
				$settings->addData('_anti_spam', $antispam);				
			}
		}
		
		function is_archive()
		/*
		 * By default always return false
		 */
		{			
			return $this->select('_archive', 'status');			
		}
		
		function to_primary_db()
		{
			$settings=$this->get_global('settings');
			$result=$this->is_archive()=='ok';
			
			if (($this->credentials->valid_input_session()) && ($result))
			{
				$antispam=$settings->select("_anti_spam");
				$settings->addData('_anti_spam', 'off');
				
				$this->secure_db->unarchive($this);
					
				$settings->addData('_anti_spam', $antispam);
			}
									
			return $result;
		}
		
		function to_archive()
		{			
			$result=false;
			
			if (($this->is_archiveable()) || ($this->is_deleted()))
			{
				$result=$this->move_to_archive();
			}
			
			return $result;
//			return false;			
		}
		
		function is_archiveable()
		/*
		 * this method will be overriden by descendent class with actual 
		 */
		{
			return ($this->archiveable_record()) && (!$this->is_archive());
		}
				
		function db_lock_update()
		/*
		 * lock information is already on rawdata, considering should use encode 
		 * or just use raw data 
		 * 
		 * this is working above integrity but below data level 
		 */
		{
			if ($this->credentials->check($this->active_credential))
			{				
				$uniqueid=$this->secure_db->SQLSafeString($this->{$this->id_field});
				$data=$this->secure_db->SQLSafeString($this->encrypt_data($this->raw_data));
				$query="UPDATE ".$this->primaryTable." SET ".$this->db_dataholder."='$data' WHERE $this->id_field='$uniqueid'";
				
				if (json_last_error()==JSON_ERROR_NONE)
				{
					$this->secure_db->simple_query($query);
				}
				
			}			
		}
		
		function db_lock($expired=30)
		/*
		 * used to maintain data access concurrency, lock period is default 30 seconds! 
		 */
		{
			$this->raw_data['_lock']['exp']=time()+$expired; //300 seconds?
			$this->raw_data['_lock']['id']=$this->credentials->username;

			$this->db_lock_update();
		}
				
		function db_unlock()
		{
			unset($this->raw_data['_lock']);
			
			$this->db_lock_update();
		}
		
		function db_wait($interval=10000)
		/*
		 * Reloading from db, while not locked Interval is microseconds
		 */
		{
			while (($exp=$this->raw_data['_lock']['exp']) && ($exp>time()))
			{
				usleep($interval);
				$this->load($this->{$this->id_field});				
			}			
		}
		
		function is_db_locked($wait=false)
		/*
		 * used to check whether this is locked or unlocked
		 */
		{
			$result=false;
							
			$exp=isset($this->raw_data['_lock']['exp']) ? $this->raw_data['_lock']['exp'] : null;
			$id=isset($this->raw_data['_lock']['id']) ? $this->raw_data['_lock']['id'] : null;
			if (($id) && ($exp))
			{
				$t_data_values=[];
				foreach ($this->temp_data as $temp_data)
				{
					$t_data_values[$temp_data]=$this->select($temp_data);
					$this->remove($temp_data);
				}
				
				if (($exp>time()) && ($id!=$this->credentials->username))
				{
					$result=true;
					if ($wait)
					{
						$this->db_wait(10000);
					}
				}
				else
				{
					$this->db_unlock();
				}
				
				foreach ($this->temp_data as $temp_data)
					$this->addData($temp_data, $t_data_values[$temp_data]);					
			}
										
			return $result;
//			if locked then locked, but if the input session is the same
//			then with the input session then its ok alias valid
		}
		
		function is_db_unlocked($wait=false)
		{
			return !$this->is_db_locked($wait);
		}
				
		function db_indices_value()
		/*
		 * Reading indices values to be stored on database 
		 */
		{
			$result=[];
			
			foreach ($this->indices as $key => ['db_index' => $database, 'is_function' => $is_function])
				if ($database)
				{
					if ($is_function)
						$result[$key]=$this->$key(...$this->indices[$key]['params']);
					else
						$result[$key]=$this->get($key);
				}
			
			return $result;
		}
		
		function add_index($key, $is_function=false, ...$params)
		/*
		 * This is used to add index to this
		 */
		{
			if ($is_function)
				$this->indices[$key]=['is_function' => $is_function, 'params' => $params];
			else
				$this->indices[$key]=['is_function' => $is_function];
		}
		
		function enable_db_index($key, $pepper, $nonce)
		/*
		 * This can be set using _set if needed but, not for now
		 */
		{
			$this->indices[$key]['db_index']=true;			
			$this->indices[$key]['pepper']=$pepper;
			$this->indices[$key]['nonce']=$nonce;
		}
		
		function db_add_index($key, $pepper='', $nonce=0, $is_function=false, ...$params)
		/* 
		 * Same with add_index and enable_db_index but for calling it at once
		 * 
		 * used on instill_element_indices function of a list when an element is added
		 * to a list  
		 */
		{
			$this->add_index($key,$is_function, ...$params);
			
			$this->enable_db_index($key, $pepper, $nonce);
		}
		
		function db_update_index()
		/*
		 * This method will update extra index on database, only when its outdated
		 */
		{
			foreach ($this->db_indices_value() as $key => $value)
			{				
				$v_key=$this->hash_nonce($value, $this->indices[$key]['pepper'], $this->indices[$key]['nonce']);
/*				if (!is_null($this->stored_indices))
				{var_dump('aaa', $this->stored_indices, $key, $value); die;}*/
				if ((!isset($this->stored_indices[$key])) || 
					(array_search($this->stored_indices[$key], $v_key)===false))				
					$this->update();
			}
//			die;
//			var_dump($this->stored_indices);
		}
								
		function var_not_null($eq_function='select', $params=[])
		{
			if (!is_array($params)) $params=[$params];
			
			return $this->{$eq_function}(...$params);
		}

		function var_null($eq_function='select', $params=[])
		{
			if (!is_array($params)) $params=[$params];
			
			return !$this->{$eq_function}(...$params);
		}
		
		function haystack_search($needle, ...$haystack)
		{
			return array_search($needle, $haystack) !== false;
		}
		
		function array_same_contents($a, $b)
		/*
		 * This method will return if the contents of an array is the same 
		 */
		{
			$a=$a ?? [];
			$b=$b ?? [];
			return (count($a)==count($b) && count(array_intersect($a, $b))==count($a));
		}
		
		function var_equals($params, $eq_function, $value, $null_zero=true)
		/*
		 * This function is used to determine whether a variable on this class is equals to
		 * the required value using specified equalisation function
		 * input: 
		 * params is used on select, fill it with name and index (as array) or just name
		 * eq function can be hash_equals or string_equals or any other function
		 * value 
		 * if its not specified then it will result to == equals 
		 * output 
		 * whether it is equal or not
		 */
		{
			/*			if (is_string($this->select('time')))
			 {
			 $this->addData('time', $this->date_string_to_timestamp($this->select('time')));
			 $this->update();
			 }*/
			
			if (!is_array($params)) $params=[$params];

			if (!is_array($value)) $ar_value=[$value];
			else $ar_value=$value;
			
			$this_value=$this->get(...$params) ?? ($null_zero ? 0 : null);
			
			$result=false;
			
			switch (true)
			{
				case (method_exists($this, $eq_function)) :
					$result=$this->{$eq_function}($this_value, ...$ar_value);
					break;
				case (function_exists($eq_function)) :
					$result=$eq_function($this_value, ...$ar_value);
					break;
				case ($eq_function=='='): case ($eq_function=='=='): 
					$result=$this_value == $value;
					break;
			}
					
			return $result;
		}	
		
		function var_exists($params, $eq_function, ...$values)
		{
			if (!is_array($params)) $params=[$params];
			
			$result=false;
			
			if ($this->select(...$params))
			{
				if (method_exists($this, $eq_function))
					foreach ($values as $value)
						if ($result=$this->{$eq_function}($this->select(...$params), $value)) break;
				else
					if (function_exists($eq_function))
						foreach ($values as $value)
							if ($result=$eq_function($this->select(...$params), $value)) break;
					else
						foreach ($values as $value)
							if ($result=$this->select($value) == $params) break;
			}
			
			return $result;							
		}
				
		function f_open_form_hyperlink($callback, $uri, $credential, $params, $target='_top')
		{
			$result=$this->make_hyperlink($uri ?? "page.php", $callback, $credential, 
				array_merge([$this->id_field => $this->select($this->id_field)], $params), $target);
			
			return $result;
		}	
		
		function __toString()
		{
			return $this->select($this->id_field);
		}
		
		function instanceof($class)
		{
			return $this instanceof $class;
		}
		
		function count($name, ...$indices)
		/*
		 * Use this to count the contents of the record
		 * Do not use this method in add_formatting, it might behave weirdly
		 * 
		 * Use it on addDisplay
		 */
		{
			return count($this->get($name, ...$indices) ?? []);
		}
		
		function authorise(credentials $credential, $name='auth', $date=null)
		{
			$this->set($name, $credential->username, 'username');
			$this->set($name, $date ?? \date::today('timestamp'), 'date');
		}
		
		function authoriser($name)
		{
			return $this->get($name, 'username');
		}
		
		var $form;
		function set_form(lib_form $form)
		/*
		 * reverse of set and get dataholderr
		 */
		{
			$this->form=$form;
		}
		
		function get_form(): lib_form
		{
			return $this->form;
		}
	}	
		
	class conversion extends entity
	{		
		var $length_conversion_table;
		var $mass_conversion_table;
		var $time_conversion_table;
		
		var $modules;
		
		static function duplicate_array($outer_array)
		{			
			foreach ($outer_array as $unit1 => $inner_array)
			{
				foreach ($inner_array as $unit2 => $value)
				{
					if (!isset($outer_array[$unit2]))
						$outer_array[$unit2]=array();
					if (!isset($outer_array[$unit2][$unit1]))
						$outer_array[$unit2][$unit1]=1.0/$value;
				}
			}
					
			return $outer_array;
		}
		
		static function mass_conversion_table()
		{
			$conversion_array=array();
			$conversion_array['kg']=array();
			$conversion_array['kg']['g']=1000;
						
			$conversion_array['bale']=array();
			$conversion_array['bale']['lbs']=400;
						
			$conversion_array['lbs']=array();
			$conversion_array['lbs']['g']=453.59237;			
									
			return conversion::duplicate_array($conversion_array);
		}
		
		static function length_conversion_table()
		{
			$conversion_array=array();
			$conversion_array['m']=array();
			$conversion_array['m']['cm']=100;
			
			$conversion_array['m']['dm']=10;
			
			$conversion_array['cm']=array();
			$conversion_array['cm']['mm']=10;
			
			$conversion_array['mm']=array();
			
			$conversion_array['yd']=array();
			$conversion_array['yd']['inch']=36;
			
			$conversion_array['inch']=array();
			$conversion_array['inch']['mm']=25.4;
						
			return conversion::duplicate_array($conversion_array);
		}

		static function time_conversion_scale()
		{
			$conversion_array=array();						
			$conversion_array['h']=array();
			$conversion_array['h']['m']=60;
			
			$conversion_array['m']=array();
			$conversion_array['m']['s']=60;

			$conversion_array['d']['h']=24;
			
			return conversion::duplicate_array($conversion_array);
		}
		
		function __construct($global_name='converter')
		{
			$this->length_conversion_table=$this->length_conversion_table();//initialise to basic conversion
			$this->mass_conversion_table=$this->mass_conversion_table();//initialise to basic conversion
			$this->time_conversion_table=$this->time_conversion_scale();
			
			$this->to_global($global_name, $this);
			
			$this->modules=[];
		}
		
		function add_module(conversion $new_module)
		{
			$this->modules[get_class($new_module)]=$new_module;
		}
		
		function convert($function, $from, $to, $value)
		{
			if (method_exists($this, $function))
			{
				$result=$this->{$function}($from, $to, $value);
			}
			else 
			{
				foreach ($this->modules as $module)
				{
					if (method_exists($module, $function))
						$result=$module->{$function}($from, $to, $value);
				}
			}
			
			return $result;
		}
		
		function conversion_factor($from, $to, $lookup_table, $checked=array())
		{
			$result=0;			
			if ($from==$to) $result=1;
			else
				if (!isset($checked[$from]))
				{
					$checked[$from]=true;
					
					$conversion_table=$this->{$lookup_table};
					
					if (isset($conversion_table[$from][$to]))
					{
						$result=$conversion_table[$from][$to];
					}
					else
					{							
						foreach ($conversion_table[$from] as $key => $value)
						{							
							if ($from!=$key)
							{
								$c_result=$this->conversion_factor($key, $to, $lookup_table, $checked);
								if ($c_result!=0)
								{
									$result=$conversion_table[$from][$key]*$c_result;
									$this->add_conversion_unit($from, $to, $result, $lookup_table);
								}
							}
						}
					}
				}
			
			return $result;
		}
		
		function convert_time($from, $to, $value)
		//run recursivly and dinamically later in the future
		{
			return $this->conversion_factor($from, $to, 'time_conversion_table')*$value;
		}
		
		function convert_length($from, $to, $value)
		//run recursivly and dinamically later in the future
		{
			return (float) $this->conversion_factor($from, $to, 'length_conversion_table')*(float) $value;
		}
		
		function convert_mass($from, $to, $value)
		{
			return $this->conversion_factor($from, $to, 'mass_conversion_table')*$value;
		}		
		
		function add_conversion_unit($from, $to, $conversion_factor, $lookup_table)
		{
			if ($conversion_factor!=0)
			{
				if (!$this->{$lookup_table}[$from])
					$this->{$lookup_table}[$from]=array();
				if (!$this->{$lookup_table}[$to])
					$this->{$lookup_table}[$to]=array();
						
				$this->{$lookup_table}[$from][$to]=$conversion_factor;
				$this->{$lookup_table}[$to][$from]=1.0/$conversion_factor;
			}
		}		
	}
			
	trait namespace_member
	{
		function get_namespace_master($interface=null)
		/*
		 * This function is not buggy but it needs to be fixed, do not use in production
		 * 
		 * Accessing namespace master (e.g. first class that initialise the extension)
		 * from anything that is not junction class will require you to put 
		 * the interface that is used by junction
		 */
		{
			$setting=$this->get_global('settings');
			
			if (is_object($interface))
				$if_class=get_class($interface);
			else 
				$if_class=$interface ?: get_class($this);
			
			return $setting->select('namespace_master', $if_class);
		}
	}
	
	trait record_interface
	/*
	 * DEPRECATED
	 * 
	 * To have a recorded entity with loader button is not the nicest thing to make in term of
	 * software architecture, trait is used to give it when needed
	 */
	{
		static function rv_loader_step()
		/*
		 * This will determine which rv_input_step is the loader step, hence the system will expect
		 * that as the loader
		 *
		 * Please overload on a function that is using loader_mode
		 */
		{
			return 0;
		}
		
		function create_loader_button(junction $callback, $interface, $inputs)
		/*
		 * Create button to load an recorded entity,
		 *
		 * junction is the current interface that this was called
		 * interface is the next interface of the form
		 * inputs are the necessary input that is needed for this loader button to work
		 */
		{
			if (!class_exists($interface))
			{
				$namespace=$this->find_namespace($this);
				
				$c_interface=$namespace.'\\'.$interface;
			}
			
			$is=$c_interface::interface_form()::rv_input_step();
			$loader=$c_interface::interface_form()::rv_loader_step();

			$input=[$this->id_field => $this->select($this->id_field), $c_interface::interface_form()::submit_name() => $is[$loader], ...$inputs];
//var_dump(array_keys($input), array_keys($inputs));			
			$form=$callback->miniform_button($input, [$this->id_field, ...array_keys($inputs)], ['submit' => $c_interface::interface_form()::submit_name()]);
//			var_dump($form->select('_namespace'));
			$form->set_junction($interface);
			
			return $form->toStringFormat('&nbsp;');
		}
	}
		
	trait numerator
	{
		/*
		 * This trait is used to add sequential number to anything that need it
		 */
		private $seq_number;
		
		function seq_number()
		/*
		 * Numberator, call this from addDisplay
		 */
		{
			return $this->seq_number ?? '0';
		}
		
		function set_seq_number($seq)
		{
			$this->seq_number=$seq;
		}
	}	
?>