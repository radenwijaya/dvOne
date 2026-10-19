<?php
	class mailbox_form extends inputform_control
	{
		
	}

	class mailbox_junction extends junction
	{
		function form_title($form_title='')
		{
			return parent::form_title('Mailbox');
		}
		
		static function form_caption($mods='')
		{
			return 'Mailbox';
		}
		
		function __construct($credentials, $input, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
			
			$this->form=$this->miniform_password($input, [], ['caption' => 'Password']);
			$this->form->input($input);
			
			if ($this->form->select('passwd'))
			{
				/*
				 * Chekck for password
				 */
				if ($this->credentials->password_verify($this->form->select('passwd')))
					var_dump('Valid');
				else
					var_dump('inValid');
			}
		}
		
		function toHTML()
		{
			$result=$this->form->toStringFormat();
			
			return $result;
		}
	}
?>