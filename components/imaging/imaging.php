<?php
	define('injector_cache_threshold', 1);

	class secure_image extends secure_page
	/*
	 * Don't forget to add
	 * use image_viewer in that junction
	 * add add image_viewer to get_invocable_interface
	 *
	 * On the junction add this or similar to this
		use \image_viewer;
	
		static function get_invocable_interface(\credentials $credentials, \settings $settings)
		{
			$result=[];
	
			$result[]=[f => 'image_viewer', p => [$credentials]];
	
			return $result;
		}
	 */
	{
		function __construct($credentials, $input, $secure_db, $readonly_db, $settings)
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
			
			$junction='image_junction';
			
			if ($junction::credential_check($credentials, $settings, $input))
			{
				$this->interface=$this->prepare_junction($junction, $credentials, $input, $secure_db, $readonly_db);				
			}
		}
		
		function output()
		{
			if (isset($this->interface))
			{
				$this->interface->toImage();
			}
			else
			{
				throw new Exception('Please enable image_junciton on calling junction!');
			}
		}
		
		static function image_link(junction $junction, ...$images)
		/*
		 * This will produce image hyperlink for each member of the family.
		 * Call from junction using $this as the jucntion arguments
		 */
		{
			$result_array=[];
			foreach ($images as ['uniqueid' => $uniqueid, 'width' => $width, 'height' => $height])
			{
				$uri='image.php';
				
				$params=[];
				$params[_junction]='image_junction';
				$params['uniqueid']=$uniqueid;
				$params['width']=$width ?? 600;
				$params['height']=$height ?? 600;	
				
				$result_array[$uniqueid]=$junction->make_uri($uri, $params);
			}
			
			return $result_array;
		}
		
		static function verify(String $file_name, String $hash)
		/*
		 * This will compare the image hash to the image to verify its the correct image
		 * 
		 * Call this during verification
		 */
		{
			$file_hash=hash_file('sha3-256', $file_name);
			
			return hash_equals($file_hash, $hash);
		}
	}
	
	trait image_viewer
	/*
	 * This will make an image viewer using image.php to show image as an extra layer of a hidden
	 * image display
	 */
	{
		static function image_viewer(\credentials $credentials)
		{
			$result=[];
			
			$result['credential']=$credentials->primary();
			
			$result[elements][]=[junction => '\image_junction'];
			
			return $result;
		}		
	}
	
	class image_checkform extends \fc_form
	/*
	 * This is used as checkform.  Checking the junction parameters
	 */
	{
		function __construct($credentials, $name)
		{
			parent::__construct($credentials, $name, null, 'post', null);
			
			$this->io_set_submit('stepZero', 'stepZero', 'show', 'true');
			$this->io_set_final('stepZero');			
			
			$this->add_credential($credentials->primary());
			
			/*
			 * Uniqueid is the filename!
			 */
			$this->add_text_input('', 'uniqueid', '', '', 256, [required => true]);
			
			$this->add_number_input('', 'width', 600, 1, 10000, [required => true]);
			$this->add_number_input('', 'height', 600, 1, 10000, [required => true]);						
		}
	}
	
	class image_junction extends junction
	{
		var image $image;
		
		function initialise(image $image, $width = null, $height = null)
		/*
		 * This will initialise the image.  Call this after load, or simply initialise this with other image resources
		 */
		{
			$this->set('width', $width);
			$this->set('height', $height);
			
			$this->image=$image;
		}
		
		function toImage()
		{
			$this->image->outputHeader();
			$this->image->outputImage();
		}
		
		function __construct($credentials, $input, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
			
			$form=new image_checkform($credentials, 'checkform');
			$form->input($input);
			
			if ($form->is_final_input_step())
			{				
				/*
				 * Correct input accepted
				 */
				$image=new image($credentials, $secure_db, $readonly_db);
				$image->initialise($form->select('uniqueid'));
				
				$this->initialise($image, $form->select('width'), $form->select('height'));
			}
		}
	}
	
	class image_compress extends s_lib
	/*
	 * This will load an image, then compress and store image to database
	 * 
	 */
	{
		function __construct($filename, advanced_list $list, $width=0, $height=0)
		{
			
		}
	}
		
	class image extends record
	/*
	 * This is the actual image stored, it is not jpeg_handler
	 * It is used to do whatever with the image.
	 */
	{
		/*
		 * image is an gdimage type, for now not swaved on database
		 */
		var GdImage $image;
		
		function __construct($credentials, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $credentials->primary(), $secure_db, $readonly_db, []);
		}
		
		function load($uniqueid='', $data=[])
		{
			parent::load($uniqueid, $data);
			
		
		}
		
		function initialise($filename)
		/*
		 * Initialising from file
		 */
		{
			$im=null;
			$image_type = 0;
				
			if (file_exists($filename))
			{
				list($width, $height, $type, $attr) = getimagesize($filename);
				
				switch (exif_imagetype($filename)) //get the image type
				{
					case IMAGETYPE_GIF:
						$im = imagecreatefromgif($filename);
						$image_type = IMG_GIF;
						break;
					case IMAGETYPE_JPEG:
						$im = imagecreatefromjpeg($filename);
						$image_type = IMG_JPG;
						break;
					case IMAGETYPE_PNG:
						$im = imagecreatefrompng($filename);
						$image_type = IMG_PNG;						
						break;
					case IMAGETYPE_WBMP:
						$im = imagecreatefromwbmp($filename);
						$image_type = IMG_WBMP;
						break;					
				}
				
				$this->image=$im;
				$this->set('filename', $filename);
				$this->set('image_type', $image_type);				
				$this->set('width', $width);
				$this->set('height', $height);
				$this->set('type', $type);
				$this->set('attr', $attr);
			}
		}
				
		function outputImage()
		/*
		 * Directly output to screen, no echo
		 */
		{
			switch ($this->get('image_type'))
			{
				case IMG_GIF:
					imagegif($this->image);
					break;
				case IMG_JPEG:
					imageinterlace($this->image, true);
					imagejpeg($this->image);
					break;
				case IMG_PNG:
					imagepng($this->image, NULL, 9, PNG_ALL_FILTERS);
					break;
				case IMG_WBMP://bmp outputs PNG for smaller size
					imagepng($this->image);
					break;					
			}
		}
		
		function outputHeader()
		{
			$filename=$this->real_filename;
			//			$size=sizeof($this->sim);
			
			$size=-1;

			switch ($this->get('image_type'))
			{
				case IMG_GIF:
					header('Content-type: image/gif');
					break;
				case IMG_JPEG:
					header('Content-type: image/jpeg');
					break;
				case IMG_PNG:
					header('Content-type: image/png');
					break;
				case IMG_WBMP://bmp outputs PNG for smaller size
					header('Content-type: image/png');
					break;
			}
			
			if ($size!=-1)
			{
				header('Content-Length: '.$size);
			}
			if ($filename!='')
			{
				header('Content-Disposition: attachment; filename="'.$filename.'"');
			}
			
			$expired_date=date('D, d M Y H:i:s T', mktime(0, 0, 0, date("n")+1, date("j"), date('Y')));
			header("Cache-Control: max-age=86400, public");
			header("Expires: $expired_date");
		}		
	}
	
	class jpeg_handler extends file_handler
	/*
	 * Handler for JPEG file
	 */
	{
		var $file_name;
		
		const mime = ['image/jpeg', 'jpg', 'jpeg'];
		
		static function upload_args($show_upload=null, $width=null, $height=null)
		{
			$result=[];
			
			if ($show_upload===true)
				$result['show_upload']=true;
		
			if ($width)
				$result['width']=$width;
			
			if ($height)
				$result['height']=$height;
			
			return $result;
		}
		
		static function showcase_placeholder($id=null, $show_upload=null, $width=null, $height=null)
		{
			$result='';
			if ($show_upload)
				$result=\html::img(id : $id, width : $width, height : $height, attr : ['hidden' => null]);
				
			return $result;			
		}
		
		static function loader_function($element=null, $preview=null)
		{
			return ['oninput' =>
						"element=document.getElementById('".$element."');  preview=document.getElementById('".$preview."'); ".
						"if (preview.src!='') window.URL.revokeObjectURL(preview.src);".
						"preview.src=window.URL.createObjectURL(element.files[0]);".
						"preview.hidden=false;"
			];
		}
		
		static function supports($mime)
		{
			return in_array($mime, self::mime);
		}
		
		function __construct($filename, $permission)
		{
			$this->file_name=$filename;
			
			$this->file_size=getimagesize($filename);
		}
		
		function read()
		/*
		 * This will read the file.  Caution: slow!
		 */
		{
			$this->contents=imagecreatefromjpeg($this->file_name);		
		}
		
		function contents($format='filename')
		/*
		 * if the format is tag, it will return the filename
		 * otherwise will return the contents
		 */
		{
			if ($format=='filename') 
				return $this->file_name;
			else 
				return $this->contents;
		}
		
		function __destruct()
		/*
		 * Do Nothing
		 */
		{
		}
		
		function proportionise($t_width, $t_height)
		/*
		 * Enters current width and height, and then return array 
		 * of width & height to the proportion 
		 * 
		 * Make sure its inside frame!
		 */
		{
			$width=$this->file_size[0];
			$height=$this->file_size[1];
			
			if ($width/$t_width > $height/$t_height)
			{
				$r_width=$t_width;
				$r_height=round($height * $t_width/$width);
			}
			else
			{
				$r_height=$t_height;
				$r_width=round($width * $t_height/$height);
			}
			
			return ['w' => $r_width, 'h' => $r_height];
		}
		
		function as_element($args)
		/*
		 * Return HTML elements pointing to here
		 */
		{
			$size=$this->proportionise(800, 600);
			
			$img=\secure_image::image_link($args[junction], ['uniqueid' => $this->file_name, 
				$args['width'] => $this->file_size[0],
				$args['height'] => $this->file_size[1], 'show' => 'true'])[$this->file_name];
			
			$result=\html::img($img, '', $size['w'], $size['h']).\html::br();
			
			return $result;
		}
	}
	
	class image_compressor
	{
		var $ori_image;
		var $link;
		var $image_type;
		var $checksum;
		var $id;
		var $sim;		
		var $width;
		var $height;
		var $cacheExists;
		var $filename;
		var $real_filename;
		
		var $database;
		
		function makeID()
		{			
			$strong=true;
			return bin2hex(openssl_random_pseudo_bytes(16, $strong));
		}

		function getCompress($width, $height)
		{
			$newwidth=$this->width;
			$newheight=$this->height;
			$filename=$this->filename;
	//load
			$image_type=0;
			$sim = imagecreatetruecolor($newwidth, $newheight);		
			switch (exif_imagetype($filename)) //get the image type
			{
			     case IMAGETYPE_GIF:
					 $im = imagecreatefromgif($filename);
			         $image_type = IMG_GIF;
			         break;
			     case IMAGETYPE_JPEG:
					 $im = imagecreatefromjpeg($filename);
			         $image_type = IMG_JPG;
			         break;
			     case IMAGETYPE_PNG:
	//				 $required_memory = Round($width * $height * 3);
	//				 $new_limit=memory_get_usage() + $required_memory;
	//				 ini_set("memory_limit", $new_limit);
					 $im = imagecreatefrompng($filename);
			         $image_type = IMG_PNG;
	
	 			     imagecolortransparent($sim, imagecolorallocatealpha($sim, 0, 0, 0, 127));
				     imagealphablending($sim, false);
				     imagesavealpha($sim, true);
	//		         ini_restore ("memory_limit");
			         break;
			     case IMAGETYPE_WBMP:
					 $im = imagecreatefromwbmp($filename);
			         $image_type = IMG_WBMP;
			         break;
			      default:
			         $image_type = 0;
			         break;
			 }

			$this->ori_image=$im;
			imagecopyresampled($sim, $im, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

			$this->image_type=$image_type;
			$this->sim=$sim;
			//store image on database if not existed
	
	// Free up memory
			return $sim;
		}
	
		function connect()
		{
	//cache opening
			/*$mysql_user = "imgcache";
			$mysql_pass = 'HUIU7789534jkgrykmIUGRJHKFEYUIyuit478%3uig7497846jfjkertie35yuig#%!isfuie@jklgf!%jklgeriYIUogjkFlgerui%jhgr#hegksgbnmsrtHTETH%Ji';
			$dbname = "imgcache";
			$server = "imgcache.db.6597658.hostedresource.com";
			$link = new mysqli("$server", "$mysql_user", "$mysql_pass", "$dbname");
			if ($link->connect_error)
			{
				$link=NULL;
			}*/
			$this->link=$this->database->link;
			return $this->link;
		}
		
		function outputHeader()
		{
			$filename=$this->real_filename;
//			$size=sizeof($this->sim);

			$size=-1;
			
			$image_type=$this->image_type;
			
			switch ($image_type)
			{
			     case IMG_GIF:
					header('Content-type: image/gif');
			     	break;
			     case IMG_JPEG:
					header('Content-type: image/jpeg');
			     	break;
			     case IMG_PNG:
					header('Content-type: image/png');
			     	break;
			     case IMG_WBMP://bmp outputs PNG for smaller size
					header('Content-type: image/png');
			     	break;
			}
			
			if ($size!=-1)
			{
				header('Content-Length: '.$size);
			}
			if ($filename!='')
			{
				header('Content-Disposition: attachment; filename="'.$filename.'"');			
			}
			
			$expired_date=date('D, d M Y H:i:s T', mktime(0, 0, 0, date("n")+1, date("j"), date('Y')));
			header("Cache-Control: max-age=2592000, public");
			header("Expires: $expired_date");			
		}
		
		function outputImage($noprogressive = false)
		{
			$sim=$this->sim;
			$image_type=$this->image_type;
			
			if ($this->cacheExists)
			{
				if (sha1(bin2hex($this->sim))==$this->checksum)
					echo($this->sim);
				
				/*echo(sha1(bin2hex($this->sim)));
				echo($this->checksum);*/				
			}
			else
			{
//			echo(' '.$image_type.' image ');
	// Output the image
				switch ($image_type)
				{
			    	 case IMG_GIF:
						imagegif($sim);
			     		break;
			     	case IMG_JPEG:
			     		imageinterlace($sim, !$noprogressive);
						imagejpeg($sim);
				     	break;
				     case IMG_PNG:
						imagepng($sim, NULL, 9, PNG_ALL_FILTERS);
			     		break;
			     	case IMG_WBMP://bmp outputs PNG for smaller size
						imagepng($sim);
			     		break;
				}
			}
		}
		
		function checksum($filename)
		{
			/*$filestring=file_get_contents($filename);
			$crc=crc32($filestring);*/
			$crc=sha1_file($filename);
			return $crc;
		}

		function check_cacheDB()
		{
			$link=$this->link;
			$id=$this->id;
			$filename=$this->filename;
			
			$checksum=$this->checksum;			
			$sql_stmt=$link->prepare('SELECT image, image_type, checksum FROM img_repository WHERE id=?');
			$sql_stmt->bind_param('s', $id);
		
			$sql_stmt->execute();
			$sql_stmt->store_result();
			
			$sql_stmt->bind_result($image, $image_type, $checksum);
			$sql_stmt->fetch();
			
			$this->image_type=$image_type;
			$this->checksum=$checksum;
						
			/*$hash=$this->checksum($filename);
			$sim=NULL;			
			if ($hash!=$checksum)
			{
				$this->checksum=$hash;//set the new checksum to the hash for further usage in saving the cache
			}
			else
			{
				$sim=$image;
				$this->sim=$sim;
			}*/
					
			$sim=$image;
			$this->sim=$sim;
			
			$sql_stmt->close;
			
			return $sim;
		}
	
		function save_cacheDB()
		{
			//$checksum=$this->checksum;
			$image_type=$this->image_type;
			$link=$this->link;
			$id=$this->id;
			$sim=$this->sim;
			
			$sql_stmt=$link->prepare("INSERT INTO img_repository (id, image, image_type, checksum, accesstime) VALUES(?,?,?,?, now()) ON DUPLICATE KEY UPDATE checksum=?");
			
	//		echo($sim);
			 
			ob_start(); //open buffer
			$this->outputImage($_GET['progressive']=='no'); //output the image to the buffer
			$final_image=ob_get_contents(); //save the final image
			ob_end_clean(); //clean the buffer
			
			$this->checksum=sha1(bin2hex($final_image));
			$checksum=$this->checksum;			
			
	//		echo($final_image);
	
			$buffer=NULL;
			$sql_stmt->bind_param("sbiss", $id, $buffer, $image_type, $checksum, $checksum);		
			$sql_stmt->send_long_data(1, $final_image);
									
			$sql_result=$sql_stmt->execute();
			
	//		echo("saving cache ".$sql_stmt->error);
			
	//		echo('hello world'.$sql_stmt->error);
			
			$sql_stmt->close;
		}
		
		function __destruct()
		{						
			if (isset($this->ori_image))
			{
				imagedestroy($this->sim);
				imagedestroy($this->ori_image);
			}
		}
				
		function __construct($filename, $database, $width, $height)
		{
			$this->database=$database;
			$this->link=$this->connect();
			$this->filename=$filename;
			$start_time=microtime(true); //for benchmarking
			if (file_exists($filename))			
			{
				//storage mode
				//$this->checksum=$this->checksum($filename);
				
				$this->real_filename=pathinfo($filename)['basename'];				
		
				$image_type=0;
				list($oriwidth, $oriheight) = getimagesize($filename);	
		//defining width
				if ($width)
				{
					$newwidth=$width;
				}
				else
				{
					if ($height) //if height is exist then calculate width using height proportionally
					{
						$newwidth=round($height/$oriheight*$width);
					}
					else
					{//else no need to set width
						$newwidth=$oriwidth;
					}
				}
		
				if ($height)
				{
					$newheight=$height;
				}
				else
				{
			//if no height then calculate new height proportionally to the width
					$newheight=round($newwidth/$oriwidth*$oriheight);
				}
				
				$this->width=$newwidth;
				$this->height=$newheight;
					
				$cacheExists=false;
				
				$id=$this->makeID();
				$this->id=$id;
			
				$cacheExists=true;
				$sim==NULL;
				//$sim=$this->check_cacheDB();
							
			//		echo("check cache ".(microtime(true)-$start_time)."<br>");
					
				if ($sim==NULL) //ok database is empty
				{								
					$cacheExists=false;
					$this->getCompress($oriwidth, $oriheight);
							
					$this->save_cacheDB();
				}
			}
			else 
			{
				//loading mode
				$this->id=$filename;
				$this->width=$width;
				$this->height=$height;
				
				$sim=$this->check_cacheDB();
				//adjust to the new size
				$cacheExists=($sim!=NULL);
			}
			
			$this->cacheExists=$cacheExists;
		}
		
		function realOutputImage()
		{
			$this->outputHeader();
			$this->outputImage();
		}
	}
	
	class injector
	{
		var $image_id;
		var $path;

		function __construct($image_id, $path='inject.php')
		{
			$this->image_id=$image_id;		
			$this->path=$path;
		}
				
		function inject()
		{							
			$result=$this->path."?img=".$this->image_id;
			return $result;
//			https://ptgoldensun.com/newgwm/testbed/injectpicture.php?img=http://www.ptgoldensun.com/newgwm/testbed/pictures/IMG1.JPG&width=256&height=192&strip=true
		}
	}
	
//	echo("all done ".(microtime(true)-$start_time)."<br>");
//image test string:
//http://www.ptgoldensun.com/newgwm/testbed/injector2/injectpicture.php?img=http://www.ptgoldensun.com/gallery/images/training_and_install/IMG_00000671.jpg&width=794&height=595&strip=true
?>
