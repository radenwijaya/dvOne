<?php
	/*
	 * Internal texting system for users, works sort of SMS and IM system hybrid
	 * 
	 * First will ask user for password, if public / private key not existed it will not work
	 * 
	 * This system uses public / private key for security with public key is stored in a database libirary
	 * 
	 * text_message is always encrypted with public key 
	 * mailbox-> every text message inbox will be copied twice and encrypted with self and other party's public key
	 * 
	 * public_keys is keyed with plain username
	 * private_keys is keyed with hash_hmac of username & password 
	 */

	include_once("texting_mailbox.php");

	class texting extends extensions
	{		
		function __construct($settings, $credentials, $login_data)
		{
			$extension_credentials=[];
			
			parent::__construct($settings, $credentials, $extension_credentials, $login_data);
			
			$this->new_list('public_keys', 'smart_list', 'public_key', cAdminChkSec, 'table_public_keys', dbm::dbm_record_1);			
			$this->new_list('private_keys', 'smart_list', 'private_key', cAdminChkSec, 'table_private_keys', dbm::dbm_record_1);
			
			$mailbox=$this->new_list('mailbox', 'text_list', 'text_message', cAdminChkSec, 'table_inbox', dbm::dbm_record_1);
			$mailbox->add_db_index('texting_pairs', 10, true);						
		}		
	}
	
	class text_list extends smart_list
	{
		function get_texting_pair($from, $to)
		{
			$tp1=text_message::make_texting_pairs($from, $to);
			$tp2=text_message::make_texting_pairs($from, $to);
			
			$this->get_extension('texting')->get_list('mailbox')->db_index_fill(['texting_pairs' => $tp1]);
			$this->get_extension('texting')->get_list('mailbox')->db_index_fill(['texting_pairs' => $tp2]);						
		}
	}
	
	class text_message extends record
	{
		/*
		 * Two type of text message
		 * contents can be encrypted or public, from shella its not encrypted 
		 * 
		 * no attachemnt support for now
		 */
		
		static function make_texting_pairs($sender, $receiver)
		{
			return $sender.'_'.$receiver;
		}
		
		function texting_pairs()
		/*
		 * This will text indexed as from and to pair 
		 * 
		 * to receive the data, just reverse the sender pair, e.g. from - to and to - from
		 */
		{			
			return $this->make_texting_pairs($this->get('sender'), $this->get('receiver'));
		}
		
		function send_text($sender, $receiver, $sender_key, $receiver_key, #[\SensitiveParameter] $text)
		/*
		 * this will add text message of this
		 */
		{
			$i=0;
			
			$this->set('T', $sender);
			$this->set('R', $receiver);
			
			$chunks=str_split($text, 128);
			foreach ($chunks as $m)
			{
				$sender=''; $receiver='';
	
				$sender=openssl_public_encrypt($m, $sender, $sender_key);
				$receiver=openssl_public_encrypt($m, $receiver, $receiver_key);
				
				$this->set('text', $sender, 'T', $i);
				$this->set('text', $receiver, 'R', $i);
				
				$i++;
			}
		}
		
		function get_text($consignee, #[\SensitiveParameter] $private_key) : SensitiveParameterValue
		/*
		 * This will return the text
		 */
		{
			$chunks=[];
			if ($this->get("T")==$consignee)
			{
				/*
				 * Sender Mode
				 */
				$chunks=$this->get('text', 'T');				
			}
			else
			if ($this->get('R')==$consignee)
			{
				$chunks=$this->get('text', 'R');				
			}
			
			if (!empty($chunks))
			{
				$temp=[];
				foreach ($chunks as $c)
				{
					$r='';
					openssl_private_decrypt($c, $r, $private_key);					
					$temp[]=$r;
				}
				
				$result=implode("", $temp);
			}
			
			return $result;
		}
	}
	
	class public_key extends record
	{
		/*
		 * This is a record used to store public key
		 */
	}
	
	class private_key extends record
	{
		/*
		 * This is a record used to store private key, ideally this is stored on the user side / client side 
		 * but for now it has to be stored on the server, protected by password
		 */
		
		function get_private_key(#[\SensitiveParameter] String $password): SensitiveParameterValue
		{
			$key=$this->get('key');
			$iv=$this->get('iv');
			
			return $this->decrypt($key, $password, $iv);
		}
		
		function set_private_key(#[\SensitiveParameter] $key, #[\SensitiveParameter] String $password)
		{
			$iv=$this->random_uniqueid(openssl_cipher_iv_length(encryption_chiper));
			$this->set('iv', $iv);
			
			$p_key=$this->encrypt($key, $password, $iv);
			$this-set('key', $p_key);
		}
	}	
?>