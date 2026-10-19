<?php
	/*
	 * The purpose of this file is to share database server secrets to the dv1 system
	 * hence dv1 can be developed by many people without compromising the server secrets
	 * 
	 * For key exchange
	 * Server Key is private key
	 * Client Key is public key 
	 * 
	 * After key exchange, client will give server a key (AES Key) for exchanging 
	 * secert informations 
	 * 
	 * Because public key can be extracted from private key,  
	 */
	class lib_secret
	{
		var $auth_uri;
		var $client_key;
		var $server_key;
		
		var $access_code;
		var $mode;
		
		var $client_cipher;
		var $client_iv;
		
		function __construct(#[\SensitiveParameter] $auth_uri, #[\SensitiveParameter] $client_key, #[\SensitiveParameter] $server_key, #[\SensitiveParameter] $access_code, $mode)
		/*
		 * Initialise with client or server key.
		 * Keep both the client and server key private on seperate computers
		 * 
		 * mode is the mode, it can be client or server.
		 */
		{
			$this->auth_uri=$auth_uri;
			$this->mode=$mode;
			
			if ($client_key) 
				$this->client_key=openssl_pkey_get_public($client_key);
			else 
				$this->client_key=null;
			
			if ($server_key)
				$this->server_key=openssl_pkey_get_private($server_key);
			else
				$this->server_key=null;
			
			if ($mode=='client')
			/*
			 * Client will d efine server key
			 */
			{
				$cipher_length=openssl_cipher_key_length(auth_cipher);
				$cstrong=true;
				$this->server_key=openssl_random_pseudo_bytes($cipher_length, $cstrong);				
			}
			
			$this->access_code=$access_code;
		}
		
		static function random_bytes()
		{
			return bin2hex(random_bytes(64));
		}
		
		static function make_keys()
		{
			$config = ["digest_alg" => "sha512", "private_key_bits" => 2048, "private_key_type" => OPENSSL_KEYTYPE_RSA];
			
			$private_key=openssl_pkey_new($config);
			
			$private_key_pem='';			
			$public_key_pem = openssl_pkey_get_details($private_key)['key'];
			
			openssl_pkey_export($private_key, $private_key_pem);
			
			$keys=[];
			$keys=['public' => $public_key_pem, 'private' => $private_key_pem];
									
			return $keys;
		}
		
		function encrypt(#[\SensitiveParameter] String $message)
		/*
		 * Encrypt the message using the server key
		 * 
		 * returns chunks of texts due to limitation of openssl_public_encrypt that only can process short texts
		 */
		{
			$result=[];
						
			switch ($this->mode)
			{
				case 'client':
					{
						$chunks=str_split($message, 128);
						
						foreach ($chunks as $m)
						{
							$r='';
							openssl_public_encrypt($m, $r, $this->client_key);
							$result[]=bin2hex(gzdeflate($r));
						}						
						break;
					}
				case 'server':
					{
						$r=openssl_encrypt($message, $this->client_cipher, 
							$this->client_key, 0, $this->client_iv);
						$result[]=bin2hex(gzdeflate($r));
												
						break;
					}
			}
			
			return $result;
		}

		function decrypt($chunks)
		/*
		 * Encrypt the message using the any key that is available
		 * 
		 * input array of messages, return 1 string
		 */
		{
			$result='';
			
			switch ($this->mode)
			{
				case 'client':
					/*
					 * Decrypting as client is in synchronous mode
					 *
					 */
					{
						$e=implode("", $chunks);
						$result=openssl_decrypt(gzinflate(hex2bin($e)), auth_cipher, $this->server_key, 0, $this->client_iv);

						break;
					}
				case 'server':
					/*
					 * Decryption as server is always receiving in async mode
					 */
					$temp=[];
					foreach ($chunks as $c)
					{
						$r='';
						openssl_private_decrypt(gzinflate(hex2bin($c)), $r, $this->server_key);
						$temp[]=$r;					
					}
					
					$result=implode("", $temp);
					break;
			}

			return $result;
		}
		
		function decode_transmission($data)
		/*
		 * Run this when receiving data transmission 
		 * Input: Array of encrypted test
		 * Output: Clear Text
		 */
		{
			$msg=$this->decrypt($data);
			
			$case=json_decode($msg, true);
			
			$hash=array_pop($case);
			$hash_result=hash_hmac('sha256', json_encode($case), $this->access_code);
			
			$result=null;
			if (hash_equals($hash, $hash_result))
			{
				$result=$case['value'];
				
				if ($this->mode=='server')
				{
					$this->client_iv=hex2bin($case['iv']);
					$this->client_cipher=$case['cipher'];
					$this->client_key=hex2bin($case['key']);
				}				
			}
							
			return $result;
		}
		
		function encode_transmission(#[\SensitiveParameter] String $data)
		/*
		 * Encoding data for transmission.  
		 * 
		 * Output array of strings containing chunks of data
		 * 
		 * Data must always be string
		 * Run this to prepare data for transmission
		 */
		{			
			$case=['id' => bin2hex(random_bytes(8)), 'value' => $data];
			
			if ($this->mode=='client')
			{
				$ivlen=openssl_cipher_iv_length(auth_cipher);
				$cstrong=true;
				$iv=openssl_random_pseudo_bytes($ivlen, $cstrong);
				
				$case['key']=bin2hex($this->server_key);
				$case['cipher']=auth_cipher;
				$case['iv']=bin2hex($iv);
			
				$this->client_iv=$iv;
			}
			
			$hash_result=hash_hmac('sha256', json_encode($case), $this->access_code);
			$case['hash']=$hash_result;
			
			/*
			 * Add chunk size here perhaps?
			 */
						
			$e_string=json_encode($case);
			
			$result=$this->encrypt($e_string);
						
			return $result;			
		}
		
		function transmit($data)
		/*
		 * Calling CURL to the auth URIarray(1) { [0]=> string(512) "
		 * Then will 
		 */
		{
			$ch=curl_init();
			
			$encoded=$this->encode_transmission($data);
			
			curl_setopt($ch, CURLOPT_URL, $this->auth_uri);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HEADER, false);		
			
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_POST, true);
//			curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($encoded));
			
			$d=curl_exec($ch);
			
			if ($d!==false)			
			{							
				$result=json_decode($d, true);			
			}			
			else
			{
				$result=['status' => 'invalid response'];
			}
			
			if (curl_error($ch))
			{
				$result=['status' => 'error'];
			}				
//			var_dump($d);
//			curl_close($ch);
			
			return $result;
		}		
	}	
?>