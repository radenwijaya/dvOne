<?php
	define('authentication_code', 
		new \SensitiveParameterValue('secret_auth_code'));

	define('client_key', new \SensitiveParameterValue(implode("\n", [
		"-----BEGIN PUBLIC KEY-----",
		"-----END PUBLIC KEY-----"])));
	
	define('auth_uri', new \SensitiveParameterValue('localhost/dv1_auth/auth.php'));
	
	define('auth_cipher', 'AES-128-CBC');
?>
