<?php
	class collapsable_menu extends features
	{
		/*
		 * Menu is 3 parts
		 * Super menu : unimplemented yet - is a menu of an extension and collapsing it will collapse 
		 * the whole menu related to that extension
		 * Menu: is the menu of an extension
		 * Element: is the element of an menu
		 * 
		 * to collapse a menu:
		 * call menu collapse / switch  
		 * call menu_element_collapse with parameters of the menu items, and its menu id
		 * 
		 */
		var $name;
		
		function __construct($credentials, $name)
		{
			$this->name=$name;
			parent::__construct($credentials, [], $name, ['menu_element_collapse', 'switch_collapse', 'execute_collapse', 'mark_collapse', 'mark_expand', 'get_menu_mark', 'adaptive_collapse']);
		}
		
		static function get_icon($style)
		{
			switch ($style)
			{
				case 'none':
					$icon='&#9776;';
					break;
				default: case 'block':
					$icon='&#9783;';
					break;
			}			
			
			return $icon;
		}
		
		function adaptive_collapse()
		/*
		 * Used on initialisation, can actually be replaced by calling multiple menu_element_collapse
		 */
		{
			$result='<script type="text/javascript">';
			$result.='function adaptive_collapse(fields)';
			$result.='{';
			$result.='	for (let i=0; i<fields.length; i++)';
			$result.='	{';
			$result.='		document.getElementById(fields[i]).style.display="none";';
			$result.='	}';
			$result.='}';
			$result.='</script>';
			return $result;
		}
		
		function mark_collapse()
		/*
		 * Set a manu to collapse
		 */
		{
			$result='<script type="text/javascript">';
			$result.='function mark_collapse(this_id)';
			$result.='{;';
			$result.='	document.getElementById(this_id).innerHTML="'.$this->get_icon('none').'";';
			$result.='	return "none";';
			$result.='}';			
			$result.='</script>';
			return $result;			
		}
		
		function mark_expand()
		/*
		 * Set a manu to collapse
		 */
		{
			$result='<script type="text/javascript">';
			$result.='function mark_expand(this_id)';
			$result.='{;';
			$result.='	document.getElementById(this_id).innerHTML="'.$this->get_icon('block').'";';
			$result.='	return "block";';
			$result.='}';
			$result.='</script>';
			return $result;
		}		
		
		function get_menu_mark()
		/*
		 * Used to get the marking of the menu
		 */
		{
			$result='<script type="text/javascript">';
			$result.='function get_menu_mark(menu_id)';
			$result.='{';
			$result.='	let result="block";';
			$result.='	switch (document.getElementById(menu_id).innerHTML)';
			$result.='	{';
			$result.='		case "'.html_entity_decode($this->get_icon('block')).'":';
			$result.='			result="block";';
			$result.='			break;';
			$result.='		case "'.html_entity_decode($this->get_icon('none')).'":';
			$result.='			result="none";';
			$result.='			break;';
			$result.='	}';			
			$result.='	return result;';
			$result.='}';
			$result.='</script>';
			return $result;
		}		
		
		function switch_collapse()
		/*
		 * Used to set the menu to collapse
		 */
		{
			$result='<script type="text/javascript">';
			$result.='function switch_collapse(menu_id)';
			$result.='{';
			$result.='	let result="block";';
			$result.='	switch (document.getElementById(menu_id).innerHTML)';
			$result.='	{';	
			$result.='		case "'.html_entity_decode($this->get_icon('block')).'":';
			$result.='			result=mark_collapse(menu_id);';
			$result.='			break;';			
			$result.='		case "'.html_entity_decode($this->get_icon('none')).'":';
			$result.='			result=mark_expand(menu_id);';
			$result.='			break;';
			$result.='	}';
//			$result.='	alert(document.getElementById(this_id).innerHTML); alert("'.$this->get_icon('block').'");';
			$result.='	return result;';			
			$result.='}';
			$result.='</script>';
			return $result;			
		}
		
		function execute_collapse()
		/*
		 * Switching to collapse and then changing the menu
		 * for making super menu collapse, do not use swtich_collapse
		 * 
		 * instead use mark_collapse / mark_expand
		 */
		{
			$result='<script type="text/javascript">';
			$result.='function execute_collapse(fields, menu_id)';
			$result.='{';
			$result.='	switch_collapse(menu_id);';
			$result.='	menu_element_collapse(fields, menu_id);';
			$result.='}';
			$result.='</script>';
			return $result;			
		}
		
		function menu_element_collapse()
		/*
		 * Used to actually collapse or expand the menu according to the style
		 */
		{
			$result='<script type="text/javascript">';
			$result.='function menu_element_collapse(fields, menu_id)';
			$result.='{';			
			$result.='	style=get_menu_mark(menu_id);';
			$result.='	for (let i=0; i<fields.length; i++)';
			$result.='	{';
			$result.='		element=document.getElementById(fields[i]);';
			$result.='		element.style.display=style;';
			$result.='	}';
			$result.='}';
			$result.='</script>';
			return $result;
		}
	}
?>