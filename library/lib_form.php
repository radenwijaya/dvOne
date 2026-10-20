<?php
	include_once("lib_baseclass.php");
	
	define('max_input_vars', 1000);
	
	define('form_invalid_input', '406 input is not acceptable');
	
	class lib_form extends s_lib
	{
		const upload_temp_location = '/tmp/';
		
		use lib_html;
		
		//it does not support radio!		
		var $name;
		var $action;
		var $method;
		var $fields;
		var $return;
		
		var $upload_form;
		
		var $input_step;
		
		var $credentials;
		
		var $available_credentials;
		
		var $index;
		
		var $standard_output;
		
		/*
		 * For output step usually stepZero, stepOne, etc...
		 */
		var $rv_output_step;
		
		/*
		 * For input step on default submit (first submit)
		 * 
		 * You can make same value for input step to make it repeating
		 * the value
		 */
		var $rv_input_step;
		
		var $rv_dataholder_name;
		var $rv_dataholder;
		
		var $form_status;
		
		var $csv_output;
		
		var $error;
		var $success;
		var $warning;
		var $notice;
		
		var $max_input_vars;
		
		/*
		 * Hidden fields will be encrypted when 
		 */
		var $hidden_fields;
		var $mandatory_fields;
		
		var $submit_name;
		
		var $buttons_list;
		
		var $standard_input;
		var $events;
		var $rv_output_field;
		
		var $junction;
		var $interface;
		
		static function submit_name()
		/*
		 * This will be default value for submit_name, user changeable
		 */
		{
			return 'save';
		}
		
		function __construct(credentials $credentials, $name, $action, $method, $return)
		{
			$this->max_input_vars=max_input_vars;
			
			$this->name=$name ?: get_class($this).'_'.$this->random_uniqueid(4);
			$this->action=$action;
			$this->method=($method==get) ? get : post;
			$this->return=$return;
			$this->input_step=0;
			$this->submit_name=$this->submit_name();
			
			$this->upload_form=false;
			
			$this->credentials=$credentials;
			$this->available_credentials=array();
			$this->rv_output_step=array();
			$this->rv_input_step=array();
			$this->rv_output_field=array();
						
			$this->fields=array();
			
			$this->mandatory_fields=[];
			
			$this->hidden_fields=[];
			
			$this->events=[];
			
			$this->add_field('', '_rv_name', 'reserved', $name, 0);
			$this->add_field('', '_rv_action', 'reserved', $action, 0);
			$this->add_field('', '_rv_method', 'reserved', $method, 0);
			$this->add_field('', '_rv_return', 'reserved', $return, 0);		
			
			$this->standard_input=
				array(
					'submit' => 'html_submit',
					'password' => 'html_password',
					'email' => 'html_text',
					'url' => 'html_text',
					'tel' => 'html_text',
					'hidden' => 'html_hidden',
					'text' => 'html_text',
					'textarea' => 'html_textarea',
					'dropdown' => 'html_dropdown',					
					'currency' => 'html_currency',
					'number' => 'html_number',					
					'date' => 'html_date',
					'time' => 'html_time',
					'datetime-local' => 'html_datetime',
					'month' => 'html_month',					
					'checkbox' => 'html_checkbox'
				);
				
			$this->rv_dataholder_name='';
			
			$this->error='';
			$this->success='';			
		}
		
		function input_count()
		{
			return count($this->fields);
		}
		
		function final_input_step()
		{
			return count($this->rv_input_step);
		}	
		
		function index()
		{
			return $this->name;
		}
		
		function getIndex()
		{
			return $this->name;
		}
		
/*		function toString()
		{
			//$result="";
			$output_function=$this->rv_output_step[$this->input_step];
			if (method_exists($this, $output_function))
				$result=$this->$output_function();
			else
				$result='function not implemented';
					
			return $result;
		}*/	
		
		function output_function()
		/*
		 * Will be refactored to always return toStringFormat
		 */		
		{
			return $this->rv_output_step[$this->input_step];
		}
			
		function toHTML()
		/*
		 * This will invoke the output_function of this class, which is defined in rv_output_step
		 * 
		 * the output function can be string or array, depending
		 * 
		 * Needs refactoring, e.g. to HTML will always run toStringFormat
		 * While the other stuff refactored to inputform_control
		 */		 
		{
			$output_step_count=count($this->rv_output_step);
			if ($output_step_count>0)
			{
				$output_function=$this->output_function();
				if (method_exists($this, $output_function))
				{
					$of_result=$this->$output_function();
					if (($this->has_layout_maker()) && (is_array($of_result)))
					{
						$result=$this->layout_maker()->assign_layout($of_result);
					}
					else
					{
						if (is_array($of_result))
						{
							$result='Layout maker not implemented';
							throw new Exception($result);
						}
						else
							$result=$of_result;
					}
				}
				else
				{
					$result='Output function not implemented';
					throw new Exception('Output function not implemented');
				}
			}
			else
			{
				$result=$this->toStringFormat('<br>', true);
			}
			
//			if ($this->)
			
			return $result;
		}
		
		function toString()
		/*
		 * Alias to toHTML
		 */
		{
			return $this->toHTML();
		}
		
		function get_caption($field)
		/*
		 * Autocaptioning!  It will check if lexicon dictionary existed then will use it
		 * however it will display input text as caption if the term doesnt existed in lexicon list
		 */
		{			
			$caption=$field['caption'];
			
			if ($caption!=='') 
			{
				$namespace=$this->find_object_location($this)['namespace'];
				
				if (class_exists($namespace.'\L'))
					$caption=($namespace.'\L')::S($field['caption']) ?? $field['caption'];
			}
			
			return '<label for="'.$this->scramble_field_name($field['name']).'">'.$caption.': '.'</label>';			
		}
				
		function form_open()
		{			
			if ($this->final_input_step()==$this->input_step)
				$nextpage=$this->return;
			else 
				$nextpage=$this->action;

			if ($this->upload_form)
				$enc='enctype="multipart/form-data"';
			else 
				$enc='';
				
			$this->form_status='opened';
			
			$result='<form name="'.$this->name.'" id="'.$this->name.'" action="'.$nextpage.'" method="'.$this->method.'" '.$enc.'>';
			
			foreach ($this->fields as ['name' => $name])
				if (isset($name))
					$this->add_specification($name, 'form', false);
			
			return $result;
		}
				
		function form_close()
		{
			$this->form_status='closed';
			
			$result='';
			$this->add_mandatory_input('_input_step', $this->input_step);
			$this->add_mandatory_input('_form_name', $this->name);
			
			foreach ($this->mandatory_fields as $mandatory)
				$result.=$this->html_hidden($mandatory);
				
			$params=[];
			foreach ($this->hidden_fields as $field)
			{	
				switch ($this->fields[$field]['type'])
				{
					case 'dropdown':
						$params[$field]=$this->fields[$field]['selected'];
						break;
					case 'number':						
						$value=$this->fields[$field]['value'];
						if (($this->fields[$field]['units']=='percent') || ($this->fields[$field]['units']=='%'))
							$value=$value*100;
						$params[$field]=$value;
						break;	
					default:
						$params[$field]=$this->fields[$field]['value'];
				}				
			}
			
//			if ($this->get_global('settings')->select('scramble_parameters'))
				$params['sf']=$this->credentials->get_scrambled_input();
					
			if ($this->upload_form)
				$params['uf']='upload_form';
					
			if ($this->get_global('settings')->select('encrypt_hidden_parameters'))
			{
				$ec=$this->encrypt_html_params($this->action, $params);
				$result.="<input type='hidden' name='ec' value='$ec'>";
			}
			
			foreach ($this->fields as ['name' => $name])
				if (isset($name))
					$this->add_specification($name, 'form', true);
			
			$result.='</form>';
			return $result;
		}
						
		function html_reserved($reserve_variable)
		{			
			$result="<input name='$reserve_variable' type='hidden' value='".$this->select($reserve_variable)."'>"; 
			
			return $result;
		}
		
		function add_credential_input($name, $value)
		{
			$this->add_field('', $name, 'credential', $value, 0);
		}
		
		function available_credentials()
		{			
			return array_keys($this->credentials->data);
		}
		
		function add_credential(...$credentials)
		{			
			if (($credentials=='') || empty($credentials))			
				$credentials=$this->available_credentials();
			
			if ($this->credentials->isValid())
			{			
				$cred=$this->credentials->asArray();
				if (!isset($this->fields[cUsernameChkSec]))
				{
					$this->add_credential_input(cUsernameChkSec, $this->credentials->username());					
					$this->available_credentials[]=cUsernameChkSec;
				}
				$this->add_field('', '_input_session', 'credential', $this->credentials->input_session(), 0);
				
				foreach ($credentials as $credname)
				{					
					$this->add_credential_input($credname, $cred[$credname]['cs']);
					$this->available_credentials[]=$credname;
				}				
			}
		}
		
		function get_fields()
		{
			return $this->fields;
		}
		
		function copy_field($name='', $fields='')
		/*
		 * Deprecated, scheduled for removal
		 */
		{
			if ((is_array($fields)) && ($name))
			{
				$this->fields[$name]=$fields;
				return $this->fields[$name];
			}
			else 
			{
				if (!is_array($name)) return $this->fields[$name];				
			}
		}
		
		function html_credential()
		{
			$result='';
			foreach ($this->available_credentials as $credential)
			{
				$result.=$this->html_hidden($credential);
			}
			
//			$this->add_field('', '_input_session', 'credential', $this->credentials->input_session(), 0);
			$this->editfield('_input_session', $this->credentials->input_session());
//			var_dump('x', $this->credentials->input_session());echo("<BR><BR>");
			$result.=$this->html_hidden('_input_session');
			
			return $result;
		}
				
		function add_specification($name, $spec, $value)
		{
			$this->fields[$name][$spec]=$value;
		}
		
		function specified($name, $spec)
		/*
		 * if it has specification (specified)  
		 */
		{
			$result=null;
			if (isset($this->fields[$name][$spec]))
				$result=$this->fields[$name][$spec];
			
			return $result;
		}
		
		function set_param($name, $spec, $params)
		{
			if (isset($params[$spec]))
				$this->add_specification($name, $spec, $params[$spec]);
		}		

		function add_field($caption, $name, $type, $value, $input_step)
		//$params can be placeholder or required		
		//on text area can be input here
		{
			$this->fields[$name]=['name' => $name, 'caption' => $caption, 'type' => $type, 'value' => $value, 'add_step' => $input_step, 'default' => $value];
			
			$this->rv_output_field[$input_step][]=$name;			
		}
		
		function add_div($caption, $name, $value, $input_step)
		{
			$this->add_field($caption, $name, 'div', $value, $input_step);
		}
		
		function add_button($caption, $name, $value='', $params=array(), $input_step=0)
		{
			$this->add_field($caption, $name, 'button', $value, $input_step);
			
			if (array_key_exists('disabled', $params)===true)
				$this->add_specification($name, 'disabled', true);
			if (array_key_exists('confirmation', $params))
				$this->add_specification($name, 'confirmation', $params['confirmation']);
			if (array_key_exists('auto_hide', $params))
				$this->add_specification($name, 'auto_hide', true);
			if (array_key_exists('hint', $params))
				$this->add_specification($name, 'hint', $params['hint']);
			if (array_key_exists('validate', $params))
				$this->add_specification($name, 'validate', $params['validate']);
			else
				$this->add_specification($name, 'validate', true);

//			if (array_key_exists('onclick', $params))
//				$this->add_specification($name, 'onclick', $params['onclick']);
			
			$this->add_specification($name, 'show_caption', true);										
		}
		
		function add_submit_button($caption='', $name='save', $value='', $params=array(), $input_step=0)
		/*
		 * Since submit_name variable defaults to 'save', if you wanted to use different name
		 * than save, specify 'main' on param to signify its the main submit
		 */
		{
			if (($name==$this->submit_name))
				if (!$value)
					$value=$this->rv_input_step[0];
				else if (!isset($this->rv_input_step[0]))
					$this->rv_input_step[0]=$value;
				
			$this->add_field($caption, $name, 'submit', $value, $input_step);
			$this->buttons_list[$name]=['name' => $name, 'clicked' => false];
			
			if (array_key_exists(disabled, $params)===true)
				$this->add_specification($name, 'disabled', true);
			if (array_key_exists('confirmation', $params))
				$this->add_specification($name, 'confirmation', $params['confirmation']);
			if (array_key_exists('auto_hide', $params))
				$this->add_specification($name, 'auto_hide', true);			
			if (array_key_exists('hint', $params))
				$this->add_specification($name, 'hint', $params['hint']);
			if (array_key_exists('validate', $params))
				$this->add_specification($name, 'validate', $params['validate']);
			else
				$this->add_specification($name, 'validate', true);

			if (array_key_exists('main', $params)===true)
				$this->submit_name=$name;
				
			$this->add_specification($name, 'show_caption', true);
		}
		
		function add_hidden_input($name, $value, $input_step=0)
		{
			$this->add_field('', $name, 'hidden', $value, $input_step);
		}
		
		function add_mandatory_input($name, $value, $input_step=0)
		{
			$this->add_field('', $name, 'hidden', $value, $input_step);
			$this->mandatory_fields[]=$name;
		}
		
		function add_password_input($caption, $name, $minlength, $maxlength, $input_step=0)
		{
			$this->add_field($caption, $name, 'password', '', $input_step);

			if ((is_numeric($maxlength)) && ($maxlength>0))
				$this->add_specification($name, maxlength, $maxlength);

			if ((is_numeric($minlength)) && ($minlength>0))
				$this->add_specification($name, minlength, $minlength);

			$this->add_specification($name, 'show_caption', true);
			
			$this->add_specification($name, required, true);
//			$this->add_specification($name, 'pattern', "{$minlength,$maxlength}");
		}

		function add_email_input($caption, $name, $value, $maxlength, $params, $input_step=0)
		{
			$this->add_field($caption, $name, 'email', $value, $input_step);

			if ((is_numeric($maxlength)) && ($maxlength>0))
				$this->add_specification($name, 'maxlength', $maxlength);

			if ($params['required']===true)
			{
				$this->add_specification($name, 'required', true);
				$this->add_specification($name, 'placeholder', 'this field is required');
			}
			
			$this->add_specification($name, 'show_caption', true);
		}
		
		function add_url_input($caption, $name, $value, $maxlength, $params, $input_step=0)
		{
			$this->add_field($caption, $name, 'url', $value, $input_step);
			
			if ((is_numeric($maxlength)) && ($maxlength>0))
				$this->add_specification($name, 'maxlength', $maxlength);
				
			if ($params['required']===true)
			{
				$this->add_specification($name, 'required', true);
				$this->add_specification($name, 'placeholder', 'this field is required');
			}
			
			$this->add_specification($name, 'show_caption', true);
		}		
		
		function add_phone_input($caption, $name, $value, $maxlength, $params, $input_step=0)
		{
			$this->add_field($caption, $name, 'tel', $value, $input_step);
		
			if ((is_numeric($maxlength)) && ($maxlength>0))
				$this->add_specification($name, 'maxlength', $maxlength);
		
			if ($params['required']===true)
			{
				$this->add_specification($name, 'required', true);
				$this->add_specification($name, 'placeholder', 'this field is required');
			}
			
			$this->add_specification($name, 'show_caption', true);
		}
		
		function add_textarea_input($caption, $name, $value, $placeholder, $maxlength, $params, $input_step=0)
		{			
			$this->add_field($caption, $name, 'textarea', $value, $input_step);
			
			if ((!is_numeric($maxlength)) || (!$maxlength)) $maxlength=10240;
			
			$this->add_specification($name, maxlength, $maxlength);

			if ($params['required']===true)
			{
				$this->add_specification($name, required, true);
				$this->add_specification($name, placeholder, 'this field is required');
			}
			
			if ($params[writeprotect]===true)
			{
				$this->add_specification($name, writeprotect, true);				
			}
			
			if ($placeholder!='')
				$this->add_specification($name, placeholder, $placeholder);
						
			if (is_numeric($params[size]))
			{
				$this->add_specification($name, size, $params['size']);
			}
			
			if (is_numeric($params[wrap]))
			{
				$this->add_specification($name, wrap, $params['wrap']);
			}
			
			if (array_key_exists(validate, $params))
				$this->add_specification($name, validate, $params[validate]);
			else
				$this->add_specification($name, validate, true);
			
			$this->add_specification($name, 'show_caption', true);
		}
				
		function add_text_input($caption, $name, $value, $placeholder, $maxlength, $params, $input_step=0)
		{
			$this->add_field($caption, $name, 'text', $value, $input_step);

			if ((!is_numeric($maxlength)) || (!$maxlength)) $maxlength=255;			
			$this->add_specification($name, maxlength, $maxlength);			
			
			if ($params[required]===true)
			{
				$this->add_specification($name, required, true);
				$this->add_specification($name, placeholder, 'this field is required');
			}
			if ($params[writeprotect]===true)
			{
				$this->add_specification($name, writeprotect, true);
			}
			if ($params[pattern])
			{
				$this->add_specification($name, pattern, $params[pattern]);
			}
			if ($params['hint'])
			{
				$this->add_specification($name, hint, $params[hint]);
			}
			if ($placeholder!='')
				$this->add_specification($name, placeholder, $placeholder);
			
			if (is_numeric($params['size']))
			{
				$this->add_specification($name, size, $params[size]);
			}
			if (is_numeric($params['wrap']))
			{
				$this->add_specification($name, wrap, $params[wrap]);
			}
			
			if (array_key_exists(validate, $params))
				$this->add_specification($name, validate, $params[validate]);
			else
				$this->add_specification($name, validate, true);
					
			$this->add_specification($name, 'show_caption', true);
		}
		
		function add_datalist($name, $items)
		/*
		 * This will add datalist to an input 
		 */
		{
			$dl_name=$this->quick_hash($this->random_uniqueid(8).$name);
			
			$this->add_specification($name, 'list', $dl_name);
					
			$this->fields[$name]['items']=$items;
		}
		
		function add_currency_input($caption, $name, $value, $min, $max, $params, $locale=default_locale, $input_step=0)
		{
			$this->add_field($caption, $name, 'currency', $value, $input_step);
			
			$this->add_specification($name, 'currency', $locale);
			
			if ($params['required']===true)
			{
				$this->add_specification($name, 'required', true);
				$this->add_specification($name, 'placeholder', 'this field is required');
			}
			
			if (is_numeric($min))
			{
				$this->add_specification($name, 'min', $min);
				$this->add_specification($name, 'placeholder', "x >$min");
			}
			if (is_numeric($max))
			{
				$this->add_specification($name, 'max', $max);
				$this->add_specification($name, 'placeholder', "x <$max");
			}
			if ((is_numeric($min)) && (is_numeric($max)))
				$this->add_specification($name, 'placeholder', "$min < x < $max");
			
			if (is_numeric($params['step']))
				$this->add_specification($name, 'step', $params['step']);
			else
				$this->add_specification($name, 'step', '0.01');
					
			if (is_numeric($params['decimals']))
				$this->add_specification($name, 'decimals', $params['decimals']);
			else
				$this->add_specification($name, 'decimals', 0);						
				
			if ($params['readonly']===true)
				$this->add_specification($name, 'readonly', true);			
			
			if ($params['placeholder'])
				$this->add_specification($name, 'placeholder', $params['placeholder']);
			
			$this->add_specification($name, 'show_caption', true);
		}
		
		function set_number_input_range($name, null|int|float $min, null|int|float $max)
		{
			if (is_numeric($min))
			{
				$this->add_specification($name, min, $min);				
			}
			if (is_numeric($max))
			{
				$this->add_specification($name, max, $max);				
			}
			
			switch (true)
			{
				case ((is_numeric($this->fields[$name][min])) && (is_numeric($this->fields[$name][max]))):
					$this->add_specification($name, placeholder, $this->fields[$name][min]." < x < ".$this->fields[$name][max]);					
					break;
				case (is_numeric($this->fields[$name][min])):
					$this->add_specification($name, placeholder, "x > ".$this->fields[$name][min]);
					break;
				case (is_numeric($this->fields[$name][max])):
					$this->add_specification($name, placeholder, "x < ".$this->fields[$name][max]);
					break;
			}								
		}
		
		function add_number_input($caption, $name, $value, $min, $max, $params, $input_step=0)
		/*
		 * Value must be input as integer
		 * otherwise when its hidden will not work.
		 */
		{
			$this->add_field($caption, $name, 'number', $value, $input_step);
			if ($params['required']===true)
			{
				$this->add_specification($name, required, true);
				$this->add_specification($name, placeholder, 'this field is required');
			}
						
			$this->set_number_input_range($name, is_numeric($min) ? $min : null, is_numeric($max) ? $max : null);
			
			if (is_numeric($params[step]))
			    $this->add_specification($name, step, $params[step]);
			
		  	if (is_numeric($params[decimals])) 
			    $this->add_specification($name, decimals, $params[decimals]);
			else 
			    $this->add_specification($name, decimals, 0);
			
            if ($params[units])
                $this->add_specification($name, units, $params[units]);

            if ($params[size])
                $this->add_specification($name, size, $params[size]);
                	
			if ($params[writeprotect]===true)
				$this->add_specification($name, writeprotect, true);
			
			if ($params[placeholder])
				$this->add_specification($name, placeholder, $params[placeholder]);
			
			if (array_key_exists(validate, $params))
				$this->add_specification($name, validate, $params[validate]);
			else
				$this->add_specification($name, validate, true);
						
			$this->add_specification($name, 'show_caption', true);
		}
				
		function add_date_input($caption, $name, $value, $min, $max, $params, $input_step=0)
		{
			if (is_int($value))	$value=date('Y-m-d', $value);
			
			$this->add_field($caption, $name, 'date', $value, $input_step);

			if ($min!==false)
				$this->add_specification($name, 'min', is_numeric($min) ? date("Y-m-d", $min) : $min);
			if ($max!==false)
			{				
				$this->add_specification($name, 'max', is_numeric($max) ? date("Y-m-d", $max) : $max);
			}

			if (array_key_exists('required', $params)===true)
				$this->add_specification($name, 'required', true);
			if (array_key_exists('readonly', $params)===true)
				$this->add_specification($name, 'readonly', true);
			
			$this->add_specification($name, 'show_caption', true);
		}
		
		function add_daterange_input($caption, $name, $value, $min, $max, $range, $params, $input_step=0)
		/*
		 * This method is a work in progress to add date range input.
		 * 
		 * If the begin and end range is is reversed it will be adjusted accordingly 
		 * 
		 * So it is not just two dates, but its a date range
		 */
		{
			if (!is_array($value)) $value=['begin' => \date::change_day(-$range, $value, 'timestamp'), 'end' => $value];
			
			$this->add_field('', $name.'_begin', 'daterange_begin', $value['begin'], $input_step);
			$this->add_specification($name.'_begin', 'daterange', $name);
			
			$this->add_field('', $name.'_end', 'daterange_end', $value['end'], $input_step);
			$this->add_specification($name.'_end', 'daterange', $name);
			
			$this->add_field($caption, $name, 'daterange', $value, $input_step);			
			
			if ($min!==false)
				$this->add_specification($name, 'min', is_numeric($min) ? date("Y-m-d", $min) : $min);
			if ($max!==false)
				$this->add_specification($name, 'max', is_numeric($max) ? date("Y-m-d", $max) : $max);
					
			if ($range!==false)
				$this->add_specification($name, 'range', $range);
				
			if (array_key_exists('required', $params)===true)
				$this->add_specification($name, 'required', true);
			if (array_key_exists('readonly', $params)===true)
				$this->add_specification($name, 'readonly', true);
						
			$this->add_specification($name, 'show_caption', true);						
		}
		
		function add_range_input($caption, $name, $min, $max, $params, $input_step=0)
		/*
		 * This method is a work in progress to add date range input.
		 *
		 * If the begin and end range is is reversed it will be adjusted accordingly
		 */
		{			
			$this->add_field('', $name.'_begin', 'range_begin', '', $input_step);
			$this->add_specification($name.'_begin', 'range', $name);
			
			$this->add_field('', $name.'_end', 'range_end', '', $input_step);
			$this->add_specification($name.'_end', 'range', $name);
			
			$this->add_field($caption, $name, 'range', 1, $input_step);
			
			if ($min!==false)
				$this->add_specification($name, 'min', $min);
			if ($max!==false)
				$this->add_specification($name, 'max', $max);
					
			if (is_numeric($params[step]))
				$this->add_specification($name, step, $params[step]);
					
			if (is_numeric($params[decimals]))
				$this->add_specification($name, decimals, $params[decimals]);
			else
				$this->add_specification($name, decimals, 0);
				
			if (array_key_exists('required', $params)===true)
				$this->add_specification($name, 'required', true);
			if (array_key_exists('readonly', $params)===true)
				$this->add_specification($name, 'readonly', true);
								
			$this->add_specification($name, 'show_caption', true);
		}		
		
		function add_time_input($caption, $name, $value, $min, $max, $params, $input_step=0)
		{
			if (is_int($value))	$value=date('H:i', $value);
			if (is_int($min)) $value=date('H:i', $min);
			if (is_int($max)) $value=date('H:i', $max);
			
			$this->add_field($caption, $name, 'time', $value, $input_step);
			
			if ($min!==false)
				$this->add_specification($name, 'min', $min);
			if ($max!==false)
				$this->add_specification($name, 'max', $max);
					
			if (array_key_exists('required', $params)===true)
				$this->add_specification($name, 'required', true);
			if (array_key_exists('readonly', $params)===true)
				$this->add_specification($name, 'readonly', true);
			
			$this->add_specification($name, 'show_caption', true);
		}		
		
		function add_datetime_input($caption, $name, $value, $min, $max, $params, $input_step=0)
		{
			if (is_int($value))	$value=date('Y-m-d\TH:i', $value);
			
			$this->add_field($caption, $name, 'datetime-local', $value, $input_step);
			
			if ($min!==false)
				$this->add_specification($name, 'min', $min);
			if ($max!==false)
				$this->add_specification($name, 'max', $max);
					
			if (array_key_exists('required', $params)===true)
				$this->add_specification($name, 'required', true);
			if (array_key_exists('readonly', $params)===true)
				$this->add_specification($name, 'readonly', true);
			
			$this->add_specification($name, 'show_caption', true);
		}		
		
		function add_month_input($caption, $name, $value, $min, $max, $params, $input_step=0)
		{
			$this->add_field($caption, $name, 'month', $value, $input_step);
			
			if ($min!==false)
				$this->add_specification($name, min, $min);
			if ($max!==false)
				$this->add_specification($name, max, $max);
			
			if (array_key_exists(required, $params)===true)
				$this->add_specification($name, required, true);
			if (array_key_exists(writeprotect, $params)===true)
				$this->add_specification($name, writeprotect, true);
				
			$this->add_specification($name, 'show_caption', true);							
		}

		function add_dropdown_item($name, ...$items)
		{///var_dump($items);
			foreach ($items as $item)
				$this->fields[$name]['items'][$item[value]]=[value => $item[value], display => $item[display]];
		}
		
		function remove_dropdown_item($name, ...$values)
		{
			foreach ($values as $value)
				unset($this->fields[$name]['items'][$value]);				
		}
		
		function dropdown_preselect($name, $preselect)
		{
			$this->fields[$name]['selected']=$preselect;
		}
		
		function add_readonly($caption, $name, $value, $input_step=0)
		{
			$this->fields[$name]=array('caption' => $caption, 'name' => $name, 'type' => 'readonly', 'value' => $value, 'add_input' => $input_step);
		}
		
		function boolean_dropdown($true, $false)
		{
			$items=['1' => [value => '1', display => $true], '0' => [value => '0', display => $false]];
			
			return $items;
		}
		
		function add_dropdown_from_csv($caption, $name, lib_file_handler $file, $vd, $preselect, $params, $null_display = '', $input_step=0)
		/*
		 * Deprercated, should be removed and consolidated to resource_to_dropdown and add_dropdown.
		 * CSV is file handler
		 */
		{
			$file->read('csv');
			$csv=$file->get('csv_handler');
			
			$items=[];			
			foreach ($csv['data'] as [$vd['value'] => $value, $vd['display'] => $display])
				$items[$value]=['value' => $value, 'display' => $display];
			
			$this->add_dropdown($caption, $name, $items, $preselect, $params, $null_display, $input_step);				
		}
		
		function resource_to_dropdown($resource, $map): array
		/*
		 * map is the mapping of a resource, what goes as value and what goes as display
		 */
		{
			$items=[];
			foreach ($resource['data'] as [$map[value] => $value, $map[display] => $display])
				$items[$value]=[value => $value, display => $display];
				
			return $items;
		}
		
		function add_dropdown($caption, $name, $items, $preselect, $params, $null_display = '', $input_step=0)
		//$items is an array of display and value
		{
			$this->fields[$name]=array('caption' => $caption, 'name' => $name, 'type' => 'dropdown', 'selected' => $preselect, 'add_input' => $input_step);
//			$this->fields[$name]['length']=$items;

			if ($null_display)
				$this->add_dropdown_item($name, [value => '', display => $null_display]);
			
//			foreach ($items as $item)
//			{
				$this->add_dropdown_item($name, ...array_values($items));
//			}
			
			if (array_key_exists(required, $params)===true)
				$this->add_specification($name, required, true);
			if (array_key_exists(writeprotect, $params)===true)
				$this->add_specification($name, writeprotect, true);
			if (array_key_exists(size, $params)===true)
				$this->add_specification($name, size, $params['size']);
			if (array_key_exists(multiple, $params)===true)
				$this->add_specification($name, multiple, true);
			if (array_key_exists('sort', $params)===true)
				$this->add_specification($name, 'sort', $params['sort']);
			if (array_key_exists(no_show, $params)===true)
				$this->add_specification($name, no_show, $params[no_show]);
					
			if (array_key_exists('validate', $params))
				$this->add_specification($name, 'validate', $params['validate']);
			else
				$this->add_specification($name, 'validate', true);
			
			$this->add_specification($name, 'show_caption', true);
		}
		
		static function validate_choices($needle, $haystack=[])
		/*
		 * Used to checkk if needle is in the haystack, and throws exception if it is not
		 */
		{
			$key=array_search($needle, $haystack, true);
			
			return (($key!==false) ? $haystack[$key] : throw new Exception('Incorrect validation parameters'));			
		}
		
		function validate_dropdown($name, $values, $input_step)
		{
			$result=true;
			
			if (!is_array($values))
				$values=[$values];
								
			foreach ($values as $value)
			{
				if (!isset($this->fields[$name]['items'][$value]))
				{
					$result=false;
					break;
				}
			}
			
			return $result;
		}
		
		function validate_textbased($name, $value, $input_step)
		{						
			$result=(strlen($value)<=$this->specified($name, maxlength));
			
			return $result;
		}
		
		function validate_checkbox($name, $value)
		/*
		 * OK when value is between checked or unchecked
		 */
		{			
			return (($value==$this->specified($name, 'checked')) ||
					($value==$this->specified($name, 'unchecked')));
		}
		
		function validate_time($name, $value, $input_step)
		{
			
		}
		
		function validate_number($name, $value, $input_step)
		{					
			$result=((is_numeric($value)) && 
					 (($this->specified($name, min) ?? PHP_INT_MIN)<=$value) &&
					 ($value<=($this->specified($name, max) ?? PHP_INT_MAX)));
			
			return $result;				
		}
		
		function validate_submit($name, $value, $input_step)
		{
			$result=true;
//			var_dump($this->rv_input_step[$input_step], $input_step, $value);
			if ($name==$this->submit_name)
				$test=$this->rv_input_step[$input_step];
			else 
				$test=$this->fields[$name]['value'];

			if ($test)
				$result = hash_equals($test, $value);
			else 
				$result = false;
						
			return $result;
		}
		
		function validate($name, $value, $input_step)
		/*
		 * input step is used invalidate/validate when a certain variable was conceived,
		 * it must be sychronised with the number of the input step
		 */
		{
			$result=[];
			
			if ((isset($this->fields[$name][validate])) && ($this->fields[$name]['validate']===true))
			{
				if ($this->fields[$name]['add_step']<=$input_step)
				{
					switch ($this->fields[$name]['type'])
					{
						case number:
							if (!$this->specified($name, required))
							{
								/*
								 * If not required only accept null but must be corrrect
								 */
								if ((!$this->validate_number($name, $value, $input_step)) && 
									($value!==''))
								{
									$result[$name]=$this->fields[$name][caption] ?: 'Number';									
								}
							}
							else
								if (!$this->validate_number($name, $value, $input_step))
									$result[$name]=$this->fields[$name][caption] ?: 'Number';								
								
							break;
						case dropdown:
							if (!$this->validate_dropdown($name, $value, $input_step))
								$result[$name]=$this->fields[$name][caption] ?: 'Dropdown';							
							break;						
						case submit:
							if (!$this->validate_submit($name, $value, $input_step))
								$result[$name]=$this->fields[$name][caption] ?: 'Submit';
							break;
						case text: case textarea:						
							if (!$this->validate_textbased($name, $value, $input_step))
								$result[$name]=$this->fields[$name][caption] ?: 'Text';
							break;
						case checkbox:
							if  (!$this->validate_checkbox($name, $value))
								$result[$name]=$this->fields[$name][caption] ?: 'Checkbox';
							break;
					}
				}
				else
					$result['_others']='Input Step';
			}
							
			return $result;
		}
		
		function add_checkbox($caption, $name, $value, $checked, $unchecked, $params, $input_step)
		/*
		 * Input Step required to be specified at all times due to the nature of HTML
		 * 
		 * non checked = no information transmitted, hence updateValue will not update it
		 * if inputstep is present it will revert to default value, otherwise will return to
		 * unchecked
		 */
		{
			$condition=($value==$checked) ? $checked : $unchecked;
			
			$this->add_field($caption, $name, 'checkbox', $condition, $input_step);
		
			if (array_key_exists(writeprotect, $params)===true)
				$this->add_specification($name, 'readonly', true);
			if (array_key_exists(required, $params)===true)
				$this->add_specification($name, 'required', true);
						
			$this->add_specification($name, 'unchecked', $unchecked);
			$this->add_specification($name, 'checked', $checked);
			$this->add_specification($name, validate, true);
			
			$this->add_specification($name, 'show_caption', true);						
		}

		function move_upload($name, $location=lib_form::upload_temp_location, $new_name=null)
		/*
		 * if new_name is null then automatically give it a new file name
		 * if new_name is original, then use original file name
		 * otherwise whatever value of new_name
		 */
		{			
			$status=0;
			
			$file_name='';
			$extension=$this->fields[$name]['file_type'];
			
			switch ($new_name)
			{
				case null:
					{
						$file_name=hash_file('sha3-256', $this->fields[$name]['file_name']).'.'.$extension;
						break;
					}
				case 'original' :
					{
						$file_name=$this->fields[$name]['value'];
						break;
					}
				default:
					{
						/*
						 * Warning this will change the extension
						 */
						$file_name=$new_name;
					}
			}
			$actual_filename=$location.$file_name;
			
			if (!file_exists($actual_filename))
			{
				if (move_uploaded_file($this->fields[$name]['file_name'], $actual_filename))
				{
					$status=1;
					$this->fields[$name]['actual_file']=$actual_filename;					
				}
				else
				{
					throw new Exception('Upload Transfer Error');
				}
			}
			else
			{
				/*
				 * File already exists, which is the old file. 
				 * No need to crash or die, just walk because it is already verified by SHA3 that the contents is the same
				 */
				$status=2;
				$this->fields[$name]['actual_file']=$actual_filename;
			}				
			
			$this->add_mandatory_input('_actual_upload_'.$name, $actual_filename);
			$this->select($name, 'contents')->update_filename($actual_filename);
			
			return ['status' => $status, 'filename' => $file_name];
		}
		
		function add_upload($caption, $name, $accept, $max, $params, $input_step=0)
		/*
		 * This will add an upload form
		 * use param [show_image] if its image
		 * 
		 * $accept is a class of file_handler
		 */
		{
			$this->upload_form=true;
			
			//if (!is_array($accept)) $accept=[$accept];
			$this->add_mandatory_input('_actual_upload_'.$name, '');
			
			$this->add_field($caption, $name, 'file', '', $input_step);
			
			if (array_key_exists(required, $params)===true)
				$this->add_specification($name, 'required', true);
			
			if (array_key_exists('upload_args', $params)===true)
				$this->add_specification($name, 'upload_args', $params['upload_args']);
			
			$this->add_specification($name, 'accept', $accept);
			$this->add_specification($name, 'max', $max);			
		}
		
		function html_upload($field)
		{
			if (!is_array($field))
				$field=$this->fields[$field];
			
			$params=['type' => 'file'];
			if (array_key_exists(required, $field)===true) $params['required']=null;
			
			if (count($field['accept']::mime)>0)
				$params['accept']=implode(',', $field['accept']::mime);
		
			$f_name=$this->scramble_field_name($field['name']);
							
//			$result.='<input type="file" id="'.$f_name.'" name="'.$f_name.'" '.$params.' oninput="alert('."'test'".')";>';
			
			$placeholder='';
			if (array_key_exists('upload_args', $field))
			{
				$sargs=$field['upload_args'];
				if ((isset($sargs['show_upload'])) && ($sargs['show_upload']==true))
				{
					$sargs['id']='disp_'.$f_name;
				
					$placeholder=\html::br().$field['accept']::showcase_placeholder(...$sargs);
					
					foreach ($field['accept']::loader_function($f_name, $sargs['id']) as $key => $value)
						$params[$key]=$value;
				}
			}
			
			$result=\html::input($f_name, $params);
			if ($placeholder)
				$result.=\html::br().$placeholder;
			
			return $result;
		}
		
		function html_image()
		/*
		 * This is replacement of html hidden display, but is instead showing image
		 */
		{
			
		}
		
		function add_conditional_parameters($additional_params)
		{
			foreach ($additional_params as $field => $param)
			{
				$this->add_specification($field, $param, true);
			}
		}
		
		function textbased_parameters($field)
		/*
		 * Parameters for number, date, etc
		 */
		{
			if (!is_array($field))
				$field=$this->fields[$field];
					
			$params='';
			if (($field['type']=='date') || ($field['type']=='daterange') || ($field['type']=='month') || 
				($field['type']=='number') || ($field['type']=='currency') || ($field['type']=='range'))
			{
				if ($field['min']) $params.=' min="'.$field['min'].'"';
				if ($field['max']) $params.=' max="'.$field['max'].'"';
			}
			
			if (isset($field['maxlength'])) $params.=' maxlength="'.$field['maxlength'].'"';
			if (isset($field['minlength'])) $params.=' minlength="'.$field['minlength'].'"';
			if (isset($field['placeholder'])) $params.=' placeholder="'.$field['placeholder'].'"';
			if ($this->specified($field['name'], required)) $params.=' required';
			if ($this->specified($field['name'], writeprotect))	$params.=' readonly';
			if (isset($field['pattern'])) $params.=' pattern="'.$field['pattern'].'"';		
			if (isset($field['form'])) $params.=' form="'.$this->name.'"';
			if (isset($field['hint'])) $params.=' title="'.$field['hint'].'"';
			if (isset($field['list'])) $params.=' list="'.$field['list'].'"';
			
			if ($this->specified($field['name'], decimals))	$params.=' decimals="'.$field[decimals].'"';
			if ($this->specified($field['name'], step)) $params.=' step="'.$field[step].'"';
										
			return $params;
		}
		
		function html_password($field)
		{
			return $this->std_field_toString($field);
		}
		
		function std_field_toString($field, ...$addon_params)
		{
			if (!is_array($field))
				$field=$this->fields[$field];
					
			$params=$this->textbased_parameters($field);
				
			foreach ($addon_params as $a_params)
				if ((isset($field[$a_params])) && ($field[$a_params]!=''))
					$params.=' '.$a_params.'="'.$field[$a_params].'"';
			
			if (($field['caption']) && ($field['show_caption']))
				$result=$this->get_caption($field);
			else
				$result='';
				
			$result.='<input name="'.$this->scramble_field_name($field['name']).'" type="'.$field['type'].'" value="'.$field['value'].'"'.$params.'>';
			
			return $result;
		}
		
		function html_readonly($field)
		{
			$result='';
			if (!is_array($field))
				$field=$this->fields[$field];

			if (($field['caption']) && ($field['show_caption']))			
				$result=$this->get_caption($field).'<br>';
					
			return $result.$field['value'];
		}

		function html_textarea($field, $readonly=false)
		{
			if (!is_array($field))
				$field=$this->fields[$field];
					
			if (($field['caption']) && ($field['show_caption']))
				$result=$this->get_caption($field).'<br>';
			
			$params='';
			
			if ($field['size']) 
			{
				$params.=' cols="'.$field['size'].'"';
				if ($field['maxlength']) $params.=' maxlength="'.$field['maxlength'].'"';
					$params.=' rows="'.round(ceil($field['maxlength']/$field['size'])).'"';
			}
			if ($this->specified($field['name'], maxlength)) $params.=' maxlength="'.$field['maxlength'].'"';
			if ($this->specified($field['name'], required))	$params.=' required';
			if (($this->specified($field['name'], writeprotect)) || ($readonly)) $params.=' readonly';
			if ($this->specified($field['name'], placeholder))	$params.=' placeholder="'.$field['placeholder'].'"';
			if ($this->specified($field['name'], wrap))	$params.=' wrap="'.$field['wrap'].'"';
			if ($field['form']) $params.=' form="'.$this->name.'"';
			
			return $result.'<textarea name="'.$this->scramble_field_name($field['name']).'" '.$params.'>'.$field['value'].'</textarea>';
		}
				
		function html_dropdown($field)
		{
			if (!is_array($field))
				$field=$this->fields[$field];
			
			if ($this->specified($field['name'], writeprotect))
			{
				$result=$this->html_hidden_display($field);
			}
			else
			{			
				$params="";
				if ($this->specified($field['name'], disabled)) $params.=' disabled';
				if ($this->specified($field['name'], required)) $params.=' required';
				if ($this->specified($field['name'], size)) $params.=' size='.$field['size'];
				if ($this->specified($field['name'], multiple))
				{
					$params.=' multiple';
					$field_name=$this->scramble_field_name($field['name']).'[]';
				}
				else 
				{
					$field_name=$this->scramble_field_name($field['name']);
				}
				
				if ($field['form']) $params.=' form="'.$this->name.'"';
				
				if (($field['caption']) && ($field['show_caption']))										
					$result=$field['size'] ? $result=$this->get_caption($field).'<br> ' : $result=$this->get_caption($field);
				else
					$result='';
						
				$result.='<select name="'.$field_name.'"'.$params.'>';
					
				$selection=is_array($field['selected']) ? array_flip($field['selected']) : [$field['selected'] => 0];
				
				$items=[];
				if ($sort_function=$field['sort'])
				{
					$temp=[];
					$null_found=false;
					foreach ($field['items'] as ['value' => $value, 'display' => $display])
						if ($value)
							$temp[$value]=$display;
						else 
							$null_found=$display;

					if (($sort_function!=='arsort') && ($sort_function!=='asort'))
						$sort_function='asort';
						
					$sort_function($temp, SORT_NATURAL);
					
					if ($null_found!==false)
						$items[]=['value' => "", 'display' => $null_found];
					
					foreach ($temp as $value => $display)
						$items[]=['value' => $value, 'display' => $display];
				}
				else 
					$items=$field['items'];
				
				foreach ($items as $item)
				{	
					$show=true;
					/*
					 * The selection has to be manually cleared if you wanted to use this for
					 * the next field
					 */
					if (!isset($selection[$item[value]]))
						$show=!in_array($item[value], $field[no_show] ?? []);

					if ($show)
					{
						$selected=isset($selection[$item[value]]) ? " selected" : '';
						
						$result.='<option value="'.$item[value].'"'.$selected.'>'.$item['display'].'</option>';
					}
				}
				$result.='</select>';
			}
			
			return $result;
		}
		
		function html_dropdown_display($field)
		/*
		 * You can call this directly for dropdown display, but calling html_hidden_display is okay too
		 */
		{
			if (!is_array($field))
				$field=$this->fields[$field];
						
			$values=[];
			
			$selected=$field['selected'];
			if (!is_array($selected)) $selected=[$selected];
			
			foreach ($selected as $selection)
				if ($field['items'][$selection])
					$values[]=$field['items'][$selection]['display'];
				else
					$values[]=$selection;//fill with the value
			
			return implode(', ', $values);
		}
		
		function array_to_advanced_params($params)
		{
			$result='';
			foreach ($params as $key => $param)
			{
				if (!empty($param))
				{			
					$subresult='';
					foreach ($param as $statement)
					{
						$subresult.=$statement.';';
					}
					$result.=' '.$key.'="'.$subresult.'"';
				}
			}
			
			return $result;
		}
		
		function html_submit($field=null, $set_autohide=false)
		{
			if (!$field)
				$field=$this->submit_name;
			
			if (!is_array($field))
				$field=$this->fields[$field];
			
			if (($field[value]=='Confirm') && ($set_autohide===false)) $set_autohide=true;
			
			$param_array=array();
			
			$params='';
			if ((isset($field[disabled])) && ($field[disabled]))
				$params.=' disabled';
						
			if ((isset($field['confirmation'])) && ($field['confirmation']))
			{				
				$confirmation_string='confirmation=confirm('."'".$field['confirmation']."'".')';
				$param_array['onclick'][]=$confirmation_string;				
			}
			else 
			{
				$param_array['onclick'][]='confirmation=true';
			}
			
			if ((isset($field['auto_hide'])) && (($field['auto_hide']) && ($set_autohide)))
				$param_array['onclick'][]="if (confirmation) document.getElementById('".$this->scramble_field_name($field[name])."').hidden=true";
			
			if (isset($field['confirmation']))
			{
				$param_array['onclick'][]='return confirmation';
			}
			if ($field['form']) $params.=' form="'.$this->name.'"';						
				
			if (isset($field[hint]))
				$params.=$field[hint] ? ' title="'.$field[hint].'"' : '';
			
			$params.=$this->array_to_advanced_params($param_array);
				
			if ($field[caption])
				$result=$this->get_caption($field);
			else
				$result='';
			
//			if ($this->valid_input==1)
			$result.='<input type="submit" id="'.$this->scramble_field_name($field['name']).'" name="'.$this->scramble_field_name($field['name']).'" value="'.$field[value].'"'.$params.'>';
			/*
			 * Do not add back button automatically, some form might hate it
			 */
			return $result;
		}
		
		function html_button($field=null, $set_autohide=false)
		{
			if (!$field)
				$field=$this->submit_name;
				
			if (!is_array($field))
				$field=$this->fields[$field];
					
			if (($field['value']=='Confirm') && ($set_autohide===false)) $set_autohide=true;
					
			$param_array=array();
					
			$params='';
			if ((isset($field['disabled'])) && ($field['disabled']))
				$params.=' disabled';
						
			if ((isset($field['confirmation'])) && ($field['confirmation']))
			{
				$confirmation_string='confirmation=confirm('."'".$field['confirmation']."'".')';
				$param_array['onclick'][]=$confirmation_string;
			}
			else
			{
				$param_array['onclick'][]='confirmation=true';
			}
						
			if ((isset($field['auto_hide'])) && (($field['auto_hide']) && ($set_autohide)))
				$param_array['onclick'][]="if (confirmation) document.getElementById('".$this->scramble_field_name($field['name'])."').hidden=true";
							
			if (isset($field['confirmation']))
			{
				$param_array['onclick'][]='return confirmation';
			}
			if ($field['form']) $params.=' form="'.$this->name.'"';
							
			if (isset($field['hint']))
				$params.=$field['hint'] ? ' title="'.$field['hint'].'"' : '';
						
			if ((isset($field['onclick'])) && ($field['onclick']))
			{
				$param_array['onclick'][]=$field['onclick'];
			}
			
			$params.=$this->array_to_advanced_params($param_array);
			
			if ($field['caption'])
				$result=$this->get_caption($field);
									
			$result.='<input type="button" id="'.$this->scramble_field_name($field['name']).'" name="'.$this->scramble_field_name($field['name']).'" value="'.$field['value'].'"'.$params.'>';
					
			return $result;
		}
		
		function html_div($field)
		{
			if (!is_array($field))
				$field=$this->fields[$field];
				
			$name=$field['name'];
			$value=$field['value'];
			$result='<div id="'.$name.'">'.$value.'</div>';
				
			return $result;
		}
				
		function html_hidden($field)
		{
			if (!is_array($field))
				$field=$this->fields[$field];
							
			if ($this->get_global('settings')->select('encrypt_hidden_parameters')==0)
				$result=$this->html_hidden_codes($field);
			else
				$this->hidden_fields[]=$field['name'];
				
			return $result ?? null;
		}
		
		function html_hidden_codes($field)
		{
			if (!is_array($field))
				$field=$this->fields[$field];
			$params='';
			if ($field['form']) $params.=' form="'.$this->name.'"';
			$s_field=$this->scramble_field_name($field['name']);
			switch ($field['type'])
			{
				case 'dropdown':
					if (!is_array($field['selected']))
						$result.="<input type='hidden' name='".$s_field."' id='".$s_field."' value='".$field['selected']."' ".$params.">";
					else
					{
						$selected=$field['selected'];
						foreach ($selected as $value)
							$result.="<input type='hidden' name='".$s_field."[]' id='".$s_field."' value='".$value."' ".$params.">";
					}
					break;
				case 'file':
					$value=$field['value'];
					$result="<input type='hidden' name='".$s_field."' id='".$s_field."' value='".$value."' ".$params.">";
					break;
				case 'number':
					$value=$field['value'];
					
					if (($field['units']=='percent') || ($field['units']=='%'))
						$value=$value*100;
					
					$result="<input type='hidden' name='".$s_field."' id='".$s_field."' value='".$value."' ".$params.">";
					break;
				case 'password':
					$value=$field['value'];
					$result="<input type='hidden' name='".$s_field."' id='".$s_field."' value='".$value."' ".$params.">";
					$this->warning='Password is visible with when setting encrypt_hidden_parameters is set to 0';
					break;
				default:
					$value=$field['value'];
					$result="<input type='hidden' name='".$s_field."' id='".$s_field."' value='".$value."' ".$params.">";
					break;
			}
			
			return $result;
		}
		
		function html_checkbox($field)
		{			
			if (!is_array($field))
				$field=$this->fields[$field];
			
			$params="";
			$name=$field['name'];
			
			if ($field['readonly'])
				$params.=' disabled';
			if ($field['required'])
				$params.=' required';
					
			if ($field['value']==$this->specified($name, 'checked'))
				$params.=' checked';
			
			$value=$field['checked'];
				
			if ($field['form']) $params.=' form="'.$this->name.'"';
				
			$result="<input type=\"checkbox\" name=\"".$this->scramble_field_name($field['name'])."\" value=\"$value\" $params>";
			
			if ($field['caption'])
				$result.=$field['caption'];
			
			return $result;
		}
		
		function html_numeric_format($field, $field_name='placeholder')
		{
			$decimal=0;
			if (($field['step']>0) && ($field['step']<1))
				$decimal=strlen($field['step'])-2;
				
			$placeholder=number_format($field[$field_name], $decimal);
			
			return $placeholder;	
		}
		
		function html_currency($field)
		{
			if (!is_array($field))
				$field=$this->fields[$field];
					
			$params='';
			if ($field['min']) $params.=' min="'.$this->html_numeric_format($field, 'min').'"';
			if ($field['max']) $params.=' max="'.$this->html_numeric_format($field, 'max').'"';
			
			if ($field['placeholder']!='') 
			{
				if (is_numeric($field['placeholder']))
				{							
					$placeholder=$this->html_numeric_format($field, 'placeholder');
/*					setlocale(LC_MONETARY, $field['currency']);
					$placeholder=money_format('%i', $field['placeholder']);
					setlocale(LC_MONETARY, default_locale);*/					
				}
				else 
				{					
					$placeholder=$field['placeholder'];
				}
				
				$params.=' placeholder="'.$placeholder.'"';
			}
			if ($this->specified($field['name'], required))	$params.=' required';
			if ($this->specified($field['name'], writeprotect)) $params.=' readonly';
			if ($this->specified($field['name'], pattern)) $params.=' pattern="'.$field['pattern'].'"';
			if ($this->specified($field['name'], step)) $params.=' step="'.$field['step'].'"';
			if ($field['form']) $params.=' form="'.$this->name.'"';
			
			if (($field['caption']) && ($field['show_caption']))
				$result=$this->get_caption($field);
			else
				$result='';
			
			if (($field['readonly']) && (!$field['value']))
				$toDisplay=0;
			else 
				$toDisplay=$field['value'];
			
			$result.='<input name="'.$this->scramble_field_name($field['name']).'" type="number" value="'.$toDisplay.'"'.$params.'>';
			return $result;
		}
		
		function html_currency_display($field)
		{
			if (!is_array($field))
				$field=$this->fields[$field];
					
			$result='';
			if ($field['value'])
				$toDisplay=$field['value'];
			else
				$toDisplay=0;

			$result=finance::money_format($field['currency'], $toDisplay);
			
			return $result;
		}
		
		function html_datetime_display($field)
		{
			if (!is_array($field))
				$field=$this->fields[$field];
			
			$result=$this->date_string_change_format($field['value'], 'Y-m-d\TH:i', 'Y-m-d H:i');
			return $result;
		}
		
		function prepare_HTML_event($field)
		{
			$param_array=[];//var_dump($field); die;
			
			if ((isset($field['onclick'])) && ($field['onclick']))
				$param_array['onclick'][]=$field['onclick'];
				
			if ((isset($field['onchange'])) && ($field['onchange']))
				$param_array['onchange'][]=$field['onchange'];

			if ((isset($field['onfocus'])) && ($field['onfocus']))
				$param_array['onfocus'][]=$field['onfocus'];

			if ((isset($field['onblur'])) && ($field['onblur']))
				$param_array['onblur'][]=$field['onblur'];
					
			return $param_array;
		}
		
		function html_number($field)
		{
		    if (!is_array($field))
		        $field=$this->fields[$field];
		        
		    $params='';
		    if (!is_null($this->specified($field['name'], min))) $params.=' min="'.$field[min].'"';
		    if (!is_null($this->specified($field['name'], max))) $params.=' max="'.$field[max].'"';
		    
		    if ($this->specified($field['name'], 'placeholder')!='')
		    {
		        if (is_numeric($field['placeholder']))
		        {		           
		        	$placeholder=$this->html_numeric_format($field, 'placeholder');		        
		        }
		        else
		            $placeholder=$field['placeholder'];
		              
		        $params.=' placeholder="'.$placeholder.'"';
		    }
		    if ($this->specified($field['name'], required)) $params.=' required';
		    if ($this->specified($field['name'], writeprotect)) $params.=' readonly';
		    if ($this->specified($field['name'], decimals))	$params.=' decimals="'.$field[decimals].'"';
		    if ($this->specified($field['name'], pattern)) $params.=' pattern="'.$field[pattern].'"';
		    if ($this->specified($field['name'], step)) $params.=' step="'.$field[step].'"';
		    if ($this->specified($field['name'], size)) $params.=' size="'.$field[size].'"';
		    
		    if ($this->specified($field['name'], form)) $params.=' form="'.$this->name.'"';
		    
		    if (($field[caption]) && ($field['show_caption']))
		        $result=$this->get_caption($field);
		    else
		        $result='';
		   
		    $param_array=$this->prepare_HTML_event($field);
		   			
	        $params.=$this->array_to_advanced_params($param_array);
	        
	        $toDisplay='';
	        if ($field['value'])
	        	$toDisplay=$field['value'];
	        		
	        switch ($field['units'])
	        {
	        	case 'percent': case '%' :
	        		if (is_numeric($toDisplay))
	        			$toDisplay=$toDisplay*100;
	        		
	        		break;
	        	default:
	        		$toDisplay=$toDisplay;
	        		break;
	        }	        
		                
	        $result.='<input name="'.$this->scramble_field_name($field['name']).'" id="'.$field['name'].'" type="number" value="'.$toDisplay.'"'.$params.'>';
		    return $result;
		}
		
		function html_number_display($field)
		{
		    if (!is_array($field))
		        $field=$this->fields[$field];
		        
		    $result='';
		    if ($field['value'])
		        $toDisplay=$field['value'];
		 		    
		    if (is_numeric($toDisplay))
			    switch ($field['units'])
			    {
			        case 'percent': case '%' : 
			            $result=number_format($toDisplay*100, $field['decimals']).'%';
			            break;
			        default:
			            $result=number_format($toDisplay, $field['decimals']).$field['units'];
			            break;
			    }
		    else 
		    	$result='&nbsp;';
            		    
		    return $result;		                
		}
		
		function html_datalist($name)
		{
			$items=$this->fields[$name]['items'];
			
			$result='<datalist id="'.$this->fields[$name]['list'].'">';
			foreach ($items as $item)
				$result.='<option '.value.'="'.$item.'">';
			
			$result.='</datalist>';
			
			return $result;
		}
		
		function html_text($field)
		{				
			if (is_array($field))
				$name=$field['name'];
			else
			{
				$name=$field;
				$field=$this->fields[$field];			
			}
			
			$result=$this->std_field_toString($field);
			
			if ($this->fields[$name]['list'])
			{
				$datalist=$this->html_datalist($name);
				$result.=$datalist;
			}
			
			return $result;
		}
		
		function html_month($field)
		{
			if (!is_array($field))
				$field=$this->fields[$field];
									
			$params=$this->textbased_parameters($field);
												
			if (($field['caption']) && ($field['show_caption']))
				$result=$this->get_caption($field);
			else
				$result='';
									
			if (is_int($field[value]))
				$value=$this->date($field[value], 'Y-m');
			else
				$value=$field[value];
			
			$result.='<input name="'.$this->scramble_field_name($field['name']).'" type="'.$field['type'].'" value="'.$value.'"'.$params.'>';
									
			return $result;
		}
		
		function html_date($field)
		{
			if (!is_array($field))
				$field=$this->fields[$field];
			
			return $this->std_field_toString($field);			
		}
		
		function html_daterange($field)
		{
			if (!is_array($field))
				$field=$this->fields[$field];
				
			$params=$this->textbased_parameters($field);
										
			if (($field['caption']) && ($field['show_caption']))
				$result=$this->get_caption($field);
			else
				$result='';
			
			$begin=$this->date($field['value']['begin']);
			$end=$this->date($field['value']['end']);
			
			$result.='<input name="'.$this->scramble_field_name($field['name'].'_begin').'" type="date" value="'.$begin.'"'.$params.'>';
			$result.='&nbsp;';
			$result.='<input name="'.$this->scramble_field_name($field['name'].'_end').'" type="date" value="'.$end.'"'.$params.'>';
//			$result.=$this->html_hidden($field['name']);
			
			return $result;								
		}
		
		function html_range($field)
		{
			if (!is_array($field))
				$field=$this->fields[$field];
				
			$params=$this->textbased_parameters($field);
				
			if (($field['caption']) && ($field['show_caption']))
				$result=$this->get_caption($field);
			else
				$result='';
						
			$begin=$field['value']['begin'];
			$end=$field['value']['end'];
			
			$result.='<input name="'.$this->scramble_field_name($field['name'].'_begin').'" type="number" value="'.$begin.'"'.$params.'>';
			$result.='&nbsp;';
			$result.='<input name="'.$this->scramble_field_name($field['name'].'_end').'" type="number" value="'.$end.'"'.$params.'>';
						//			$result.=$this->html_hidden($field['name']);
						
			return $result;
		}
				
		function html_time($field)
		{
			if (!is_array($field))
				$field=$this->fields[$field];
				
			return $this->std_field_toString($field, 'min', 'max');
		}		
		
		function html_datetime($field)
		{
			if (!is_array($field))
				$field=$this->fields[$field];
				
			return $this->std_field_toString($field);				
		}
		
		function html_hidden_display($field, $redress='')
		/*
		 * This will display a value with a HTML hidden input behind it 
		 * 
		 * $redress is a variable to redress override the visible value
		 */
		{
			if (!is_array($field))
				$field=$this->fields[$field];
			
			$result='';
			if (($field['caption']) && ($field['show_caption']))
				$result=$this->get_caption($field);
			
			if ($field['display'])
				$result.=$field['display'].$this->html_hidden($field);
			else
			{
				if ($redress=='')
					switch ($field['type'])
					{
						case 'currency':
							$result.=$this->html_currency_display($field);
							break;
						case 'number':
							$result.=$this->html_number_display($field);
							break;
						case 'dropdown':
							/*if (is_numeric($field['selected']))
								$selected=(integer) $field['selected'];
							else
								$selected=$field['selected'];
							var_dump($selected);
							var_dump($field['items']);*/
							$result.=$this->html_dropdown_display($field);
							break;
						case 'textarea':
							if ($field['caption'])
								$result.='<br>';
							$result.=$field['value'];
							break;
						case 'datetime-local':
							$result=$this->html_datetime_display($field);
							break;
						case 'file':
							$result.=$field['value'];
							break;
						case 'month':
							$result.=$this->date($field['value'], 'Y-m');
							break;
						case 'password':
							$result.=substr($field['value'], 0, 1).str_pad('', strlen($field['value'])-2, '*').substr($field['value'], -1, 1);
							break;
						case 'daterange':
							$result.=\date::date($field['value']['begin']).'-'.\date::date($field['value']['end']);
							break;
						default:
							$result.=$field['value'];
							break;
					}
				else
					$result.=$redress;
				
				$result.=$this->html_hidden($field);
			}
				
			return $result;
		}
		
		function html_file_display($field)
		{
		
		}
				
		function html_auto_input($field, $show_caption=false)
		{
			if (!is_array($field))
				$field=$this->fields[$field];
			
			$caption=$field['caption'];
			if (!$show_caption)	$field['caption']='';
			
			if ($this->input_step>=$field['add_step'])
				switch ($field['type'])
				{
					case 'textarea':
						$result.=$this->html_textarea($field);
						break;
					case 'dropdown':
						$result.=$this->html_dropdown($field);
						break;
					case 'submit':
						$result.=$this->html_submit($field);
						break;
					case 'hidden': case 'credential':
						$result.=$this->html_hidden($field);
						break;
					case 'currency':
						$result.=$this->html_currency($field);
						break;
					case 'number':
						$result.=$this->html_number($field);
						break;
					case 'reserved': break;
					case 'file':
						$result.=$this->html_file($field);
					default :
						$result.=$this->std_field_toString($field);
						break;
				}
			else 
				$result.=$this->html_hidden_display($field);

			if (!$show_caption)	$field['caption']=$caption;
			
			return $result;
		}
		
		function toStringFormat($delimeter='&nbsp;', $inparagraph=false)
		{
			if ($inparagraph)
			{
				$pbegin='<p>';
				$pend='</p>';
			}
			else
			{
				$pbegin='';
				$pend='';
			}
			
			$result=$this->form_open().$pbegin;
			
			foreach ($this->fields as $field)
				if (isset($field['name']))
				{
					switch ($field['type'])				
					{
						case 'textarea':
							$result.=$this->html_textarea($field).$delimeter;
							break;
						case 'dropdown':
							$result.=$this->html_dropdown($field).$delimeter;
							break;
						case 'submit':
							$result.=$this->html_submit($field).$delimeter;
							break;					
						case 'hidden':  
							$result.=$this->html_hidden($field);
							break;			
						case 'currency':
							$result.=$this->html_currency($field).$delimeter;
							break;
						case 'number':
							$result.=$this->html_number($field).$delimeter;
							break;
						case 'month':
							$result.=$this->html_month($field).$delimeter;
							break;
						case 'reserved': break;
						case 'credential':
							$result.=$this->html_credential();
							break;
						case 'file':
							$result.=$field['file_name'].$delimeter;
							break;
						case 'readonly':
							$result.=$this->html_readonly($field).$delimeter; 
							break;
						default :
							$result.=$this->std_field_toString($field).$delimeter;
							break;
					}
				}
			$result.=$pend.$this->form_close();
			
			return $result;
		}			
		
		function scramble_field_name($name)
		/*
		 * This method is used to scramble field name and must be used when adding new input field
		 */
		{					
			return $this->credentials->scramble_input($name, $this->fields[$name]['type']);
		}
				
		function get_input($input, $name)
		/*
		 * Deprecated
		 */
		{
			$result=$input[$name] ?: $input[$this->scramble_field_name($name)];
			
			return $result;
		}
				
		function descramble_field_name($datasource)
		/*
		 * deprecated, but will not remove as it might still be usable
		 */
		{
			if ($this->scramble_field_name===false)
				$result=$datasource;
			else
			{
				$scrambled=[];
				foreach ($this->fields as ['name' => $name])
					if (isset($name))
						$scrambled[$this->scramble_field_name($name)]=$name;
				
				$result=[];
				foreach ($datasource as $s_name => $value)
					if (isset($scrambled[$s_name]))
						$result[$scrambled[$s_name]]=$value;
					else
						$result[$s_name]=$value;
			}
		
			return $result;
		}
				
		function updateValue($datasource, ...$fields)
		/*
		 * Updating all values on the fields, according to fields
		 * if fields is empty then update everything, which can be inefficient
		 */
		{		
			$result=[];
			
			$f=[];
			if (!empty($fields))
			{
				/*
				 * Update only as needed / directed
				 */
				foreach ($fields as $name)
					$f[$name]=$this->fields[$name];
			}
			else
				$f=$this->fields;
			//var_dump($datasource);
			foreach ($f as $name => $field)
			{
				if (isset($datasource[$name]) && ($datasource[$name]!==null)) //original ($datasource[$name]!="")
				{//var_dump($datasource);
					
					/*
					 * Validation
					 */
					$valid=$this->validate($name, $datasource[$name], $datasource['_input_step'] ?? 0);

					if (empty($valid))
					{
						switch ($field['type'])
						{
							case 'number': 
								{
									switch ($field['units'])
									{
										case 'percent': case '%':
											$this->fields[$name]['value']=$datasource[$name]/100;											
										break;
										default:
											$this->fields[$name]['value']=$datasource[$name];
										break;
									}
									break;
								}
							case 'submit':
								{
									if ($this->fields[$name]['value']==$datasource[$name])
										$this->fields[$name]['submit']=true;
									break;								
								}
							case 'dropdown':
								{															
									$this->fields[$name]['selected']=$datasource[$name];
									$this->fields[$name]['value']=$datasource[$name];
							
									/*									if (!is_array($datasource[$name]))
										$selected=[$datasource[$name]];
									else
										$selected=$datasource[$name];
												
									$display=[];
									foreach ($selected as $selection)
										$display[]=$this->fields[$name]['items'][$selection]['display'];
													
									$this->fields[$name]['display']=implode(', ', $display);*/												
	
								break;
								}
							case 'checkbox':
								{
									$this->fields[$name]['value']=$datasource[$name];
									break;
								}
							case 'month':
								{
									if (is_numeric($datasource[$name]))
										$this->fields[$name]['value']=date('Y-m', $datasource[$name]);
									else
										$this->fields[$name]['value']=$datasource[$name];
									break;
								}							
							case 'date':
								{
									if (is_numeric($datasource[$name]))
										$this->fields[$name]['value']=date('Y-m-d', $datasource[$name]);
									else
										$this->fields[$name]['value']=$datasource[$name];
									break;
								}
							case 'daterange_begin': case 'daterange_end':
								{
								/*
								Beta and Untested
								*/									
									if (is_numeric($datasource[$name]))
										$this->fields[$name]['value']=date('Y-m-d', $datasource[$name]);
									else
										$this->fields[$name]['value']=$datasource[$name];
									
									$dr=$this->fields[$name]['daterange'];
									
									if ($field['type']=='daterange_begin')
										$this->fields[$dr]['value']['begin']=$this->fields[$name]['value'];
									if ($field['type']=='daterange_end')
										$this->fields[$dr]['value']['end']=$this->fields[$name]['value'];
									
									break;											
								}								
							case 'time':
								{
									if (is_int($datasource[$name]))
										$this->fields[$name]['value']=date('H:i', $datasource[$name]);
									else
										$this->fields[$name]['value']=$datasource[$name];
									break;
								}
							case 'datetime-local':
								{
									if (is_int($datasource[$name]))
										$this->fields[$name]['value']=date('Y-m-d\TH:i', $datasource[$name]);
									else
										$this->fields[$name]['value']=$datasource[$name];
									break;
								}
							case 'credential'://var_dump($datasource[$name]);
								if ($name=='_input_session')
									if ($datasource[$name]!='')
									{
										$this->credentials->set_input_session($datasource[$name]);
										$this->fields[$name]['value']=$datasource[$name];
									}
								break;
							case 'file': 
								{										
									if (is_string($datasource[$name]))
									{
										/*
										 * Second Step Upload
										 */
										$this->fields[$name]['value']=$datasource[$name];
										
										//$file_name=self::upload_temp_location.$datasource[$name];
										$file_name=$this->select("_actual_upload_".$name);
										
										$mime=pathinfo($file_name, PATHINFO_EXTENSION);										
									}
									else
									{
										/*
										 * First Step Upload
										 */
										$this->fields[$name]['value']=$datasource[$name]['name'];
										
										$file_name=$datasource[$name]['tmp_name'];
										$mime=pathinfo($datasource[$name]['full_path'], PATHINFO_EXTENSION);
									}
									
									$this->fields[$name]['size']=filesize($file_name);
									
									$this->fields[$name]['file_type']=$mime;
									$this->fields[$name]['file_name']=$file_name;
									$this->fields[$name]['file_signature']=hash_file('sha3-256', $file_name);
									
									$this->fields[$name]['valid']=(($this->fields[$name]['valid']=$this->fields[$name]['size']<=$this->fields[$name]['max'])
										&& (array_search($mime, $this->fields[$name]['accept']::mime)!==false));
									
									if ($this->fields[$name]['valid'])
									{
										$this->fields[$name]['contents']=new lib_file_handler($file_name, $mime, 'r');
//										Preparer the file handler but do not read!
//										$this->fields[$name]['contents']->read($mime);
									}
								}
								break;
							default:
								{
									/*
									 * Text and String
									 */
									$this->fields[$name]['value']=$datasource[$name];
									break;
								}
						}
					}
					else
					{
						$result=$valid;
					}
				}
				else
				{
					switch ($field['type'])
					{
					/*
					 * For initial assesment this will check if its actually valid or not
					 */
						case 'checkbox':
						{
							if ($this->fields[$name]['add_step']>$this->input_step)
								$this->fields[$name][value]=$this->fields[$name]['unchecked'];
							else
								$this->fields[$name][value]=$this->fields[$name]['default'];
							
							break;
						}
						case 'dropdown':
						{
							$this->fields[$name][value]=null;
							if (!isset($this->fields[$name][selected]))
								$this->fields[$name][selected]=null;
							break;
						}
					}
				}
			}
			
			return $result;
		}
		
		function asArray()
		{
			$result=array();
			foreach ($this->fields as $name => $field)
				if (isset($field['name']))
				{
					switch ($field['type'])
					{
						case 'credential': case 'reserved' : case 'submit': break;					
						case 'dropdown': $result[$name]=$field['selected']; break;					
						default: $result[$name]=$field['value'];
					}
	/*				if ($field['type']!='reserved')
					{
						if ($field['type']!='credential')
							if ($field['type']=='dropdown')
								$result[$name]=$field['selected'];
							else
								if ($field['type']!='submit')
		 							$result[$name]=$field['value'];
					}*/
				}
			
			return $result;
		}		
		
		function editfield($name, $value)
		{
			$this->set($name, $value);
		}
		
		function set($name, $value)
		/*
		 * This function will call edit_field, and defaults the variable to 'value'
		 */
		{
			if ($this->fields[$name]['type']=='dropdown')
			{
				$this->edit_field($name, 'selected', $value);
			}
			
			$this->edit_field($name, 'value', $value);
		}
		
		function edit_field($name, $variable, $value)
		/*
		 * This will be relegated to _set
		 * Add more edit_field later on to make it compatible
		 */
		{
			switch ($this->fields[$name]['type'])
			{
				case 'date' : case 'daterange_begin': case 'daterange_end':
					if (is_integer($value))
						$this->fields[$name][$variable]=$this->date($value, 'Y-m-d');
					else
						$this->fields[$name][$variable]=$value;
					break;						
				default:
					$this->fields[$name][$variable]=$value;
					break;
			}
			
			if (($this->rv_dataholder_name!='') && ($variable=='value'))
				if (is_subclass_of($this->dataholder(), 'record'))
				/*
				 * This might cause some funny behaviour because it will only reflect the update on data holder
				 * when the data holder explicitly have a placeholder.  E.g. anything, but null  
				 */
				{
					if (!is_null($this->dataholder($name)))
						$this->dataholder()->set($name, $value);
				}
		}
				
		function get_dataholder()
		{
			if ($this->rv_dataholder_name!='')
				return $this->{$this->rv_dataholder_name};
		}
		
		function set_dataholder($name, $dataholder)
		/*
		 * Might need to add dataholder name for compatibility
		 * just check how it goes
		 * 
		 * $name is just for compatibility 
		 */
		{
			if (is_subclass_of($dataholder, 'record'))
			{
				$this->rv_dataholder_name='rv_dataholder';
				$this->rv_dataholder=$dataholder;
				
				$dataholder->set_form($this);
			}
		}
		
		function sync_from_dataholder(...$restriction)
		/*
		 * please use dataholder_synchronise for anything that does not
		 * need restriction
		 * 
		 * exists method will check if this dataholder existed in dataase, when set to true
		 * it will reload from the database
		 */
		{
			if ($this->dataholder()->exists(true))
			{
				//load dataholder and sync its contents
				$data=$this->dataholder()->asArray();
				foreach ($data as $field => $value)
				{
					$field_sync=true;
					
					foreach ($restriction as $restricted)
						if (mb_strpos($field, $restricted)===0)
						{
							$field_sync=false;
							break;
						}
					
					if ($field_sync) $this->editfield($field, $value);
				}
			}
		}	
				
		function dataholder(...$variable)
		{			
			if ($this->rv_dataholder_name)
			{
				$dataholder=$this->{$this->rv_dataholder_name};
				
				if (!empty($variable))
					$result=$dataholder->select(...$variable);
				else 
					$result=$dataholder;
			}
			else
				$result=false;
			
			return $result;
		}
		
		function f_pipe($function_name, ...$parameters)
		/*
		 * Used to pipe to dataholder
		 */
		{
			$result=null;
			
			if (($this->dataholder()) && (method_exists($this->dataholder(), $function_name)))
			{
				$result=$this->dataholder()->{$function_name}(...$parameters);
			}
			
			return $result;
		}
		
		function dataholder_execute_function($function_name, ...$parameters)
		/*
		 * alias to f_pipe
		 */
		{
			return $this->f_pipe($function_name, ...$parameters);
		}
		
		function getfield($name, $field)
		/*
		 * This will be relegated to _get
		 */
		{
			if (isset($this->fields[$name]))
				return $this->fields[$name][$field];
			else 
				null;
		}
		
		function getvalue($name)
		{
			return $this->getfield($name, 'value');
		}
		
		function getdisplay($name)
		{
			return $this->getfield($name, 'display');
		}
		
		use t_layouter;
		
		function to_layout_name($name)
		/*
		 * This will convert the name to a layout name
		 * e.g. padded
		 */
		{	
			$result=null;
			
			if (isset($this->fields[$name]))
			{
				$result=$name;
				
				if ($this->fields['multi_input']=='1')
				{
					if ($this->fields[$name]['multi_input'])
						$result=$this->name.' '.$result;
				}
					
				$result=layout_maker::to_layout_name($result);				
			}
			else
				$result=layout_maker::to_layout_name($name);
			
			return $result;
		}
		
		function select($name, $field='value')
		{
			$result=null;

			switch ($this->fields[$name]['type'])
			{
				case 'date' : case 'daterange_begin': case 'daterange_end':
					switch ($field)
					{
						case 'value':  
							//if field is not value then it will be converted to non value
							$result=$this->date_string_to_timestamp($this->getfield($name, 'value'));
							break;
						case 'raw':
							$result=$this->getfield($name, 'value');
							break;
						case 'now':
							/*
							 * Now returns as current timestamp only if the date is today, otherwise it will not return 
							 * as current timestamp
							 */
							if ($this->getfield($name, 'value')==\date::today('Y-m-d'))
								/*
								 * If on the same day, returns today!
								 */
								$result=\date::today('timestamp');
							else
								$result=$this->date_string_to_timestamp($this->getfield($name, 'value'));
							break;
						case 'Y-m':
							$result=$this->date_string_change_format($this->getfield($name, 'value'), 'Y-m-d', 'Y-m');
							break;
					}
					break;
				case 'daterange' :
					$result=['begin' => $this->begin_of_day($this->fields[$name][value]['begin'], 'timestamp'), 
							 'end' => $this->end_of_day($this->fields[$name][value]['end'], 'timestamp')];
					break;
				case 'submit' : 
					$result=$this->fields[$name]['submit'];
					break;
				case 'time' : 					
					switch ($field) //if field is not value then it will be converted to non value
					{
						case 'value':							
							/*
							 * Return today with the timestamp
							 */
							$datetime=\date::today().'T'.$this->getfield($name, 'value');
							$result=$this->date_string_to_timestamp($datetime, 'Y-m-d\TH:i');
							break;
						case 'raw':					
							$result=$this->getfield($name, 'value');							
							break;
						case 'time':						
							/*
							 * Returns just the lapsed second of a day
							 */
							if ($this->getfield($name, 'value'))
								$result=$this->seconds_of_day($this->getfield($name, 'value'));
							break;
					}
					break;
				case 'datetime-local':
					if ($field=='value') //if field is not value then it will be converted to non value
					{
						$result=$this->date_string_to_timestamp($this->getfield($name, 'value'), 'Y-m-d\TH:i');
					}
					break;
				case 'month':
					if ($field=='value') //if field is not value then it will be converted to non value
						$result=$this->end_of_month($this->getfield($name, 'value'), 'timestamp');
					if ($field=='raw')
						$result=$this->getfield($name, 'value');							
					break;
				case 'dropdown':
					$result=$this->getfield($name, $field);
					if ((is_null($result)) && ($field=='value'))
						$result=$this->getfield($name, 'selected');
					break;
				case 'checkbox':
					$result=$this->getfield($name, 'checked')==$this->getfield($name, $field);
					break;
				case 'number':
					$x=$this->getfield($name, $field);
					
					if ($field=='value')
						if (is_numeric($x)) 
						{
							if ($this->fields[$name][decimals]==0)
								$result=(int) $x;
							else
								$result=(float) $x;
						}
						else
							$result=null;
					
					break;					
				default:
					$result=$this->getfield($name, $field);
					break;
			}			
/*			if (($this->fields[$name]['type']=='dropdown') && (is_null($result)) && ($field=='value'))
			{
				$result=$this->getfield($name, 'selected');
			}
			if (($this->fields[$name]['type']=='submit') && ($field=='value'))
			{				
			}
			if (($this->fields[$name]['type']=='date'))
			{
			}
			if (($this->fields[$name]['type']=='time'))
			{
			}
			if (($this->fields[$name]['type']=='datetime-local'))
			{
			}
			if (($this->fields[$name]['type']=='month'))
			{
			}*/
			
			return $result;
		}
		
		function get_fields_value($required_data=array())
		{
			$result=array();
			
			foreach ($required_data as $data)
			{
				$result[$data]=$this->select($data);
			}
			
			return $result;
		}
		
		function get_field_type($field)
		{
			if (!is_array($field))
				$field=$this->fields[$field];
				
			$result='';
			if ($field['type'])
				$result=$field['type'];;
										
			return $result;
		}
		
		function html_caption_display($field)
		{
			if (!is_array($field))
				$field=$this->fields[$field];
				
			$result='';
			if ($field['caption'])
				$result.=$field['caption'].': ';		
				
			$result.=$this->html_display($field);
			
			return $result;
		}
						
		function html_display($field)
		//returns formated display
		{
			if (!is_array($field))
				$field=$this->fields[$field];
				
			$result='';
			if ($field)
			{
				switch ($field['type'])				
				{
					case 'textarea': case 'text': case 'date':
						$result=$this->getvalue($field['name']);						
						break;					
					case 'currency':
						$result=$this->html_currency_display($field);
						break;
					case 'number':
						$result=$this->html_number_display($field);
						break;
					case 'dropdown':
						$result=$this->html_dropdown_display($field);
						break;
					case 'datetime-local':
						$result=$this->html_datetime_display($field);
						break;
					default :
						$result.=$this->std_field_toString($field);
						break;
				}
			}
			return $result;
		}		
		
		function html_warning($caption)
		{
			return "<span class='stl_warning'>$caption</span><br><br>";				
		}
		
		function enable_multi_input_support($support='0')
		/*
		 * Always usse support 0, unless you wanted to enable it right at away, it will only receive
		 * string input '0' is off, '1' is on.
		 * 
		 * multi input must use
		 */
		{
			$this->add_mandatory_input('multi_input', $support);
		}
		
		function multi_location_input($name)
		/*
		 * This is used to add a multi location input, support varies from browsers
		 * to browsers
		 * For dropdown and input field, use this
		 */
		{
			$result='';
			
			$field=$this->fields[$name];
			if ($field)
			{
				$this->add_specification($name, 'form', true);
				$this->add_specification($name, 'show_caption', false);
				
				$input_function=$this->standard_input[$field['type']];
				$result.=$this->$input_function($name);
			}
			
			$this->fields[$name]['multi_input']=true;
			
			return $result;			
		}

		function multi_location_display($name)
		/*
		 * This is used to add a html_hidden_display version of multi_location_input, 
		 * however its support varies from browsers
		 *
		 * In theory, this can be used for making multiple step multi_location_input
		 * however this was untested
		 */
		{
			$result='';
			
			$field=$this->fields[$name];
			if ($field)
			{
				$this->add_specification($name, 'form', true);
				$this->add_specification($name, 'show_caption', false);
				
				$result.=$this->html_hidden_display($name);
			}
			
			$this->fields[$name]['multi_input']=true;
			
			return $result;
		}
		
		function multi_location_input_submit($id, $name, $aux=[])
		/*
		 * This is used to add a multi location input, support varies from browsers
		 * to browsers
		 * This is special for submit button only, and must add name
		 * input:
		 * $id - the ID of element, must add id on the element form synchronised with the id of the dataholder
		 * $name - the name of the submit button
		 */
		{
			$result=$this->form_open();
			$result.=$this->html_credential();
			
			$fields=[];
			foreach ($this->fields as $f_name => $field_data)
			{
				if ((isset($field_data['multi_input'])) && ($field_data['multi_input']) && (isset($field_data['name'])))
					$fields[]=$f_name;
			}
			$this->add_mandatory_input('field', implode(';', $fields));
			$this->add_mandatory_input('multi_input', '1');
			
			$result.=$this->html_submit($name);
			$result.=$this->html_hidden($id);
			
			foreach ($this->mandatory_fields as $mandatory)
				$result.=$this->html_hidden($mandatory);
				
			foreach ($aux as $others)
			{
				$result.=$this->html_hidden($others);
			}
				
			$result.=$this->form_close();
				
			return $result;				
		}
		
		function html_simple_input($id, $name, $submit, $aux=array())
		{
			$result='';
			$result.=$this->form_open();
			$result.=$this->html_credential();
			
			$this->add_mandatory_input('field', $name);
			
			$field=$this->fields[$name];
			if ($field)
			{
				$input_function=$this->standard_input[$field['type']];			
				$result.=$this->$input_function($name).'&nbsp;';
			}
			$result.=$this->html_submit($submit);			
			$result.=$this->html_hidden($id);
			
			foreach ($this->mandatory_fields as $mandatory)
				$result.=$this->html_hidden($mandatory);
			
			foreach ($aux as $others)
			{
				$result.=$this->html_hidden($others);
			}
						
			$result.=$this->form_close();
			
			return $result;
		}
		
		function html_simple_addon_input($name)
		{
			$result='';
			
			$field=$this->fields[$name];
			if ($field)
			{
				$this->add_specification($name, 'form', true);
				$this->add_specification($name, 'show_caption', false);
				
				$input_function=$this->standard_input[$field['type']];
				$result.=$this->$input_function($name);
			}
						
			return $result;			
		}
		
		function dynamic_field($name, $type, $counter, $value='')
		{
			$newname=$this->dynamic_name($name, $counter);
			switch ($type)
			{
				case 'currency':
					$this->add_currency_input('', $newname, $value, $this->select($name, 'min'), $this->select($name, 'max'), array());
					break;
				default:
					$this->add_field('', $newname, $type, '');						
					break;
			}
		}
		
		function make_dynamic($name, $starting, $max_show, $showing=1)
		//only works on all text based fields e.g. date, month, number, text, currency
		//dynamic will add automatically on the form of the 
		//several other input such as field_dyn_current_showing
		//which can be changed and added with DOM on a live website 
		{
			if (($this->fields[$name]['type']=='text') || ($this->fields[$name]['type']=='dropdown')
				|| ($this->fields[$name]['type']=='currency') || ($this->fields[$name]['type']=='number'))
			{				
				$this->add_specification($name, '_dyn', true);
				$this->add_specification($name, '_dyn_starting', $starting);
				$this->add_specification($name, '_dyn_max_show', $max_show);
				$this->add_specification($name, '_dyn_showing', $showing);
			
				$this->editfield($name, $showing);//the value was changed to the number of inputs
				$type=$this->select($name, 'type');
				for ($i=$starting; $i<$showing; $i++)
				{
					$this->dynamic_field($name, $type, $i);
				}
			}
		}
		
		function dynamic_name($name, $counter)
		{
			return '_dyn_'.$name."_$counter";
		}
		
		function is_dynamic($name)
		{						
			return $this->select($name, '_dyn')===true;			
		}
		
		function dynamic_dropdown($name)
		{
			$field=$this->fields[$name];
			$result=$this->html_hidden($name);
				
			$showing=$this->select($name);
			$start=$this->select($name, '_dyn_starting');
			$end=$start+$showing;
				
			if ($field['readonly'])
			{
				for ($counter=$start; $counter<$end; $counter++)
				{
					$newname=$name."_$counter";
					$result='<td>'.$this->html_hidden_display($newname).'</td>';
				}
			}
			else
			{
				$params="";
				if ($field['required'])	$params.=' required';
				
				for ($counter=$start; $counter<$end; $counter++)
				{
					$newname=$name."_$counter";
					$value=$this->select($newname);
					
					$options='';
					foreach ($field['items'] as $item)
					{
						$selected="";
						if ($field[$newname]['value']==$item['value'])
							$selected=" selected";
								
						$options.='<option value="'.$item['value'].'"'.$selected.'>'.$item['display'].'</option>';
					}
						
					$result.='<td><select name="'.$newname.'"'.$params.'>'.$options.'</select></td>';
				}
			}
			
			return $result;
		}
		
		function dynamic_textbased_showing($name, $type, $counter, $editable=true)
		//this method is called by calling normal showing, therefore all the parameters
		//will continue to inherit from its default
		{
			$params=$this->textbased_parameters($name);			
			
			$newname=$this->dynamic_name($name, $counter);
			$value=$this->select($newname);			
					
			if ($editable)
				$result.='<td><input name="'.$newname.'" type="'.$type.'" value="'.$value.'"'.$params.'></td>';
			else
				$result.='<td>'.$this->html_hidden_display($newname).'</td>';
			//add more button here in javascript otherwise if impossible then via PHP
			
			return $result;
		}
		
		function html_dynamic_table($dynamics=array(), $editable=true, $conclusion_function=null)
		{
			$result='';
			$valid_elements=array();
			$headers='<th>No</th>';
			$row_no=0;
			foreach ($dynamics as $name)
			{				
				if ($this->is_dynamic($name))
				{
					$valid_elements[]=$name;
					$caption=$this->select($name, 'caption');
					$headers.='<th>'.$caption.'</th>';
					if ($row_no<$this->select($name))
					{
						$row_no=$this->select($name);
					}
					$result.=$this->html_hidden($name);
					//change to html_hidden sometime in the future
				}				
			}
			
			$headers='<tr>'.$headers.'</tr>';
			$row='';			
			for ($i=0; $i<$row_no; $i++)
			{
				$no=$i+1;
				$column="<td>".$no."</td>";
				foreach ($valid_elements as $name)
				{
					$type=$this->select($name, 'type');
					$start=$this->select($name, '_dyn_starting');
					$end=$this->select($name);
					
					$current=$start+$i;
					if ($current<$end)
					{
						switch ($type)				
						{
							case 'text': case 'date': case 'month': case 'number': case 'currency':
							{								
								$column.=$this->dynamic_textbased_showing($name, $type, $current, $editable);
								break;
							}
/*							case 'dropdown':
							{
								$column=$this->dynamic_dropdown($name, $current, $editable);
								break;
							}*/
							default :
								$column.='<td>not supported</td>';
						}
					}
					else
						$column.='<td>n/a</td>';
				}
				$row.='<tr>'.$column.'</tr>';
			}
			
			$conclusion='';
			if (($conclusion_function) && (method_exists($this, $conclusion_function)))
				$conclusion=$this->$conclusion_function($dynamics);
			
			$table='<table>'.$headers.$row.$conclusion.'</table>';
			$result.=$table;			
			
			return $result;
		}
		
		function dynamic_update_value($datasource)
		{						
			foreach ($this->fields as $name => $field)
			{
				if (($this->is_dynamic($name)) && ($datasource[$name]) && isset($field['name']))
				{
					$type=$this->select($name, 'type');
					
					$start=$this->select($name);
					$end=$datasource[$name];
					
					if ($end<$this->select($name, '_dyn_max_show'))
					{
						$this->editfield($name, $end);
						for ($counter=$start; $counter<$end; $counter++)
						{							
							$value=$datasource[$newname];
					
							$this->dynamic_field($name, $type, $counter, $value);
						}
					}
				}
				
			}
			$this->updateValue($datasource);		
			
/*			foreach ($this->fields as $name => $field)
			{			
				$showing=$datasource[$name];
				$type=$this->select($name, 'type');
				$this->editfield($name, $showing);
			
				$start=$this->select($name, '_dyn_starting');
				$end=$start+$showing;
			
				for ($counter=$start; $counter<$end; $counter++)
				{
					$newname="_dyn_".$name."_$counter";
					$value=$datasource[$newname];
				
					$this->add_field('', $newname, $type, $value);
				}
			}*/ 	
		}
		
		function currency_format_as_field($field, $number)
		{
			if (!is_array($field))
				$field=$this->fields[$field];
			
			$result=finance::money_format($field['currency'], $number);
						
			return $result;
		}
		
		function exit_form($interface=null, $input=[], $extra_fields=[], $caption='Close', $return=null)
		/*
		 * A modern version than advanced exit form
		 * 
		 * extra_fields can possibly made automatic using mandatory input, but it is 
		 * considered too risky, because sometimes it is uncecessary to pass mandatory
		 * input on one form to another.  Hence it has to be done manually.
		 * 
		 * _extending is now always available on exit_form
		 */
		{
			$extras=[];
									
			foreach ($extra_fields as $field)
			{
				if ($input[$field])
					$extras[$field]=$input[$field];
				else
					$extras[$field]=$this->select($field);
			}
			
			$field=['type' => 'add_submit_button', 'submit' => '_exit', 'submit_caption' => $caption];
			$form=new mini_form($this->credentials, '_exit', null, 'post', $return, $field,
				$extras);
			$form->set_junction($interface ?? $this->interface);
						
			return $form;				
		}
		
		function advanced_exit_form($input, $final_step=2, $buttons=array('close' => 'Close', 'view' => 'Email'))
		/*
		 * use this on non interfaced 
		 */
		{
			$result='';
			
			if ($this->input_step==$final_step)
			{
				if ($input['action'])
					$action=$input['action'];
				else
					$action=$this->return;
					
				$closing_form=new inputform_control($this->credentials, 'exit_form', $action, $this->method, $this->return);
				$closing_form->set_junction($this->interface);
				$closing_form->add_credential(...$this->credentials->available_credentials());
						
				foreach ($buttons as $name => $value)
					$closing_form->add_submit_button('', $name, $value, array());
							//$closing_form->add_submit_button('', 'view', 'Email', array());
											
				foreach ($input as $name => $value)
				{
					if ($this->select($value))
						/*
						 * Auto select value from current 
						 */
						$closing_form->add_hidden_input($value, $this->select($value));
					else
						/*
						 * Work as field name => value 
						 */
						$closing_form->add_hidden_input($name, $value);
				}
				
				$result=$closing_form->toStringFormat('&nbsp');
			}
						
			return $result;
		}			
		
		use form_title_symbols;
		function form_title($form_title='')
		{
			return $this->form_title_message($form_title, $this->error, $this->success, $this->warning,
				$this->notice);			
		}
		
		function is_showing(...$conditions)
		{			
			if ($this->dataholder()) 
				return $this->dataholder()->is_showing(...$conditions);
		}		
		
		function register_HTML_event($field, $event, $function)
		/*
		 * Used to register HTML event, function is javascript functio to execute
		 */
		{
//			$this->events[$field][$event]=$function;
			
			$this->add_specification($field, $event, $function);
		}
		
		function copy(record $to, ...$names)
		/*
		 * Copying from form this to a record
		 */
		{
			foreach ($names as $name)
				$to->set($name, $this->select($name));
		}		
		
		function set_junction(\master_junction|string $junction, $input=[])
		/*
		 * This will enable the form to work in invoker mode
		 * interface will be in form_open or form_close to add in mandatory_input
		 * as it will be used in really late to ensure it always works
		 *
		 * enter junction with differrent class than this class to exit with a
		 * different target form
		 */
		{
			$this->interface=$junction;
			$this->junction=$junction;
			
			/*			if (is_object($interface))
			 $interface->register_content_source('form', $this);*/
			
			if ($junction instanceof \junction)
			{
				$interforms=$this->junction->interform_commons;
				
				$ns=$this->find_object_location($junction);
				if ($ns['namespace'])
				{
					$this->add_mandatory_input(_namespace, $ns['namespace']);
					$this->add_mandatory_input(_junction, $ns['object']);
					$this->add_mandatory_input('_junction', $ns['object']);
				}
				else
				{
					$this->add_mandatory_input(_junction, $ns['object']);
					$this->add_mandatory_input('_junction', $ns['object']);
				}
				
				$junction->addForm($this);
			}
			elseif (is_string($junction))
			{
				$interforms=[];
				
				$this->add_mandatory_input(_junction, $junction);
				$this->add_mandatory_input('_junction', $junction);
			}
			//if (get_class($this)=='division_choice_form') var_dump($this->fields['division']);
			
			foreach ($interforms as ['name' => $common])
			{
				$this->interform_commons[$common]=['name' => $common];
				
				if (isset($input[$common]))
					$this->interform_commons[$common]['value']=$input[$common];
					
					if (!isset($this->fields[$common]))
						$this->add_mandatory_input($common, $input[$common]);
			}
			
			if ((method_exists($junction, 'ajax_enabled')) && ($junction::ajax_enabled()))
				$this->add_mandatory_input('_ajax_contents', 'event');
				
				if ((method_exists($this, 'event_driven')) && ($this::event_driven()))
					$this->prepare_for_events($junction);
		}
		
		function get_junction(): junction
		{
			return $this->junction;
		}		
	}
	
	class fc_form extends lib_form
	/*
	 * flow_control_form alias fc_form is replacement for inputform_control using tree as input_output step
	 * this is more sophisticated and allow non linear input and output at the expense of more complicated
	 * setup.
	 * 
	 * hopefully most class can be migrrated to fc_forrm hence deprecating the inputform_control
	 */
	{		
		/*
		 * Input & Output in a tree  
		 */
		
		private $_io_tree;
		private $_steps;		
		private $_is_extending;
		
		function __construct(credentials $credentials, $name, $action=null, $method=POST, $return=null)
		{
			parent::__construct($credentials, $name, $action ?? 'page.php', $method, $return ?? 'page.php');
						
			$this->_io_tree=[];
			$this->_steps=[];
			$this->_is_extending=false;
			
			$this->add_mandatory_input('uniqueid', $name);
		}
		
		final function set_extending(extensions $extender, String $extending)
		/*
		 * This will set the extending module extension name to the extender name.
		 * 
		 * It might look weird, but it works
		 * 
		 * extending is only one! never 2 even when a a class is extended by more than 2
		 * it only run once at a time!
		 */
		{
			$extender->interop_validate_extending($extending);
			$this->_is_extending=$extender;
			
			$this->add_mandatory_input(_extending, $extending);
		}
						
		final function form_close()
		/*
		 * Closing the form, with setting scrambled input/output on quick hash
		 * Perhaps needs some random to input_session?
		 */
		{
			$scambled_steps=[];
			$clear_steps=[];
			foreach ($this->_steps as ['method' => $method, 'submit' => $submit])
			{
				if ($submit)
				{
					$scambled_steps[]=parent::quick_hash($method).':'.parent::quick_hash($submit);							
					$clear_steps[]=$method.':'.$submit;
				}				
				else
				{
					$scambled_steps[]=parent::quick_hash($method);
					$clear_steps[]=$method;
				}
			}
				
			$steps=parent::quick_hash(implode(';', $clear_steps));
			
			$this->add_mandatory_input('_steps', implode(';', $scambled_steps));			
			$this->add_mandatory_input('_v_steps', $steps);
			
			return parent::form_close();
		}
		
		function verify_io_step($input)
		/*
		 * This method is used to verify the input steps and check if the input step is correctly 
		 * sequenced and not changed by user 
		 */
		{
			$this->updateValue($input, '_steps', '_v_steps');
			
			$scram_steps=explode(';', $this->select('_steps') ?? '');
			
			$method=$this->select('_start_method');
			
			$input_step=0;
			$steps=[];
			$clear_steps=[];
			foreach ($scram_steps as $s_step)
			{
				$s_steps=explode(':', $s_step);
					
				if (hash_equals($s_steps[0], parent::quick_hash($method)))
				{
					/*
					 * Good found a match!
					 */
					$steps[$input_step]['method']=$method;
					
					if (isset($s_steps[1]))
					{
						foreach ($this->io_get($method, 'submits') as $submit)
						{							
							if (hash_equals($s_steps[1], parent::quick_hash($submit)))
							{
								/*
								 * Button is corrrectly pressed
								 */
								$steps[$input_step]['submit']=$submit;
								
								$clear_steps[]=$steps[$input_step]['method'].':'.$steps[$input_step]['submit'];
								
								$method=$this->io_get($method, $submit, 'target');
								
								break;
							}
							
						}						
					}
					else
					{
						//$steps[$input_step]['submit']='';
						$clear_steps[]=$steps[$input_step]['method'];
					}
										
					$input_step++;										
				}								
			}
			
			if (!hash_equals(parent::quick_hash(implode(';', $clear_steps)), $this->select('_v_steps') ?? ''))
				throw new Exception('IO step compromised');
			
			$this->_steps=$steps;
			$this->input_step=$input_step-1;
			
			return $input_step;
		}
		
		final function validate_submit($name, $value, $input_step)
		/*
		 * This method override the validate_submit that is designed for the old method
		 * input_step variable is not used!
		 */
		{
			$step=$this->get_step();

			if ($result=(hash_equals($this->io_get($step, $name, 'value'), $value)))
				$this->buttons_list[$name]['clicked']=true;
				
			return $result;			
		}
		
		final function revert_input_step($input_step, ...$variables)
		/*
		 * This will reset the input step according to the sequence, e.g. relatively speaking
		 * 
		 * or it can work by calling it a method
		 */
		{
			if (is_integer($input_step))
				$is=$input_step;
			else
			{
				foreach ($this->_steps as $key => ['method' => $method])
					if ($method==$input_step)
					{
						$is=$key;
						break;
					}
			}
			
			$steps=[];
			foreach ($this->_steps as $key => $value)
				if ($key<=$is)
					$steps[$key]=$value;
					
			$this->_steps=$steps;
			
			$this->update_submit_captions();
		}
		
		final function is_final_input_step(): bool
		/*
		 * Since there can be multiple input step, this method will check if the current final input step
		 * final or not
		 */
		{
			$method=$this->_steps[array_key_last($this->_steps)]['method'];
			
			return ($this->io_get($method, 'final')=='true');
		}
		
		final function is_inserting()
		{
			$method=$this->get_step();
			
			return ($this->io_get($method, 'insert')!='');			
		}
		
		final function get_input_step()
		/*
		 * This is an alias to get_step
		 */
		{
			return $this->get_step();
		}
				
		final function insert()
		/*
		 * Should be final, because insert is now must be code defined custom method, with any other name
		 * than insert!!
		 */
		{
			$method=$this->_steps[array_key_last($this->_steps)]['method'];
			
			if ($insert_method=$this->io_get($method, 'insert'))
			{
				if (method_exists($this, $insert_method))
				{
					$this->{$insert_method}();
				}
				else
				{
					throw new Exception('insert method is undefined in the form class');
				}
			}
			else
			{
				throw new Exception('insert method is unspecified');
			}
		}
		
		function input($input, ...$fields)
		/*
		 * _extending is part of system, so its checked by default in this place
		 * Please assume extending is already valid!
		 */
		{
//			$input_session=$this->select('_input_session'); //restore when it is the first step
			
			$input_step=$this->verify_io_step($input);
						
			$errors=$this->updateValue($input, ...$fields);
			
			if (!empty($errors))
			{
				$this->error=form_invalid_input.' ('.implode(', ', $errors).')';
				$this->revert_input_step($input_step-1);
				$this->update_submit_captions();
				$this->valid_input=-1;
			}
			else
			{
				$method=$this->get_step();
				
				/*
				 * Advancing 1 step
				 */
				if ($s=$this->submit_click())
					$method=$this->add_submit_step($method, $s);
				
				if (!$this->is_final_input_step())
				/*
				 * While not final input step, then will update the input buttons
				 */
				{
					$this->update_submit_captions($method);
				}
			}
			
			return $this->input_step;
		}
		
		function is_designated($input)
		/*
		 * This method is used to check if an input is designated for this function
		 * This will also update the submit caption
		 */
		{
			$this->update_submit_captions();
			
			return hash_equals($input['uniqueid'] ?? '', $this->select('uniqueid'));
		}
		
		final function update_submit_captions($method=null)
		{
			$method=$method ?: $this->get_step();
			
			$submits=$this->io_get($method, 'submits');
			
			foreach ($submits as $name)
			/*
			 * Actual button caption updating
			 */
			{
				$this->set($name, $this->io_get($method, $name, 'value'));
			}			
		}
		
		final function submit_click()
		{
			$result=null;
			
			foreach ($this->buttons_list as ['name' => $name, 'clicked' => $clicked])
			{
				if ($clicked)
				{	
					$result=$name;
					
					break;
				}
			}
			
			return $result;
		}
		
		final function add_submit_step($method, $submit)
		/*
		 * This method will add the name of clicked submit button to the _steps taken by user
		 */
		{
			$target_method=$method;
			
			if ($this->get_step()==$method)
			{
				$this->_steps[array_key_last($this->_steps)]['submit']=$submit;
				
				$target_method=$this->io_get($method, $submit, 'target');
				
				$this->_steps[]['method']=$target_method;
				
				$this->input_step++;
			}
			
			return $target_method;
		}
		
		final function get_step()
		/*
		 * Returns the current step, which is output_function
		 */
		{
			$step=$this->_steps[array_key_last($this->_steps)]['method'];
			
			return $step;
		}
		
		final function input_step($input_step)
		/*
		 * This funciton will check if the supplied input step is==current step
		 */
		{
			return $this->get_step()==$input_step;
		}
		
		final function after_step(String $method)
		/*
		 * This will check if the current step is actually after specified method
		 * 
		 * it will return false if method is current step (behaves like <, not <=)
		 */
		{
			$result=false;
			$last=array_key_last($this->_steps);
			
			foreach ($this->_steps as $key => ['method' => $m])
			{				
				if (($key<$last) && (hash_equals($method, $m)))
				{
					$result=true;
					break;
				}
			}
			
			return $result;
		}
			
		final function on_step(String $method)
		/*
		 * This will check if the current step is actually on the exact specified step
		 */
		{
			return hash_equals($this->get_step(), $method);
		}
		
		final function output_function()
		/*
		 * Returns the output function
		 */
		{
			$method=$this->_steps[array_key_last($this->_steps)]['method'];
			
			return $method;
		}
		
		function toHTML()
		{
			$output_step_count=count($this->_io_tree);
			
			if ($output_step_count>0)
			{				
				$output_function=$this->output_function();
				if (method_exists($this, $output_function))
				{
					$of_result=$this->$output_function();
					if (($this->has_layout_maker()) && (is_array($of_result)))
					{
						$result=$this->layout_maker()->assign_layout($of_result);
					}
					else
					{
						if (is_array($of_result))
						{
							$result='Layout maker not implemented';
							throw new Exception($result);
						}
						else
							$result=$of_result;
					}
				}
				else
				{
					$result='Output function not implemented';
					throw new Exception('Output function not implemented');
				}
			}
			else
			{
				$result=$this->toStringFormat('<br>', true);
			}
			
			//			if ($this->)
			
			return $result;			
		}
		
		final function io_set_submit($method, $target, $name, $value)
		/*
		 * This method will add possible submit button in an output method
		 */
		{						
			$this->io_set($method, $name, 'submits', $name);
			$this->io_set($method, $value, $name, 'value');
			$this->io_set($method, $target, $name, 'target');
		}
		
		final function io_set_final($method)
		/*
		 * Marking the method as final, and will not proceed to more method
		 */
		{
			$this->io_set($method, 'true', 'final');
		}
		
		final function io_set_insert($method, $insert)
		/*
		 * This function will add insert function to an output method
		 * please note, not all output method needed insert button
		 */
		{
			$this->io_set($method, $insert, 'insert');
		}
		
		final function io_set($method, $value, ...$indices)
		/*
		 * Use this method generally 
		 * name => current method
		 * value => the next method
		 * indices => submit or save (it is a name) and its title
		 * 
		 * when io_tree is empty, its always assumed to be the first
		 * method, e.g. stepZero
		 */
		{
			if (empty($this->_io_tree))
			{
				$this->io_set_start_method($method);
			}
			
			$this->_set($this->_io_tree, $value, $method, ...$indices);			
		}
		
		final function io_set_start_method($method)
		/*
		 * This is used to set the starter method
		 */
		{
			$this->_steps=[];
			
			$this->set('_start_method', $method);
			
			$this->set('_steps', parent::quick_hash($method));
			$this->set('_v_steps', parent::quick_hash($method));

			$this->_steps[]['method']=$method;
				
			$this->update_submit_captions();			
		}
		
		final function io_get($method, ...$indices)
		{
			return $this->_get($this->_io_tree, $method, ...$indices);
		}
		
		function on_click($button)
		/*
		 * Used to tell if input was clicked
		 */
		{
			return $this->buttons_list[$button]['clicked']!==false;
		}
								
		function compile_io_tree($input_steps, $output_steps)
		/*
		 * This will make io tree from input and output steps
		 */
		{
		}
		
		function dataholder_synchronise()
		/*
		 * Automatically synchronised database using translation map to data holder
		 * translation map is used when the dataholder might not have the same name
		 * with the form
		 * 
		 * warning: this method currently does not support complex data structure of 
		 * the record class
		 * 
		 * future support is possible
		 */
		{
			foreach ($this->fields as ['name' => $field])
			{
				if ($field)
				{
					$dataholder=$this->dataholder();
					
					if ($map=$this->has_translation_map($field))
					{
						$value=$dataholder->select($field, $map);
						
						if (!is_null($value))
							$this->editfield($field, $value);
					}
					else
					{//var_dump($key);					
						$value=$dataholder->select($field);
							
						if (!is_null($value))
							$this->editfield($field, $value);
					}
				}
			}
		}
		
		function add_translation_map($map)
		/*
		 * This method is used to translate stacked stored value in database to linear HTML display form
		 * input : an array with format ['field_name' => ['map' => 'map_name', 'value' => 'map_value']]
		 * index
		 */
		{
			foreach ($map as $name => ['map' => $map, 'value' => $value])
			{
				$this->fields[$name]['map']=$map;
				if (!is_null($value))
					$this->add_mandatory_input($map, $value);
			}
		}
		
		function has_translation_map($name)
		{
			$result=$this->fields[$name]['map'] ?? null;
			
			return $result;
		}
		
		function translate_map($datasource)
		/*
		 * This method will used to translate the map from the database / datasource to the form
		 * assisted with the value on the form as mandatory input
		 * This method will not be automatically enabled on all level as this method is
		 * expensive
		 */
		{
			$result=[];
			
			foreach ($this->fields as ['name' => $name, 'map' => $index])
				if (isset($name))
				{
					if ($index)
					{
						$index_id=$this->select($index);
						$result[$name]=$datasource[$name][$index_id];
					}
					else
						$result[$name]=$datasource[$name];
				}
			
			return $result;
		}		
		
		function exit_form($interface=null, $input=[], $extra_fields=[], $caption='Close', $return=null)
		/*
		 */
		{
			$form=parent::exit_form($interface, $input, $extra_fields, $caption, $return);
/*			$extras=[];
			
			foreach ($extra_fields as $field)
			{
				if ($input[$field])
					$extras[$field]=$input[$field];
					else
						$extras[$field]=$this->select($field);
			}
			
			$field=['type' => 'add_submit_button', 'submit' => '_exit', 'submit_caption' => $caption];
			$form=new mini_form($this->credentials, '_exit', null, 'post', $return, $field,
				$extras);
			$form->set_junction($interface ?? $this->interface);*/
	
			if ($this->_is_extending)
				$form->add_mandatory_input(_extending, $this->select(_extending));
			
			return $form;
		}		
	}
	
	class inputform_control extends lib_form
	{		
		var $secure_db;
		var $readonly_db;
		var $interform_commons;
				
		var $valid_input;
		
		var $features;
		
		var $id_field;
		
		var $rv_sync_exception;
		
//		private $layout_maker;
		
		function add_IO_step($input, $output)
		/*
		 * store Input and Output step
		 */
		{
			$this->rv_input_step=$input;
			$this->rv_output_step=$output;
		}
		
		function add_feature($name, $field, $feature)
		/*
		 * add feature is used to keep track of feature on a page, so it will not be a blind features
		 * and dependencies
		 */
		{
			$this->features[$name][$field]=$feature;
		}
		
		function get_features()
		/*
		 * Not compatible with add_page_features
		 */
		{
			return $this->features;
		}
				
		static function rv_loader_step()
		/*
		 * This will determine which rv_input_step is the loader step, hence the system will expect
		 * that as the loader
		 * 
		 * Please overload on a function that is using loader_mode
		 */
		{
			return -1;
		}
				
		static function rv_input_step()
		/*
		 * This will return default rv_input_step, however override when needed in subclass
		 * 
		 * please modify rv_loader_step to tell which step is the loader step
		 */
		{
			return ['Save', 'Confirm'];
		}
		
		static function rv_output_step()
		/*
		 * This will return default rv_output_step, however override when needed in subclass
		 * 
		 * add loader here on when loading step corresponding to the rv_loader_step
		 */
		{
			return ['stepZero', 'stepOne', 'stepTwo'];
		}
		
		function __construct($credentials, $name, $action, $method, $return, $secure_db='', $readonly_db='', $id_field='id')
		{
			parent::__construct($credentials, $name, $action ?? 'page.php', $method, $return ?? 'page.php');
			
			$this->secure_db=$secure_db;
			$this->readonly_db=$readonly_db;
			$this->id_field=$id_field;
			
			$this->valid_input=0;
			
			$this->features=[];
			
			$this->interform_commons=[];
			$this->interface=null;
			
			$this->rv_sync_exception['save']=1;
			$this->rv_sync_exception['_input_session']=1;
			$this->rv_sync_exception['_interface']=1;
			$this->rv_sync_exception['_junction']=1;
			$this->rv_sync_exception['_username']=1;
			$user_credentials=$this->credentials->user_credentials();
			foreach ($user_credentials as $credential_name)
				$this->rv_sync_exception[$credential_name]=1;
			$this->rv_sync_exception['uniqueid']=1;							
		}
		
		function dataholder_synchronise()
		/*
		 * Automatically synchronised database using translation map to data holder
		 * translation map is used when the dataholder might not have the same name
		 * with the form 
		 */
		{
			foreach ($this->fields as ['name' => $field])
				if (isset($field))
				{
					$dataholder=$this->dataholder();
					
					if ($map=$this->has_translation_map($field))
					{
						$value=$dataholder->select($field, $map);					
						
						if (!is_null($value))
							$this->editfield($field, $value);
					}
					else
					{//var_dump($key);
						$value=$dataholder->select($field);
						
						if (!is_null($value))
							$this->editfield($field, $value);						
					}								
				}
		}
		
		function add_translation_map($map)
		/*
		 * This method is used to translate stacked stored value in database to linear HTML display form
		 * input : an array with format ['field_name' => ['map' => 'map_name', 'value' => 'map_value']]
		 * index
		 */
		{
			foreach ($map as $name => ['map' => $map, 'value' => $value])
			{
				$this->fields[$name]['map']=$map;
				if (!is_null($value))
					$this->add_mandatory_input($map, $value);
			}
		}
		
		function has_translation_map($name)
		{
			$result=$this->fields[$name]['map'] ?? null;
			
			return $result;
		}
		
		function translate_map($datasource)
		/*
		 * This method will used to translate the map from the database / datasource to the form
		 * assisted with the value on the form as mandatory input
		 * This method will not be automatically enabled on all level as this method is
		 * expensive
		 */
		{
			$result=[];
			
			foreach ($this->fields as ['name' => $name, 'map' => $index])
				if (isset($name))
				{
					if ($index)
					{
						$index_id=$this->select($index);
						$result[$name]=$datasource[$name][$index_id];
					}
					else
						$result[$name]=$datasource[$name];
				}
			
			return $result;
		}		
					
		function find_input_step($input)
		/*
		 * Requires descrambled input to work
		 */
		{
			$is_count=count($this->rv_input_step);
			$this->input_step=0;
			
			for ($i=0; $i<$is_count; $i++)
			{
				if ($input[$this->submit_name]==$this->rv_input_step[$i])
					$this->input_step=$i+1;
			}
			
			return $this->input_step;
		}
		
		function revert_input_step($input_step, ...$variables)
		/*
		 * Reset form = is always used here, but might not exists on the class
		 */
		{
			$this->input_step=$input_step;
			$this->editfield($this->submit_name, $this->rv_input_step[$this->input_step]);
			
			$this->editfield('_input_session', $this->credentials->input_session());
			
			if (method_exists($this, 'reset_form'))
				$this->reset_form(...$variables);
			
			return $input_step;
		}
		
		function reset($input_step=0)
		{
			$this->revert_input_step(0);
			
			foreach ($this->fields as ['name' => $name, 'default' => $default])
				if (isset($name))
					$this->edit_field($name, 'value', $default);
		}
								
		function valid_input()
		{
			return $this->valid_input==1;
		}
				
		function input($input, ...$fields)
		{						
			$input_session=$this->select('_input_session'); //restore when it is the first step
			
			$errors=$this->updateValue($input, ...$fields);
			
			$this->submit_click($input);
			
			if (!empty($errors))
			{
				$this->error=form_invalid_input.' ('.implode(', ', $errors).')';
				$this->revert_input_step($input['_input_step']);				
				$this->valid_input=-1;
			}
			else
			{	
				$this->valid_input=1;
				
				$final_is=$this->final_input_step();
				$i=0;			
				while ($i<$final_is)
				{ 
					if ((isset($input[$this->submit_name])) && 
						($input[$this->submit_name]==$this->rv_input_step[$i]))
					{
						$this->input_step=$i+1;
						$this->editfield($this->submit_name, $this->rv_input_step[$i+1]);
						break;
					}
					$i++;
				}
				
				if ($this->input_step==0)
				{
					$this->editfield('_input_session', $input_session); //restore original session
				}
			}
			
			if (($this->valid_input()) && ($this->rv_loader_step()!==-1) && 
				($this->rv_input_step[$this->rv_loader_step()]==$input[$this->submit_name]))
			/*
			 * If in loader mode, jump to next step
			 * 
			 * Loader mode is a useful mode where you can input many pieces of items
			 * using the same form without changing interface.
			 * 
			 * E.g. during stock taking or during interface
			 * 
			 * but this is not a really nice thing to do
			 */
			{
				$this->revert_input_step($this->rv_loader_step()+1);
			}
			
			return $this->input_step;
		}			
		
		function get_input_single_button($callback, $caption, $v, $name, $required_data=[], $additional_data=[], $hint=null)
		{				
			if (!$this->fields[$name])
				$this->add_submit_button($caption, $name, $v, ['hint' => $hint]);
			foreach ($required_data as $data)
				$this->add_hidden_input($data, $this->select($data));
				
			foreach ($additional_data as $key => $value)
				$this->add_hidden_input($key, $value);
					
			$result=$this->toString();
					
			return $result;
		}
		
		function loader()
		/*
		 * Loader always return 0, this is equivalent to loader step that is doing nothing and 
		 * is here to avoid 
		 */
		{
			return 0;
		}
		
		function insert()
		/*
		 * Insert will by default return warning, and must be
		 */
		{
			$this->error='insert method is undefined in the form class';
		}
		
		function submit_click($input)
		/*
		 * Used to signify if a button is clicked and return which button is clicked
		 * 
		 * This is useful for branching.
		 * 
		 * Always use input_step as linear branching, add tree branching later   
		 */
		{
			$result='';
			foreach ($this->buttons_list as ['name' => $name])
			{											
				$search=array_search(isset($input[$name]) ? $input[$name] : [], $this->rv_input_step);
				
				if ($search!==false)
				{
					$this->buttons_list[$name]['clicked']=$search;
					$result=$name;
					
					break;
				}
			}
						
			return $result;
		}
		
		function on_click($button)
		/*
		 * Used to tell if input was clicked
		 */
		{
			return $this->buttons_list[$button]['clicked']!==false;
		}
	}
	
	class mini_form extends inputform_control
	/*
	 * universal function to create mini input, it can be date, month, or whatever is needed
	 * with just one field
	 * 
	 * compared to html_simple_input, html_simple_input is used as a simplified complex 
	 * form, howeve mini_input is a complete class of input that is requires just one field with 
	 * one submit which is always called submit 
	 */
	{
		/*
		 * Please reconsider to always access using select
		 */
		var $target_date;
		var $target_month;
		var $month_begin;
		var $month_end;
		var $daterange;
		var $range;
		var $type;
				
		function initialise_month($input)
		{
			if ($input[$this->name])
			{
				$this->month_begin=$this->first_of_month($input[$this->name].'-01', 'timestamp');
				$this->month_end=$this->end_of_month($input[$this->name].'-01', 'timestamp');
				$this->target_month=$input[$this->name];
			}
			else
			{
				$this->month_begin=$this->first_of_month(\date::today(), 'timestamp');
				$this->month_end=$this->end_of_month(\date::today(), 'timestamp');
				$this->target_month=\date::today('Y-m');
			}
			
			$this->editfield($this->name, $this->target_month);
			
			$layout_maker=new layout_maker($this);
			$layout_maker->set_layout_step('layout_miniform_month');
						
			$this->submit_click($input);
		}
		
		function bom()
		{
			return $this->begin_of_month($this->select('target_month'));
		}
		
		function eom()
		{
			return $this->end_of_month($this->select('target_month'));
		}
				
		function initialise_date($input)
		{			
//			$this->input($input);
			
			$this->target_date=$this->select('target_date');
			$target_date=($input['target_date']==\date::today() || (!$input['target_date'])) ? \date::today('timestamp') :
				$this->end_of_day($input['target_date'], 'timestamp');
			$this->target_date=$target_date;
			
			$this->editfield($this->name, $this->target_date);
			
			$this->submit_click($input);			
		}
		
		function initialise_daterange($input)
		{
			//			$this->input($input);
			
//			$this->target_date=$this->select('target_date');
			$end=($input[$this->name.'_end']==$this->end_of_day() || (!$input[$this->name.'_end'])) ? $this->end_of_day() :
				  $this->end_of_day($input[$this->name.'_end']);
			
			$begin=($input[$this->name.'_begin']==$this->begin_of_day() || (!$input[$this->name.'_begin'])) ? 
					$this->begin_of_day($this->days_ago($end, 'timestamp', $this->fields[$this->name]['range'])) :
					$this->begin_of_day($input[$this->name.'_begin']);
			
			$this->daterange=['begin' => $begin, 'end' => $end];
			
			$this->set($this->name, $this->daterange);
			$this->set($this->name.'_begin', $begin);
			$this->set($this->name.'_end', $end);

			$layout_maker=new layout_maker($this);
			$layout_maker->set_layout_step('layout_miniform_daterange');
			
			$this->submit_click($input);			
		}
		
		function initialise_range($input)
		{
			//			$this->input($input);
			
			//			$this->target_date=$this->select('target_date');
			$end=$input[$this->name.'_end'] ?? $this->specified($this->name, max);			
			$begin=$input[$this->name.'_begin'] ?? $this->specified($this->name, min);
			
			if ($end<$begin) math::swap($begin, $end);
			
			$this->range=['begin' => $begin, 'end' => $end];
			
			$this->set($this->name, $this->range);
			$this->set($this->name.'_begin', $begin);
			$this->set($this->name.'_end', $end);
			
			$layout_maker=new layout_maker($this);
			$layout_maker->set_layout_step('layout_miniform_range');
			
			$this->submit_click($input);
		}		
		
		function as_timestamps()
		/*
		 * Returrns timestamp for this, only works when its month_input, daterange_input or date_input 
		 */
		{
			$result=[];
			
			switch ($this->type)
			{
				case 'add_month_input':
					$date=new \date_iterator($this->month_begin, $this->month_end);
					$result=$date->timestamps();
					break;
				case 'add_daterange_input':
					$date=new \date_iterator($this->daterange['begin'], $this->daterange['end']);
					$result=$date->timestamps();
					break;
				case 'add_date_input':				
					$result[]=$this->target_date;
					break;
			}
						
			return $result;
		}
		
		function initialise_number($input)
		{
			if ($input[$this->name])
				$this->editfield($this->name, $input[$this->name]);			

			$this->submit_click($input);				
		}
				
		function __construct($credentials, $name, $action, $method, $return, $field, $mandatory_input=[])		
		{
			parent::__construct($credentials, $name, $action, $method, $return);

			$this->type=$field['type'];
			
			switch ($field['type'])
			{
				case 'add_submit_button':				
					$this->{$field['type']}($field['caption'], $field['submit'], $field['submit_caption']);					
					break;
				case 'add_dropdown':
					$this->add_dropdown($field['caption'], $name, $field['items'], $field['preselect'], $field['params'] ?? ['required' => true]);
					$this->add_submit_button('', $field['submit'], $field['submit_caption'], array());
					break;
				case 'add_daterange_input':
					$this->add_daterange_input($field[caption], $name, $field[value], 
						$field[min], $field[max], $field['range'], $field[params] ?? [required => true]);
					$this->add_submit_button('', $field['submit'], $field['submit_caption'], []);
					break;
				case 'add_range_input':
					$this->add_range_input($field[caption], $name, 
						$field[min], $field[max], $field[params] ?? [required => true]);
					$this->add_submit_button('', $field['submit'], $field['submit_caption'], []);
					break;					
				case 'add_month_input': case 'add_date_input': case 'add_number_input':
				default:
					$this->{$field['type']}($field['caption'], $name, $field['value'],
							$field['min'], $field['max'], $field['params'] ?? ['required' => true]);
					$this->add_submit_button('', $field['submit'], $field['submit_caption'], array());
					break;
			}			
			$this->add_credential($this->credentials->primary());
			
			foreach ($mandatory_input as $key => $add_value)
				$this->add_mandatory_input($key, $add_value);
		}
		
		function __toString()
		/*
		 * On miniform use toString, it is better
		 */
		{
			$result='';
			
			if ($this->has_layout_maker())
			{
				switch ($this->name)
				{
					case 'target_month':
						$result.=$this->layout_maker()->assign_layout(
									['form_open' => $this->form_open(), 'target_month' => $this->html_month('target_month'), 
									 'save' => $this->html_submit('save'), 
									 'credential' => $this->html_credential(), 'form_close' => $this->form_close()]);
						break;
					case 'daterange':
						$result.=$this->layout_maker()->assign_layout(
								['form_open' => $this->form_open(), 
								 'daterange' => $this->html_daterange('daterange'),								 
								 'save' => $this->html_submit('save'),
								 'credential' => $this->html_credential(), 'form_close' => $this->form_close()]);						
						break;
					case 'range':
						$result.=$this->layout_maker()->assign_layout(
								['form_open' => $this->form_open(),
								 'range' => $this->html_range('range'),
								 'save' => $this->html_submit('save'),
								 'credential' => $this->html_credential(), 'form_close' => $this->form_close()]);
						break;
				}
			}
			else
				$result=$this->toString();
			
			return $result;
		}
	}
		
	trait javascript_form 
	/*
	 * This used to add javascript capability to a working lib_form or inputform_control
	 * Limitation: do not start function name with fetch
	 */
	{
		function js_form_open(...$js_input)
		/*
		 * Opening form with js_input parameters that is made with php
		 * but actually input as variable in javascript 
		 */
		{
			if ($this->final_input_step()==$this->input_step)
				$nextpage=$this->return;
			else 
				$nextpage=$this->action;

			$values=implode(', ', $js_input);
				
			$result='<script type="text/javascript">';
			$result.='async function fetch_'.$this->name.'('.$values.')';
			$result.='{';
			$result.='	data='.$this->name.'('.$values.');';
			$result.='	let response = await fetch("'.$nextpage.'", { method: "'.$this->method.'", body: data });';
			$result.='	if (response.ok)';
			$result.='	{';
			$result.='  	let y = await response.text();';
//			$result.='		alert(y);'; //Enable to show the output from called form
			$result.='	}';
			$result.='}';
	
			$result.='function '.$this->name.'('.$values.')';
			$result.='{';
			$result.='	var data = new FormData();';
			foreach ($js_input as $name)
			{
				$sname=$this->scramble_field_name($name);
				$result.='	data.append("'.$sname.'", '.$name.');';
			}
			return $result;
		}
		
		function js_data_append($key, $value)
		/*
		 * Making data append and automatically converts string and numbers
		 */
		{
			$skey=$this->scramble_field_name($key);
			
			if (is_numeric($value))
				$result='data.append("'.$skey.'", '.$value.');';
			else
				$result='data.append("'.$skey.'", "'.$value.'");';
			
			return $result;
		}
				
		function js_data($key)
		{
			return $this->js_data_append($key, $this->select($key));
		}

		function js_credentials()
		{
			$result='';

			foreach ($this->available_credentials as $credential)
				if ($this->get_global('settings')->select('encrypt_hidden_parameters')==0)
					$result.=$this->js_data($credential);
				else
					$this->hidden_fields[]=$credential;	

			return $result;
		}
		
		function js_form_close()
		{
			$result='';
			$result.=$this->add_mandatory_input('_input_step', $this->input_step);
			
			foreach ($this->mandatory_fields as $mandatory)
				$result.=$this->html_hidden($mandatory, $this->select($mandatory));
			
			$params=[];
			foreach ($this->hidden_fields as $field)
			{
				switch ($this->fields[$field]['type'])
				{
					case 'dropdown':
						$params[$field]=$this->fields[$field]['selected'];
						break;
					default:
						$params[$field]=$this->fields[$field]['value'];
				}
			}
				
//			if ($this->get_global('settings')->select('scramble_parameters'))
				$params['sf']=$this->credentials->get_scrambled_input();
			
			if ($this->get_global('settings')->select('encrypt_hidden_parameters'))
			{
				$ec=$this->encrypt_html_params($this->action, $params);
				$result.=$this->js_data_append('ec', $ec);
			}
			
			$result.='	return data;';
			$result.='}';
			$result.='</script>';
			
			return $result;
		}
	}
	
	trait miniforms
	{
		/*
		 * Trait for making miniform, usually used on junction  
		 */
		
		function miniform_password(#[\SensitiveParameter] $input, $extra_fields=[], $settings=['submit' => 'save'])
		{
			$extras=[];
			foreach ($extra_fields as $field)
				$extras[$field]=$input[$field];
			
			$field=['type' => 'add_password_input', 'caption' => $settings['caption'] ?? '', 'minlength' => $settings['minlength'] ?? '6',
					'maxlength' => $settings['maxlength'] ?? '16',
					'submit' => $settings['submit'], 'submit_caption' => $input[$settings['submit']] ?? 'Go'];
			$form=new mini_form($this->credentials, 'passwd', null, 'post', null, $field,
				$extras);
				
				/*
				 * Do not override interface if the interface is already defined (useful for jumping between interfaces)
				 */
			if (!$form->select(_junction))
				$form->set_junction($this);
								
			return $form;					
		}
		
		function miniform_date($input, $extra_fields=[], $settings=['submit' => 'save'])
		/*
		 * Please fix extras as its unfiltered hence unsecure!
		 */
		{
			$extras=[];
			foreach ($extra_fields as $field)
				$extras[$field]=$input[$field];
				
			$field=['type' => 'add_date_input', 'caption' => $settings['caption'] ?? '', 'min' => $settings['min'] ?? '2019-10-01',
					'max' => $settings['max'] ?? \date::today(), 'value' => \date::today('Y-m-d'),
					'submit' => $settings['submit'], 'submit_caption' => $input[$settings['submit']] ?? 'Go'];
			$form=new mini_form($this->credentials, 'target_date', null, 'post', null, $field,
					$extras);
				
				/*
				 * Do not override interface if the interface is already defined (useful for jumping between interfaces)
				 */
			if (!$form->select(_junction))
				$form->set_junction($this);
			$form->initialise_date($input);
					
			return $form;
		}
		
		function miniform_daterange($input, $extra_fields=[], $settings=['submit' => 'save']) : mini_form
		{
			$extras=[];
			foreach ($extra_fields as $field)
				$extras[$field]=$input[$field];
				
			$field=['type' => 'add_daterange_input', 'caption' => $settings['caption'] ?? '', 'min' => $settings['min'] ?? '2019-10-01',
					'max' => $settings['max'] ?? \date::today(), 'value' => \date::today('Y-m-d'), 'range' => $settings['range'] ?? 30,
					'submit' => $settings['submit'] ?? 'save', 'submit_caption' => $input[$settings['submit']] ?? 'Go'];
			$form=new mini_form($this->credentials, 'daterange', null, 'post', null, $field, $extras);				
				
			if (!$form->select(_junction))
				$form->set_junction($this);
			$form->initialise_daterange($input);
					
			return $form;					
		}
		
		function miniform_month($input, $extra_fields=[], $settings=['submit' => 'save'])
		{
			$extras=[];
			foreach ($extra_fields as $field)
				$extras[$field]=$input[$field];
				
			$field=['type' => 'add_month_input', 'caption' => $settings['caption'] ?? '', 'min' => $settings['min'] ?? '2019-10',
					'max' => $settings['max'] ?? \date::today('Y-m'), 'value' => '', 'submit' => $settings['submit']  ?? 'save',
					'submit_caption' => $input[$settings['submit']] ?? 'Go'];
			$form=new mini_form($this->credentials, 'target_month', null, 'post', null, $field,
					$extras);
			
			/*
			 * Do not override interface if the interface is already defined (useful for jumping between interfaces)
			 */
			if (!$form->select(_junction))
				$form->set_junction($this);
			$form->initialise_month($input);
					
			return $form;
		}
		
		function miniform_button($input, $extra_fields=[], $settings=['submit' => 'save'])
		/*
		 * This method will create a miniform button for instant button
		 *
		 * check at settings field for 'submit' for submit name of the form, which defaults to save
		 */
		{
			$extras=[];
			foreach ($extra_fields as $field)
				$extras[$field]=$input[$field];
				
			$field=['type' => 'add_submit_button', 'submit' => $settings['submit'] ?? 'save', 'submit_caption' => $input[$settings['submit']] ?? 'Go'];
			$form=new mini_form($this->credentials, 'submit', null, 'post', null, $field,
					$extras);
				
				/*
				 * Do not override interface if the interface is already defined (useful for jumping between interfaces)
				 */
			if (!$form->select(_junction))
				$form->set_junction($this);
					
			return $form;
		}
		
		function miniform_range($input, $extra_fields=[], $settings=['submit' => 'save']) : mini_form
		{
			$extras=[];
			foreach ($extra_fields as $field)
				$extras[$field]=$input[$field];
				
			$field=['type' => 'add_range_input', 'caption' => $settings['caption'] ?? '', 'min' => $settings['min'] ?? '0',
					'max' => $settings['max'] ?? 999,  
					params => [step => $settings[step] ?? 1, decimals => $settings['decimals'] ?? 0],
					'submit' => $settings['submit'] ?? 'save', 'submit_caption' => $input[$settings['submit']] ?? 'Go'];
			$form=new mini_form($this->credentials, 'range', null, 'post', null, $field, $extras);
				
			if (!$form->select(_junction))
				$form->set_junction($this);
			$form->initialise_range($input);
					
			return $form;
		}
		
		function miniform_number($input, $extra_fields=[], $settings=['submit' => 'save'])
		{
			$extras=[];
			foreach ($extra_fields as $field)
				$extras[$field]=$input[$field];
				
			$field=['type' => 'add_number_input', 'caption' => $settings['caption'] ?? '', 'value' => $settings['value'] ?? '', 'min' => $settings['min'] ?? '',
					'max' => $settings['max'] ?? '', 'submit' => $settings['submit'] ?? 'save',
					'submit_caption' => $input[$settings['submit']] ?? 'Go'];
			$form=new mini_form($this->credentials, 'number', null, 'post', null, $field,
					$extras);
				
			/*
			 * Do not override interface if the interface is already defined (useful for jumping between interfaces)
			 */
			if (!$form->select(_junction))
					$form->set_junction($this);
			$form->initialise_number($input);
					
			return $form;
		}
		
		function miniform_dropdown($input, $values, $extra_fields=[], $settings=['submit' => 'save'])
		/*
		 * This is used as a validator miniform for dropdowns such as facilities to ensure that
		 * those are the right thing
		 * 
		 * it is now unfinished but you got the idea
		 */
		{
			$extras=[];
			foreach ($extra_fields as $field)
				$extras[$field]=$input[$field];
				
			$field=['type' => 'add_number_input', 'caption' => $settings['caption'] ?? '', 'value' => $settings['value'] ?? '', 'min' => $settings['min'] ?? '',
					'max' => $settings['max'] ?? '', 'submit' => $settings['submit'] ?? 'save',
					'submit_caption' => $input[$settings['submit']] ?? 'Go'];
			$form=new mini_form($this->credentials, 'number', null, 'post', null, $field,
					$extras);
			if (!$form->select(_junction))
					$form->set_junction($this);
					
			return $form;				
		}
	}
?>