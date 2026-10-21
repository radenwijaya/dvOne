<?php
	class menu_page extends secure_page
	{
		var $module_menu_items;
		var $form_input_step;
		
		function __construct($credentials, $input, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
						
			if (!$collapse_data=$this->get_global('lib_menu')->get_list('adaptive_collapse')->
				db_select($this->quick_hash($credentials->username)))
				$collapse_data=$this->get_global('lib_menu')->get_list('adaptive_collapse')->new($this->quick_hash($credentials->username));

			$link_ids=[]; 
			$this->get_module_menu();
				
			$modules_menu=$this->write_module_menu_items($credentials, $collapse_data, $link_ids);
			
			$link_names=array_keys($link_ids);
						
			/*
			 * Clearing collapse data if no longer used
			 */
			foreach (array_keys($collapse_data->select('collapse') ?? []) as $key)
				if (!$link_ids[$key])
					$collapse_data->remove('collapse', $key);
						
			$collapsed=[];
			$menu_collapsed=[];
			foreach ($link_ids as $key => $ids)
			{
				if ($collapse_data->select('collapse', $key)=='none')
				{
					$collapsed=[...$collapsed, ...$ids];
					$menu_collapsed[]=$key;
				}
			}
			
			$link_ids['init_item_collapsed']=$collapsed;
			$link_ids['init_menu_collapsed']=$menu_collapsed;
								
			$js_variable=new js_variable($credentials, $link_ids);
			$this->add_features($js_variable);
			
			$interface=new adaptive_collapse_interface($credentials, $input, $secure_db, $readonly_db, $link_names);
			$contents=$interface->output();
			
			foreach ($modules_menu as $module_menu)
				$contents[value].=$module_menu;
				
			$this->form_input_step=$interface->form->input_step;
			
			$this->pageContents=$contents;
		}
		
		function write_output()
		{
			return ($this->form_input_step==0);
		}
		
		function get_module_menu()
		/*
		 * This method is used to make module menu item
		 */
		{
			$settings=$this->get_global('settings');
			foreach ($settings->select('extensions') as $extension_class)
			{
				$c=$this->get_global($extension_class);
				$menu_set=$c->available_menu();
								
//				$menu_set[]=['f' => 'settings_menu', 'p' => []];
				
				foreach ($menu_set as ['f' => $menu_function, 'p' => $f_params])
				{
					if (!empty($menu=$c->get_menu($menu_function, ...$f_params)))
					{
						$menu_credential=is_array($menu['credential']) ? $menu['credential'] : [$menu['credential']];
//						if ($menu['title']=='Gudang Benang S1') var_dump($menu);
						$this->add_module_menu($menu_credential, $menu['title'], $menu['params']);
						foreach ($menu['elements'] as ['url' => $url, 'class' => $class, junction => $interface, 
								 caption => $caption, params => $params, 'target' => $target, 
								 'interop' => $interop])
						{
							if ($interface)
								$class=$interface;
										
							if ($class[0]=='\\')
							{
								if ($ns=$c->select('namespace'))									
									$params['_namespace']=$ns;
							}
							else
								if (!$interop)									
									if ($ns=$c->select('namespace'))
									{
										$class=$ns.'\\'.$interface;
										$params['_namespace']=$ns;
									}
								
							if (($class) && (!$url))
							{		//var_dump($c->select('namespace'));
								if (class_exists($class))
								{									
									$params[_junction]=$interface;												
									
									if (!$caption)
										$caption=$class::form_caption();								
								}
								else 
								{//var_dump($menu['elements']);									
									$caption=$caption ?? class_not_exists;
								}
							}
							
							if ((!$url) && ($interface))
								$url='page.php';

							$this->add_module_menu_elements($menu_credential, $menu['title'], $url, $caption, $params, $target ?: 'contents');
						}
					}
				}
			}
		}
		
		function add_module_menu($c_name, $title, $params)
		{
			$key=$this->quick_hash(implode(',', $c_name).$title);
			$this->module_menu_items[$key]=['credential' => $c_name, 'title' => $title, 'params' => $params];
		}
		
		function add_module_menu_elements($c_name, $title, $url, $caption, $params, $target='contents')
		{
			$key=$this->quick_hash(implode(',', $c_name).$title);
			$this->module_menu_items[$key]['elements'][]=['url' => $url, 'caption' => $caption, 'params' => $params, 'target' => $target];
		}
		
		function write_module_menu_items($credentials, $collapse_data, &$link_ids)
		/*
		 * Making the whole menu items from top to bottom based on menu retreived from get_module_menu()
		 */
		{
			$result=[];
			$link_ids=[];
			foreach ($this->module_menu_items as ['credential' => $c_name, 'title' => $title, 'elements' => $elements, 'params' => $m_params])
			{				
				if ($credentials->check(...(is_array($c_name) ? $c_name : [$c_name])))
				{
					$head_id=$this->quick_hash($title);
					$link_ids['v'.$head_id]=[];
					
					$menu='<br><div class="boxed_div">';
					
					if (is_array($elements) && (count($elements))>=1)
						//Add menu collapse only when elements count is > 1
					{
						$icon=collapsable_menu::get_icon($collapse_data ? $collapse_data->select('collapse', 'v'.$head_id) : null);
						$menu.="<b><span id='".$head_id."' onclick='execute_collapse(".'v'.$head_id.', "'.$head_id.'"'."); fetch_collapser(".'"v'.$head_id.'"'.");'>$icon</span> </b>";
					}
					
					$menu.="<b>".$title.'</b>';
					
					foreach ($elements as ['url' => $url, 'caption' => $caption, 'params' => $params, 'target' => $target])
						if (($caption!==class_not_exists) && (($params[_junction]) || ($url)))		
						{
							if ($m_params)
								$params=$params+$m_params;
							$link_id='';
							$menu.=$this->make_menu_item($url, $caption, $c_name, $params, $target ?: 'contents', $link_id);
							
							$link_ids['v'.$head_id][]=$link_id;							
						}
						else 
						{
							if ($caption!==class_not_exists)
							{
								if ($params['highlight'])
									$c='<b>'.$caption.'</b>';
								else
									$c=$caption;
								
								$c_id=$this->quick_hash($caption);
								$link_ids['v'.$head_id][]=$c_id;
								
								$menu.="<p id='".$c_id."'>&nbsp;&nbsp;&nbsp; ".$c."</p>";
							}
							else
								$menu.="<p>".class_not_exists."</p>";
						}
						
					$menu.='</div>';

					$result[]=$menu;
				}				
			}
					
			return $result;
		}
		
		function make_menu_item($callback, $caption, $active_credential, $params=array(), $target='contents', &$link_id=0)
		/*
		 * Making single menu item element
		 */
		{			
			$link=$this->make_hyperlink($callback, $caption, $active_credential, $params, $target, $link_id);
			$result="<p id='".$link_id."'>&nbsp;&nbsp;&nbsp; ".$link."</p>";
			
			return $result;
		}		
	}
	
	class adaptive_collapse_form extends inputform_control
	{
		use javascript_form;
		
		function __construct($credentials, $name, $secure_db, $readonly_db, $link_names)
		{
			parent::__construct($credentials, $name, 'leftmenu.php', 'post', null, $secure_db, $readonly_db);

			$this->rv_input_step=['Save'];
			$this->rv_output_step=['stepZero'];
			
			$items=[];
			foreach ($link_names as $link_name)
				$items[$link_name]=['value' => $link_name, 'display' => $link_name];
			$this->add_dropdown('', 'title_list', $items, '', []);
			
			$this->add_submit_button();
			
			$this->add_credential(...$credentials->available_credentials());			
		}
		
		function stepZero()
		{
			$result=$this->js_form_open('title_list');
			$result.=$this->js_data_append('save', 'Save');
			$result.=$this->js_credentials();
			$result.=$this->js_form_close();
			
			return $result;
		}
		
		function insert()
		{
			$owner=$this->quick_hash($this->credentials->username);
			$new=false;
			if (!($col_data=$this->get_global('lib_menu')->get_list('adaptive_collapse')->db_select($owner)))
			{	
				$new=true;
				$col_data=$this->get_global('lib_menu')->get_list('adaptive_collapse')->new($owner);
			}
			
			$mode=$col_data->select('collapse', $this->select('title_list'));
			switch ($mode)
			{	
				case 'none':
					$new_mode='block';
					break;
				default: case 'block':
					$new_mode='none';
					break;					
			}

//			$col_data->dispose();
			$col_data->addData('collapse', $new_mode, $this->select('title_list'));
			
			if ($new)
				$col_data->flush();
			else
				$col_data->update();
		}
	}
	
	class adaptive_collapse_interface extends junction
	{
		function __construct($credentials, $input, $secure_db, $readonly_db, $link_names)
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
						
			$this->form=new adaptive_collapse_form($credentials, 'collapser', $secure_db, $readonly_db, $link_names);
			$this->form->set_junction($this);
			
			if ($this->form->input($input)==
				$this->form->final_input_step())			
				$this->form->insert();
		}
	}
?>