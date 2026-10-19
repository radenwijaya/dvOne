<?php
	class lib_menu extends extensions
	{
		function __construct($settings, $credentials, $login_data)
		{
			parent::__construct($settings, $credentials, [], $login_data);
			
			$this->new_list('adaptive_collapse', 'smart_list', 'record', cGuestChkSec, 'table_adaptive_collapse');
		}
		
		function menu_set_1()
		{
			$result['credential']=$this->credentials->anycredential();
			
			$result['title']='Halo '.$this->credentials->getName();
						
			$result['elements'][]=['caption' => 'Home',
				junction => '_welcome',
				'params' => [],
				'target' => ''];
			
			$result['elements'][]=['caption' => 'Send message',
				'url' => 'sendmsg.php',
				'params' => [],
				'target' => ''];
			
			$result['elements'][]=['caption' => 'Inbox',
				'url' => 'inbox.php',
				'params' => [],
				'target' => ''];
			
			$result['elements'][]=['caption' => 'Outbox',
				'url' => 'sentmsg.php',
				'params' => [],
				'target' => ''];
						
			$result[elements][]=[junction => 'mailbox_junction'];			
			
			$result['elements'][]=['caption' => 'Logout',
				'url' => 'logout.php',
				'params' => [],
				'target' => '_top'];
			
			return $result;
		}
		
		function menu_set_admin()
		{
			$result[credential]=cAdminChkSec;
			
			$result[title]='Administrasi';
			
			$result[elements][]=['caption' => 'System settings', 'url' => 'systemsettings.php', 'params' => [], 'target' => ''];
			
			$result[elements][]=['caption' => 'User management', 'url' => 'staffmgmnt.php', 'params' => [], 'target' => ''];
						
			$settings=$this->get_global('settings');
			foreach ($settings->extensions as $id => $ext)
			{
				if (!empty($ext->extension_credentials))
				{
					$hash=$this->hash($id, $this->credentials->loginkey);
					
					$result[elements][]=[caption => 'User '.$ext->get('vanity_name'), junction => 'extension_user_management', 'params' => ['_extension' => $hash]];
				}
			}
			
			//			$result['elements'][]=['interface' => 'settings_interface'];
			
			return $result;
		}
		
		function available_menu()
		{
			$result=[];
			$result[]=['f' => 'menu_set_1', 'p' => []];
			
			if ($this->credentials->check(cAdminChkSec))
				$result[]=['f' => 'menu_set_admin', 'p' => []];
				
				return $result;
		}
	}
			
	/*
	 * Welcome Page now part of junction, it will be much more flexible compared to just welcome.php
	 */
	class _welcome extends junction
	{
		function form_title($form_title=auto_title)
		{
			return parent::form_title('');
		}
		
		function __construct($credentials, $input, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
		}
		
		function toHTML()
		{
			$result='Hello '.$this->credentials->getName();
			
			return $result;
		}
	}	
?>