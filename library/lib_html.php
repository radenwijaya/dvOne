<?php	
	trait lib_html
	{
		static function open_html_list($ordered=true)
		{
			return $ordered ? '<ol>' : '<ul>';
		}
		
		static function add_html_list_element($sequence, $value)
		{
			if ($sequence)
				$result='<li value="'.$sequence.'">'.$value.'</li>';
			else
				$result='<li>'.$value.'</li>';
					
			return $result;
		}
		
		static function close_html_list($ordered=true)
		{
			return $ordered ? '</ol>' : '</ul>';
		}
		
		static function create_html_list($ordered=true, ...$elements)
		/*
		 * Input elements of the list in array format 'sequence' => 'value'
		 * Elements can be nested, e.g. contains sequences of the same format
		 * 
		 * 'sequence' => the number sequence
		 * 'value' => the value of the list 
		 * 
		 * only work on ordered list
		 */
		{
			$result=self::open_html_list($ordered);
			
			foreach ($elements as $e)
			{				
				$sequence=$e['sequence'] ?? null;
				$value=$e[value];
				$sublist=$e['sublist'] ?? null;
				$ordered_sublist=$e['ordered'] ?? true;
				
				if (is_array($sublist))
				{
					$result.=self::add_html_list_element($sequence, $value);
					$result.=self::create_html_list($ordered_sublist, ...$sublist);
				}
				else
					$result.=self::add_html_list_element($sequence, $value);
			}
			
			$result.=self::close_html_list($ordered);
			
			return $result;
		} 
		
		function family_menu($additional_input, ...$family)
		{
			$page_width=$this->get_global("settings")->select('page_width');
			
			$caption_width=0;
			
			$rows=[]; //row of strings contains HTML for the family menu (row condensed)
			$row=[]; //row of string that contains html each
			foreach ($family as ['uri' => $uri, 'caption' => $caption, 'params' => $params])
			{
				$caption_width+=strlen($caption)+1.5;
				
				if ($caption_width>$page_width)
				/*
				 * Ensure so it will not exceed the page width
				 */
				{
					$rows[]=implode('&nbsp;|&nbsp;', $row);
					$row=[];
					$caption_width=0;
				}
				
				$row[]=$this->make_hyperlink($uri, $caption,
					$this->credentials->primary(), array_merge($additional_input, $params ?? []), '_self');				
			}
					
			if (!empty($row))
				$rows[]=implode('&nbsp;|&nbsp;', $row);
				
			return implode("<br>", $rows);
		}
		
		function encrypt_html_params($uri, $params)
		{
			$params['uri']=$uri;
			$params_pk=json_encode($params);			
			
			/*
			 * pk is per login basis
			 */
			$pk=hash_hmac($this->get_global('settings')->select('encrypt_parameters_auth'), $params_pk, (get_class($this) == 'credentials') ? $this->loginkey : ($this->credentials->loginkey ?? ''));
			$params['pk']=$pk;
			
			/*
			 * data integrity is per click basis 
			 */
			$params=s_lib::add_data_integrity(1, $params);
			
			$params_string=json_encode($params);
			$ec_string=s_lib::encrypt($params_string,
				$this->get_global('settings')->select('encrypt_parameters_password'),
				$this->get_global('settings')->select('encrypt_parameters_iv'));
			
			return $ec_string;
		}
		
		function encrypt_hyperlink_params($uri, $params)
		{
			$ec_params=http_build_query(['ec' => $this->encrypt_html_params($uri, $params)]);
			
			return $ec_params;
		}
		
		function decrypt_hyperlink_params($string)
		{
			$login_data=[];
			
			$get_str=s_lib::decrypt($string,
				$this->get_global('settings')->select('encrypt_parameters_password'),
				$this->get_global('settings')->select('encrypt_parameters_iv'));
			
			$data=json_decode($get_str, true);
			
			$login_data=s_lib::check_data_integrity(1, $data);
			
			return $login_data;
		}
				
		function make_hyperlink($uri, $caption, $active_credential, $params, $target, &$link_id=0)
		/*
		 * returns hyperlink and return its 
		 */
		{
			/*			foreach ($params as $key => $value)
			 $params_string.="&".$key."=".$value;*/
			$uri=$uri ?? 'page.php';
			if ($this->get_global('settings')->select('encrypt_parameters'))
			{
				$credentials=$this->credentials->credential_data($active_credential);
				$params=array_merge($credentials, $params ?? []);
				
				$ec_params=$this->encrypt_hyperlink_params($uri, $params ?? []);
				
				$link_id=$this->quick_hash($uri.$ec_params);
				$result="<a target=\"".$target."\" href=\"$uri?".$ec_params."\">$caption</a>";
			}
			else
			{
				$credentials=$this->credentials->credential_data($active_credential, true);
				$params_string=http_build_query($params ?? []);
				
				$link_id=$this->quick_hash($uri.$credentials."&".$params_string);
				$result="<a target=\"".$target."\" href=\"$uri?".$credentials."&$params_string\">$caption</a>";
			}
			
			return $result;
		}
		
		function make_uri($uri, $params=[])
		{
			if ($this->get_global('settings')->select('encrypt_parameters'))
			{
				$params['uri']=$uri;
				$credentials=$this->credentials->credential_data();
				
				$params=array_merge($params, $credentials);
				$ec_params=$this->encrypt_hyperlink_params($uri, $params);
				
				$result=$uri.'?'.$ec_params;
			}
			else
			{
				$credentials=$this->credentials->credential_data($this->credentials->primary());
				
				$params_string=http_build_query($params);
				//				foreach ($params as $key => $value)
					//					$params_string.="&".$key."=".$value;
				
				$result=$uri.'?'.$credentials.'&'.$params_string;
			}
			
			return $result;
		}
		
		static function html_array_arguments($arguments, $k_v_delimiter='=', $value_delimiter=' ')
		{
			$result_array=[];
			foreach ($arguments as $key => $value)
			{
				$result_array[]=$key.$k_v_delimiter."'".htmlspecialchars($value, ENT_HTML5, 'UTF-8')."'";
			}
			return implode($value_delimiter, $result_array);
		}
		
		static function add_html_table_line($tag, ...$columns)
		{
			$result='<tr>';
			foreach ($columns as $column)
			{
				if (is_array($column))
				{
					$tag_params=array();
					foreach ($column as $name => $value)
					{
						switch ($name)
						{
							case '_tag':	//this is used to force tag into special tag
								$tag=$value;
								break;
							case '_value':
								$column_display=$value;
								break;
							default:
								$tag_params[]="$name='$value'";
								break;
						}
					}
					
					$tag=$tag.' '.implode(' ', $tag_params);
				}
				else
				{
					$column_display=$column;
				}
				
				$result.='<'.$tag.'>'.$column_display.'</'.$tag.'>';
			}
			$result.='</tr>';
			
			return $result;
		}
		
		static function add_html_table_header(...$columns)
		{
			return self::add_html_table_line('th', ...$columns);
		}
		
		static function add_html_table_row(...$columns)
		{
			return self::add_html_table_line('td', ...$columns);
		}
		
		static function open_html_table($arguments=array(), ...$columns)
		{
			if (!empty($arguments))
			{
				$arguments_string=self::html_array_arguments($arguments);
				$result="<table $arguments_string>";
			}
			else
			{
				$result="<table>";
			}
			
			if ($columns)
				$result.=self::add_html_table_header(...$columns);
				
			return $result;
		}
		
		static function close_html_table(...$columns)
		{
			$result='';
			if ($columns)
				$result.=self::add_html_table_line('td', ...$columns);
				
			$result.='</table>';
			return $result;
		}
		
		static function html_back($space=false)
		{
			if ($space)
				return '&nbsp;<button onclick="window.history.back()">Back</button>';
				else
					return '<button onclick="window.history.back()">Back</button>';
		}
		
		static function html_print($space=false, $autohide=true)
		{
			if ($autohide)
				$onclick="document.getElementById('_btn_print').hidden=true;";
				
			$onclick.='window.print();';
				
			if ($autohide)
				$onclick.="document.getElementById('_btn_print').hidden=false;";
					
			if ($space)
				return '&nbsp;<button id="_btn_print" onclick="'.$onclick.'">Print</button>';
			else
				return '<button id="_btn_print" onclick="'.$onclick.'"">Print</button>';
		}		
	}
	
	class html
	{
		use lib_html;
		
		static private function void($type, array $attr)
		/*
		 * this function will create void element (element withoug end tag)
		 * 
		 * format $params['name'] => 'value'
		 * $params['id'] => 'value'
		 *
		 * for certain attributes, such as checked, use null for value 
		 */
		{
			$attributes='';
			foreach ($attr as $key => $value)
				if (!is_null($value))
					$attributes.=' '.$key.'="'.$value.'"';
				else
					$attributes.=' '.$key;
			
			return '<'.$type.$attributes.'>';
		}
		
		static private function normal($s, $type, array $params)
		/*
		 * Params includes name, id, attributes, etc
		 * 
		 * format $params['name'] => 'value'
		 * $params['id'] => 'value'
		 *
		 * for certain attributes, such as checked, use null for value 
		 * 
		 * $s can be other elements.  
		 */
		{
			$parameters='';
			foreach ($params as $key => $value)
				if (!is_null($value))
					$parameters=' '.$key.'="'.$value.'"';
				else
					$parameters=' '.$key;
				
			return '<'.$type.$parameters.'>'.$s.'</'.$type.'>';
		}
		
		static function h1($s, $params=[])
		{
			return self::normal($s, 'h1', $params);
		}
		
		static function h2($s, $params=[])
		{
			return self::normal($s, 'h2', $params);
		}
		
		static function h3($s, $params=[])
		{
			return self::normal($s, 'h3', $params);
		}
		
		static function h4($s, $params=[])
		{
			return self::normal($s, 'h4', $params);
		}
		
		static function b($s, $params=[])
		{
			return self::normal($s, 'strong', $params);
		}
		
		static function br($x=1)
		{
			$result='<br>';
			for ($i=1; $i<$x; $i++) 
				$result.='<br>';
			
			return $result;
		}
		
		static function nbsp($x=1)
		{
			$result='&nbsp;';
			
			for ($i=1; $i<$x; $i++)
				$result.='&nbsp;';
				
			return $result;
		}		
		
		static function input($id, $args=[])
		{			
			$args['id']=$id;
			$args['name']=$id;
			
			$result=self::void('input', $args);
			
			return $result;
		}
		
		static function img($src=null, $alt=null, $id=null, $width=0, $height=0, $attr=[])
		{			
			if ($src)
				$attr['src']=$src;
			
			if ($id)
				$attr['id']=$id;
			
			if ($alt)
				$attr['alt']=$alt;
				
			if ($width)
				$attr['width']=$width;

			if ($height)
				$attr['height']=$height;
					
			$result=self::void('img', $attr);
			
			return $result;
		}
		
		static function list($ordered=true, ...$elements)
		{
			return self::create_html_list($ordered, ...$elements);
		}		
	}
	
	trait repeating_column_html_table
	{
		use lib_html;
		
		var $_rows;
		
		function add_repeating_row(...$columns)
		{
			$this->_rc_rows[]=$columns;
		}
		
		function initialise_repeating_row($repeat)
		{
			$this->_rc_data=[];
			$this->_rc_data=['repeats' => $repeat];
			
			$this->_rc_rows=[];
		}
		
		function add_repeating_tally($columns)
		/*
		 * Columns data is defined like this
		 * ['col' => X, 'function' => 'values_sum']
		 */
		{
			$this->_rc_data['tally']=$columns;
		}
		
		function add_conclusion($columns)
		/*
		 * Add final conlusion can be input as value or function
		 * after tally value
		 * 
		 * Columns data is defined like this
		 * ['col' => X, optional 'function' => 'whatever', 'params' => 'param' 
		 *  will be used as value when no function is defined]
		 */
		{
			$this->_rc_data['conclusion']=$columns;
		}
		
		function add_repeating_header($params, ...$header)
		{
			$this->_rc_header=$header;
			
			$this->_rc_data['params']=$params;
		}
		
		function make_repeating_table()
		{
			$count=count($this->_rc_rows);
			
			$repeat=$this->_rc_data['repeats'];
			if ($count % $repeat ==0)
				$row_count=round($count/$repeat);
			else
				$row_count=ceil($count/$repeat);
					
			$value_tally=[];
			$function_tally=[];
			foreach ($this->_rc_data['tally'] as ['col' => $col, 'function' => $f])
				$function_tally[$col]=$f;
						
			/*
			 * Formatting into two columns
			 */
			$row_no=0;
			$reps=0;
			$rows=[];
			foreach ($this->_rc_rows as $column)
			{
				if (!$rows[$row_no])
					$rows[$row_no]=[];
								
				$rows[$row_no]=[...$rows[$row_no], ...$column];

				if ($this->_rc_data['tally'])
					foreach ($this->_rc_data['tally'] as ['col' => $col])
						$value_tally[$col][$reps][]=$column[$col];

				$row_no++;
				if ($row_no>=$row_count)
				{
					$reps++;
					$row_no=0;
				}
			}
						
			$header=[];
			for ($i=0; $i<$repeat; $i++)
				$header=[...$header, ...$this->_rc_header];
							
			$result=$this->open_html_table($this->_rc_data['params'], ...$header);
			foreach ($rows as $row)
				$result.=$this->add_html_table_row(...$row);

			if ($this->_rc_data['tally'])
			{
				$tally=[];
				for ($reps=0; $reps<$repeat; $reps++)
				{
					$rep_tally=[];
					foreach (array_keys($this->_rc_header) as $col)
					{
						if ($value_tally[$col])
							$rep_tally[$col]=$this->{$function_tally[$col]}($value_tally[$col][$reps]);
						else
							$rep_tally[$col]='&nbsp;';
					}
					$tally=[...$tally, ...$rep_tally];
				}
				
				$result.=$this->add_html_table_row(...$tally);
			}
			
			$total_column=count($header);
			$conclusion=[];
			if ($this->_rc_data['conclusion'])
			{
				$cs=[];
				foreach ($this->_rc_data['conclusion'] as $key => ['col' => $col])
					$cs[$col]=$this->_rc_data['conclusion'][$key];
				
				for ($col=0; $col<$total_column; $col++)
				{
					if ($cs[$col])
					{
						$f=$cs[$col]['function'];
						$p=is_array($cs[$col]['params']) ? $cs[$col]['params'] : [$cs[$col]['params']];
						
						if (method_exists($this, $f))
							$value=$this->{$f}(...$p);
						else
							$value=is_array($p) ? array_values($p)[0] : $p;
					}
					else
						$value='&nbsp;';
						
					$conclusion[]=$value;
				}
						
				$result.=$this->close_html_table(...$conclusion);
			}
			else
				$result.=$this->close_html_table();
								
			return $result;
		}
	}	
				
	trait t_layouter
	/*
	 * Use this on lib_form or junction
	 */
	{
		private layout_maker $_layout_maker;
		
		function to_layout_name($name)
		/*
		 * Override on the class or just use it
		 */
		{
			return layout_maker::to_layout_name($name);
		}
		
		function set_layout_maker(layout_maker $layout_maker)
		{
			$this->_layout_maker=$layout_maker;
		}
		
		function has_layout_maker(): bool
		{
			return (isset($this->_layout_maker) && ($this->_layout_maker instanceof layout_maker));
		}
		
		function layout_maker() : layout_maker
		{
			return $this->_layout_maker;
		}
		
		function get_input_step()
		{
			return isset($this->input_step) ? $this->input_step : 0;
		}
	}
	
	trait t_layout_maker
	/*
	 * Layout maker trait is a trait that contains all that is needed to make a layout
	 * 
	 * use this only for smaller class
	 * 
	 * for multiform input, please use complete name on fields of the assign_layout
	 */
	{
		use lib_html;
		
		var $_layout_step;
		
		function assign_layout($fields)
		/*
		 * This will output fields and output layout
		 */
		{										
			$layout_function=$this->layout_function();
			
			$layout_sequence=$this->{$layout_function}();
			
			$layouts=implode('', $layout_sequence);
			
			$replacing=[];
			foreach ($fields as $name => $value)
			{
				if ($lm_name=$this->layouter->to_layout_name($name))				
					$replacing[$lm_name]=$value;
			}
			
			$output=str_replace(array_keys($replacing), array_values($replacing), $layouts);
			
			return $output;
		}
		
		function set_layout_step(...$steps)
		/*
		 * This method supports two types of input arguments 
		 * First ...$array (dont forget to unpack the array!)
		 * e.g. $array=['stepOne' => 'layoutOne', 'stepTwo' => 'layoutTwo']
		 * set_layout_step(...$array)
		 * 
		 * or simply layoutOne, layoutTwo
		 * set_layout_step('layoutOne', 'layoutTwo');
		 */
		{
			$this->_layout_step=[];
			
			foreach ($steps as $key => $step)
				$this->_layout_step[$key]=$step;			
		}			
	}
		
	class layout_maker extends html
	/*
	 * This is a class, that will needs to be derived when using layout maker 
	 * 
	 * pros of using derivatibes of this class is reusablility.  A layout can be reused for many 
	 * times 
	 * 
	 * also for code readability having each class as its own layout maker will result on standard
	 * layout
	 * 
	 * but the cons are more codes
	 * 
	 * layout maker can also be made  on junction as well if needed.  so a layout from a form  
	 * can be arranged into junction for multiple form in a junction 
	 */
	{
		use t_layout_maker;
		
		var $layouter;
		
		function __construct(junction | lib_form $layouter)
		/*
		 * Layouter class can be junction or lib_form, and must use t_layouter
		 */		
		{
			if (method_exists($layouter, 'to_layout_name'))
			{
				$this->layouter=$layouter;
				$this->layouter->set_layout_maker($this);
			}
			else
			{
				throw new Exception("Layouter must use t_layouter trait");
			}
		}
				
		function layout_miniform_month()
		/*
		 * Example Layout for miniform month as an example of miniform month layout
		 */
		{
			$result=[];
			
			$result[]='%form_open%';
			$result[]='%target_month%&nbsp;%save%';
			$result[]='%credential%';
			$result[]='%form_close%';
			
			return $result;
		}
		
		function layout_miniform_daterange()
		/*
		 * Example Layout for miniform month as an example of miniform month layout
		 */
		{
			$result=[];
			
			$result[]='%form_open%';
			$result[]='%daterange%&nbsp;%save%';
			$result[]='%credential%';
			$result[]='%form_close%';
			
			return $result;
		}		

		function layout_miniform_range()
		/*
		 * Example Layout for miniform month as an example of miniform month layout
		 */
		{
			$result=[];
			
			$result[]='%form_open%';
			$result[]='%range%&nbsp;%save%';
			$result[]='%credential%';
			$result[]='%form_close%';
			
			return $result;
		}
		
		function layout_staf_management()
		/*
		 * Layout for staff management, e.g. insert and delete staff from a list
		 */
		{
			$result=[];
			
			$result[]='%form_open%';
			$result[]='<div>%caption%</div>';
			$result[]='<div>%employee_list%</div>';
			$result[]='<div>%save%</div>';
			$result[]='%credential%';
			$result[]='%form_close%';
			
			return $result;
		}
		
		static function to_layout_name($name)
		{
			return '%'.$name.'%';
		}		
				
		function layout_function()
		{
			return $this->_layout_step[$this->layouter->get_input_step()];		
		}
	}
?>