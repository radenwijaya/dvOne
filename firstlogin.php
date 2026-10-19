<?php
	/*
	 This file is an independent entity from the whole system, it is used to initiate the system
	 administrator, and you can delete this file after the system setup.
	 
	 How to use and prepare dv1
	 1. setup sql database, and input the credentials on on dv1_auth
	 2. run login.php, and it will ask you for setup.  
	 3. login.php will engage this file and setup the system.	 
	 */

	error_reporting(E_ALL); ini_set("display_errors", 1);

	if (!($_SERVER['HTTPS']))
	{
		header('Location: https://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']);
	}
	if (substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) ob_start("ob_gzhandler"); else ob_start();

	define("username", 'username');
	define("password", 'password');
	
	include_once("library/lib_constants.php");
	include_once("library/lib_html.php");
	
	include_once("library/lib_secret.php");
	include_once("resources/secrets_config.php");
	include_once("resources/secrets.php");
		
	include_once("library/lib_credential.php");
	
	function rebuildTables($link)
	/*
	 * This method will fill the table with required contents
	 */
	{
		$query="CREATE TABLE IF NOT EXISTS ".ctableSettings."
            (spec VARCHAR(20) NOT NULL,
            value VARCHAR(255), PRIMARY KEY (spec))";
		$link->query($query) or die($link->error);
		
		$query="CREATE TABLE IF NOT EXISTS ".ctableUsername."
            (username VARCHAR(10) NOT NULL,
            password VARCHAR(128), checksum VARCHAR(128),
			email VARCHAR(128), LoginExpirity datetime,
			data text,
			input_session VARCHAR(32), loginkey VARCHAR(128),
            PRIMARY KEY (username))";
		$link->query($query) or die($link->error);
		
		//dchecksum = dynamic checksum, always changing.
		//schecksum = static checksum, given at changing password
		//            or acc creation to prevent tampering with
		//            SQL injection at inserting to table lists
		$query="CREATE TABLE IF NOT EXISTS ".ctableAdminList."
            (username VARCHAR(10) NOT NULL,
            dchecksum VARCHAR(128), schecksum VARCHAR(128),
            PRIMARY KEY (username))";
		$link->query($query) or die($link->error);
		
		$query="CREATE TABLE IF NOT EXISTS ".ctableFCList."
            (username VARCHAR(10) NOT NULL,
            dchecksum VARCHAR(128), schecksum VARCHAR(128),
            PRIMARY KEY (username))";
		$link->query($query) or die($link->error);
		
		$query="CREATE TABLE IF NOT EXISTS ".ctableTechList."
            (username VARCHAR(10) NOT NULL,
            dchecksum VARCHAR(128), schecksum VARCHAR(128),
            PRIMARY KEY (username))";
		$link->query($query) or die($link->error);
		
		//reciver is 11 character to handle with 'everyworker' which is 11 character
		//and unique to the character lists
		$query="CREATE TABLE IF NOT EXISTS ".ctableMOTD."
              (messageID BIGINT NOT NULL AUTO_INCREMENT,
               sender VARCHAR(10), reciver VARCHAR(11), Priority INT,
               message TEXT,
               ExpDate date NOT NULL default '2099-12-31',
               primary key (messageID))";
		$link->query($query) or die($link->error);
		
		//custname is 50 w
		$query="CREATE TABLE IF NOT EXISTS ".ctableMember."
              (memberID VARCHAR(5),
               custname VARCHAR(50),
               custmobile VARCHAR(40), custwork VARCHAR(40),
               custhome VARCHAR(40),
               custaddress VARCHAR(40), custsuburb VARCHAR(25),
               custpostcode VARCHAR(10),
		       custemail VARCHAR(50),
               LastContact date NOT NULL default '2019-01-01',
               NextContact date NOT NULL default '2019-01-01',
               lang VARCHAR(2) DEFAULT 'id',
               primary key (memberID))";
		$link->query($query) or die($link->error);
	}
  //if the password and username i filled then
  //register the 1st admin  ;
	$link = new mysqli(db_server->getValue(), db_username->getValue(), db_passwd->getValue(), db_active->getValue());
	
	switch (true)
	{
		case ($link->connect_error) : 
		{
			$message='Unable to connect to the database server at this time';
			break;
		}
		case ((isset($_POST[username])) && (isset($_POST[password]))) :
	  	{    
			rebuildTables();    
		
		    $username=$_POST[username];
		    $password=password_hash($_POST[password], password_algo->getValue());
		    $checksum=password_hash($_POST[username].$_POST[password], password_algo->getValue());
	    	$sChecksum=credentials::getUsernameTableChecksum($username, ctableAdminList);
	
		    $query = "INSERT INTO tableUsername(username, password, checksum) 
		              VALUES('".$link->real_escape_string($username)."', 
							 '".$link->real_escape_string($password)."', 
							 '".$link->real_escape_string($checksum)."')";
		    $link->query($query) or die($link->error);
		
		    $adminquery = "INSERT INTO tableAdminList(username, schecksum)
		                   VALUES('".$link->real_escape_string($username)."', 
						  '".$link->real_escape_string($sChecksum)."')";
		    $link->query($adminquery) or die($link->error);
		    
		    $message='Administrator registration successful. <a href="index.php">Click here to back to login page.</a>.';
	
		    $link->close();
		    break;
	  	}
		default : 
  		{
	  		$message='Administrator registration failed. <a href="index.php">Click here to back to login page.</a>.';  		
  		}
	}
?>
<html>
<head></head>
<body>
    <p>
      <?php echo($message); ?>
    </p>
</body>
</html>  