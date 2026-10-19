<?php
	class features extends entity
	/*
	 * This method is used to add script as page features.
	 * one page can have many features and  each feature should only be linked
	 * to one feature as the parameters passed is different to each of the scripts
	 * 
	 * if you need 2 scripts of the same functionality but different name, just use two object
	 * The name should be feature, not features  
	 */
	{
		var $script_function;
		var $header_function;		
		var $dependencies;
		
		var $script_execute;
		var $credentials;
		
		function __construct($credentials, $data, $uniqueid, $script_function, $script_execute=null)
		/*
		 * $data is the parameters to the script function
		 * 
		 * if script funciton is an array, then data is expected to be an array as well, with index
		 * of the script function name
		 * 
		 * $data will be passed to script_function
		 */
		{			
			parent::__construct($data, $uniqueid);
			
			$this->script_function=$script_function;
			$this->script_execute=$script_execute;
			
			$this->credentials=$credentials;
			
			$this->dependencies=[];
		}
		
		function get_name()
		{
			return $this->uniqueid;
		}
		
		function get_script()
		{
			$output=null;
			$result=[];
			if (is_array($this->script_function))
			{
				foreach ($this->script_function as $sf)
					$result[]=$this->{$sf}(...($this->data[$sf] ?? []));
				
				$output=implode(' ', $result);					
			}
			else
			{
				if ($this->script_function)
					$result[]=$this->{$this->script_function}(...$this->data);
				
				$output=implode(' ', $result);
			}
			
			return $output;
		}
		
		function get_execute()
		/*
		 * Script Execution to function is run here, always made at the bottom of page
		 */
		{
			return $this->{$this->script_execute}();
		}
		
		function add_dependency(...$deps)
		/*
		 * Add dependent features that must be loaded, deps is a new feature of its own!
		 * Not just class name
		 */
		{
			foreach ($deps as $dep)
			{
				if ($dep instanceof features)					
					$this->dependencies[]=$dep;
			}
		}
		
		function check_dependencies()
		/*
		 * Checking if dependencies exists
		 */
		{
			return count($this->dependencies)>0;
		}
		
		function get_dependencies(...$features)
		/*
		 * Load all dependencies, but the features 
		 */
		{
			$f=[];
			
			foreach ($features as $feature)
				$used_features[]=$feature->name;
			
			foreach ($this->dependencies as $dep)
			{
				if (!in_array($dep->name, $used_features))
				/*
				 * if dependency not existed then will register it 
				 */
				{
					$f[]=$dep;
					$features=[$dep, ...$features];
					$f=[...$f, ...$dep->get_dependencies(...$features)];
				}
			}
			
			return $f;
		}		
	}
	
	class execute_function extends features
	/*
	 * Used to execute a javascript function, executed in sequential order, and 
	 * will make use functions that is input by 
	 * 
	 * This method is a little inconsistent with other 
	 * 
	 */
	{
		var $execution_param;
		
		function __construct($credentials, $uniqueid, $script_execute, $execution_param)
		/*
		 * $data is the parameters to the script function
		 *
		 * if script funciton si an array, then data is expected to be an array as well, with index
		 * of the script function name
		 */
		{
			parent::__construct($credentials, [], $uniqueid, null);
						
			$this->credentials=$credentials;
			
			$this->script_execute=$script_execute;
			
			$this->execution_param=$execution_param;
		}
		
		function get_execute()
		{			
			foreach ($this->script_execute as $sf)
				$scripts[]=$sf.'('.implode(',', $this->execution_param[$sf] ?? []).');';
			
			$result='<script type="text/javascript">';				
			$result.=implode(' ', $scripts);
			$result.='</script>';
			
			return $result;
		}
	}
	
	class css extends features
	/*
	 * Only one CSS feature is allowed on each page, if you add two css feature then it will override 
	 * the first one
	 */
	{
		var $name;
		
		function __construct($credentials, $name, $media='all', $default=true)
		/*
		 * If default is set to true, then it will be the default CSS on a page (hence the name CSS), which is overideable 
		 * when you add another CSS.
		 * 
		 * When default is set to false, then you can add multiple CSS to the page, e.g. one for print and the other for screen
		 */
		{
			$this->name=$name;
			parent::__construct($credentials, [], $default ? 'css' : $name, 'show_css');

			$this->set('media', $media);
		}
		
		function show_css($media)
		{			
			return '<link rel="stylesheet" type="text/css" '.($media ? 'media="'.$media.'"' : '').' href="'.$this->name.'" rel="stylesheet">';
		}
	}	
	
	class js_variable extends features
	{
		/*
		 * This class is used to write global variable available on javascript
		 */
		function __construct($credentials, $data)
		{			
			parent::__construct($credentials, $data, 'js_variable', 'write_global_vars');			
		}
		
		function write_global_vars(...$data)
		{
			$result='<script type="text/javascript">';
			$v_arr=[];
			foreach ($data as $var => $value)
			{
				if (is_numeric($value)) /* if it is number but string will regard as number*/
					$v_arr[]=$var.'='.$value;
				else if (is_string($value))
					$v_arr[]=$var.'="'.$value.'"';
				else if (is_array($value))
				{
					$vs=[];
					
					$is_array=true;
					foreach ($value as $key => $v)
					{
						if (!is_integer($key))
							$is_array=false;
						
						if (is_numeric($v))
							$vs[$key]=$v;
						else if (is_string($v))
							$vs[$key]='"'.$v.'"';
					}
					
					if ($is_array)
						$v_arr[]=$var.'=['.implode(',', $vs).']';
					else
					{
						$object=[];
						foreach ($vs as $key => $value)
							$object[]=$key.': '.$value;
						
						$v_arr[]=$var.'={'.implode(',', $object).'}';
					}
				}
					
			}
			$result.=implode('; ', $v_arr);
			$result.='</script>';
			
			return $result;
		}		
	}
	
	class math_dom extends features
	{
		function ratio()
		{
			$result='<script type="text/javascript">';
			$result.='function '.$this->script_function.'(target, field1, field2, multiplier)';
			$result.='{';
			$result.="	kg=0; metre=0; result='n/a';";
			$result.='	if (document.getElementById(field1).value.length>0)';
			$result.='		kg=parseFloat(document.getElementById(field1).value)*multiplier;';
			$result.='	if (document.getElementById(field2).value.length>0)';
			$result.='		metre=parseFloat(document.getElementById(field2).value);';
			$result.='  if (metre>0) result=kg/metre;';
			$result.='	document.getElementById(target).innerHTML=result.toLocaleString();';
			$result.='}';
			$result.='</script>';
			
			return $result;			
		}
		
		function percentage_smaller($qualification)
		{
			$result='<script type="text/javascript">';
			$result.='function '.$this->script_function.'(target, fields)';
			$result.='{';
			$result.='	qualify=0; count=0;';
			$result.='	for (let i=0; i<fields.length; i++)';
			$result.='	{';
			$result.='		if (document.getElementById(fields[i]).value.length>0)';
			$result.='		{';
			$result.='			count++;';
			$result.='			if (parseFloat(document.getElementById(fields[i]).value)<='.$qualification.')';
			$result.='				qualify++;';
			$result.='		}';
			$result.='	}';
			$result.='  result=0;';
			$result.='  if (count>0) result=100*qualify/count;';
			$result.='	document.getElementById(target).innerHTML=result.toLocaleString();';
			$result.='}';
			$result.='</script>';
			
			return $result;
		}		
		
		function values_avg(...$fields)
		{
			$result='<script type="text/javascript">';
			$result.='function '.$this->script_function.'(target, fields)';
			$result.='{';
			$result.='	sum=0; count=0;';
			$result.='	for (let i=0; i<fields.length; i++)';
			$result.='	{';
			$result.='		if (document.getElementById(fields[i]).value.length>0)';
			$result.='		{';
			$result.='			sum+=parseFloat(document.getElementById(fields[i]).value);';
			$result.='			count++;';
			$result.='		}';
			$result.='	}';
			$result.='  result=0;';
			$result.='  if (count>0) result=sum/count;';
			$result.='	document.getElementById(target).innerHTML=result.toLocaleString();';
			$result.='}';
			$result.='</script>';
			
			return $result;
		}
		
		function values_sum(...$fields)
		/*
		 * Calculate the value of multiple input field and output it to one location
		 * within a page
		 * 
		 * How to use the value sum
		 * On form 
		 * register the event e.g. onchange using register_html_event to 
		 * trigger the values_sum method on javascript
		 * add function on form to list the form that is used as identifier
		 * 
		 * On interface
		 * Add js_variable as global variable to list the identifier needed
		 * Add math_dom, or this class
		 * 
		 * visit bale_do_input_interface for more information 
		 * 
		 * As usual, one feature per function call
		 * 
		 * Look at bale_do_input_interface for for example on how to use it
		 * 
		 * On Firefox, when input is not a valid number, then it will not register on 
		 * document.getElementById(fields[i]).value as it will yield blank
		 * Hence calling isNaN will result in false, empty string is regarded as 0
		 * 
		 * and may or may not trigger onchange function 
		 * with non valid number it will trigger only once when changeing the first time
		 * with number outside range, it will work as as if its normal
		 */
		{
			$result='<script type="text/javascript">';
			$result.='function '.$this->script_function.'(target, fields)';
			$result.='{';
//			$result.='	fields = ['.implode(',', $f).'];';
			$result.='	result=0;';			
			$result.='	for (let i=0; i<fields.length; i++)';
			$result.='	{';				
			$result.='		if (document.getElementById(fields[i]).value.length>0)';
			$result.='			result+=parseFloat(document.getElementById(fields[i]).value);';
			$result.='	}';
			$result.="	if (target!='')";
			$result.='		document.getElementById(target).innerHTML=result.toLocaleString();';
			$result.='  return result;';
			$result.='}';
			$result.='</script>';
			
			return $result;
		}		
	}	
?>