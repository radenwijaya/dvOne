<?php
	/*
	 * Access code must correspond with the access codde on access server.
	 */
//	error_reporting(E_ALL);	ini_set("display_errors", 1);
	
	$s2=new lib_secret(auth_uri->getValue(), client_key->getValue(), "", authentication_code->getValue(), 'client');
		
	$exec=$s2->transmit(json_encode(["c" => "auth"]));
	
	switch ($exec['status'])
	{
		case 'success':
			{
				$data=json_decode($s2->decode_transmission($exec["value"]), true);
			
				foreach ($data as $name => $value)
					define($name, new \SensitiveParameterValue($value));
				
				break;
			}
		case 'forbidden':
			{
				http_response_code(403);				
				die("Forbidden");
				break;
			}
		case 'bad':
			{
				http_response_code(400);
				die("Bad Request");
				break;
			}
		case 'invalid':
			{
				http_response_code(401);
				die("Unauthorized");
				break;
			}
	}
?>