<?php
//    include_once("lib_main.php");	
	include_once('components/collapsable_menu.php');
	/*
	 * Menu is a page, it is a special mode of operation, and it is not a junction.  
	 * 
	 * Should be moved to form/junction, eventually.
	 */
	class menu extends menu_page
	{
		var $module_menu_items;
		
		function __construct($credentials, $input, $secure_db, $readonly_db)			
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
			
			$settings = $this->get_global('settings');
			$settings->load_extensions();
				
			$menu_collapse=new collapsable_menu($credentials, 'menu_collapse_control');
			
			$execute=new execute_function($credentials, 'execute', ['adaptive_collapse'], 
				['adaptive_collapse' => ['init_item_collapsed']]);
			
			$this->add_features($menu_collapse, $execute);
		}									    	       	      	    	    	    
   	}
?>