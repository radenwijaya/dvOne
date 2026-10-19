<?php
	include_once("lib_baseclass.php");
	
	class messaging_form extends junction
	{
		
	}
	
	class message extends entity
	{
		var $secure_db;
		
		function __construct($to, $from, $subject, $body, $secure_db)
		{
			$body=$secure_db->SQLSafeString($body);
			$subject=$secure_db->SQLSafeString($subject);
			$this->secure_db=$secure_db;
			parent::__construct(array('to' => $to, 'from' => $from, 'subject' => $subject, 'body' => $body));
		}
		
		function send()
		{
			$from=$this->select('from');
			$to=$this->select('to');
			$text=$this->select('subject').'<br>'.$this->select('body');
			
			$query="INSERT INTO ".ctableMOTD."(sender, reciver, message)
    	            VALUES('$from', '$to', '$text')";
			
			$this->secure_db->query($query);			
		}
	}
?>