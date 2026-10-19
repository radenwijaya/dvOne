<?php	
/*	include("../phpqrcode/qrlib.php");
	include_once("lib_baseclass.php");
	include_once("lib_credential.php");*/
	
/*
 * How to create QR code, simply use
 * 		$qr_code=ext_QRCode::headerless_text_png($value);
 *		$qr.="<img src='data:image/png;base64,$qr_code' alt='$display'>";
 *					
 * on HTML use qr_on_html
 */

	//how to use
	//call qr_container from any function, add the data you wanted (can also accept $page contents data).
	//and then call html_qrgen to get the image visible
	//in our case the qr_container gen will be used to sign on the input_form, stepTwo.
	
	//how to generate
	//qrgen.php will load this class and then execute print command
	
	//how to read
	//call qrcheck
	
	include_once("phpqrcode/qrlib.php");

	define('tableQRCheck', 'tableQRCheck');

	class lib_qrgen extends extensions
	{
		use \lib_html;
		
		function __construct($settings, $credentials, $login_data)
		{
			parent::__construct($settings, $credentials, [], $login_data);
			
			$this->dm->create_plainname_datatable(tableQRCheck);
		}
		
		function signature_form($sign_contents, $arrangement, $display, $dataholder, $active_mode, $employee_list)
		/*
		 * Making or loading signature and signing and sealing them 
		 */
		{
			$row=[];
			$result=$this->open_html_table(['class' => 'stl_free_table']);
			foreach ($arrangement as $mode)
			{
				$row[0][]=$display[$mode];
				
				if ($s_username=$dataholder->select($mode, 'auth'))
				{
					if ($active_mode==$mode)
					{
						$signer=$employee_list->db_select($s_username);
						
						$row[1][]=$signer->sign($sign_contents);
						
						$signer->seal($dataholder);
					}
					else
					{
						$signer=$employee_list->db_select($s_username);
						$seal=$signer->load_seal($dataholder->select($mode, 'seal'));
						
						$row[1][]=$signer->load_signature($seal);
					}
				}
				else
				{
					$row[1][]='&nbsp;';
				}
				
			}
			$result.=$this->add_html_table_row(...$row[0]);
			$result.=$this->add_html_table_row(...$row[1]);
			$result.=$this->close_html_table();
			
			return $result;
		}		
	}
	
	class ext_QRimage extends QRimage
	{
		public static function headerless_png($frame, $filename = false, $pixelPerPoint = 4, $outerFrame = 4)
		{
			$image = self::image($frame, $pixelPerPoint, $outerFrame);
			
			ImagePng($image);
			
			ImageDestroy($image);				
		}
		//copied from original, sorry :D
		private static function image($frame, $pixelPerPoint = 4, $outerFrame = 4)
		{
			$h = count($frame);
			$w = strlen($frame[0]);
		
			$imgW = $w + 2*$outerFrame;
			$imgH = $h + 2*$outerFrame;
		
			$base_image =ImageCreate($imgW, $imgH);
		
			$col[0] = ImageColorAllocate($base_image,255,255,255);
			$col[1] = ImageColorAllocate($base_image,0,0,0);
		
			imagefill($base_image, 0, 0, $col[0]);
		
			for($y=0; $y<$h; $y++) {
				for($x=0; $x<$w; $x++) {
					if ($frame[$y][$x] == '1') {
						ImageSetPixel($base_image,$x+$outerFrame,$y+$outerFrame,$col[1]);
					}
				}
			}
		
			$target_image =ImageCreate($imgW * $pixelPerPoint, $imgH * $pixelPerPoint);
			ImageCopyResized($target_image, $base_image, 0, 0, 0, 0, $imgW * $pixelPerPoint, $imgH * $pixelPerPoint, $imgW, $imgH);
			ImageDestroy($base_image);
		
			return $target_image;
		}
	}
	
	class ext_QRencode extends QRencode
	{
		//copied form original but with changing class
		public static function factory($level = QR_ECLEVEL_L, $size = 3, $margin = 4)
		{
			$enc = new ext_QRencode();
			$enc->size = $size;
			$enc->margin = $margin;
		
			switch ($level.'') {
				case '0':
				case '1':
				case '2':
				case '3':
					$enc->level = $level;
					break;
				case 'l':
				case 'L':
					$enc->level = QR_ECLEVEL_L;
					break;
				case 'm':
				case 'M':
					$enc->level = QR_ECLEVEL_M;
					break;
				case 'q':
				case 'Q':
					$enc->level = QR_ECLEVEL_Q;
					break;
				case 'h':
				case 'H':
					$enc->level = QR_ECLEVEL_H;
					break;
			}
		
			return $enc;
		}
		
		public function headerless_encodePNG($intext, $outfile = false,$saveandprint=false)
		{
			try {
		
				ob_start();
				$tab = $this->encode($intext);
				$err = ob_get_contents();
				ob_end_clean();
		
				if ($err != '')
					QRtools::log($outfile, $err);
		
				$maxSize = (int)(QR_PNG_MAXIMUM_SIZE / (count($tab)+2*$this->margin));
		
				ob_start();
				ext_QRimage::headerless_png($tab, $outfile, min(max(1, $this->size), $maxSize), $this->margin,$saveandprint);
				$result = ob_get_contents();
				ob_end_clean();
				
			} catch (Exception $e) {
		
				QRtools::log($outfile, $e->getMessage());
		
			}
			
			return $result;
		}		
	}
	
	class ext_QRCode extends QRcode
	{
		public static function headerless_png($text, $outfile = false, $level = QR_ECLEVEL_L, $size = 3, $margin = 4, $saveandprint=false) 
		{
			$enc = ext_QRencode::factory($level, $size, $margin);
			return $enc->headerless_encodePNG($text, $outfile, $saveandprint=false);				
		}
		
		public static function headerless_text_png($text, $outfile = false, $level = QR_ECLEVEL_L, $size = 3, $margin = 4, $saveandprint=false)
		{
			$enc = ext_QRencode::factory($level, $size, $margin);
			return base64_encode($enc->headerless_encodePNG($text, $outfile, $saveandprint=false));
		}		
		
		public static function qr_on_html($value, $display='', $level = QR_ECLEVEL_L)
		{
			$qr_code=ext_QRCode::headerless_text_png($value, false, $level);
			
			$qr="<img src='data:image/png;base64,$qr_code' alt='$display'>";
			
			return $qr;
		}
	}

	class qr_container extends record
	{
		var $trackback_id;//used to display as same qr code
		var $exists;
		
		var $secure_db;
		var $readonly_db;
		
		var $message;
		
		function set_uniqueid(...$hash)
		{			
			$this->uniqueid=hash('sha256', $hash[0]);
		}
		
		function html_qrgen($signer='', $sign_date='', $mode='auth')
		{
			$uniqueid=$this->uniqueid;			
//			$credentials=$this->credentials->credentialString($this->credentials->primary());
			$qr_code=$this->qr_as_base64_img($mode);				
			
			if ($signer)
			{
				if ($signer instanceof employee)
					$signature=$signer;
				else if (is_string($signer))
					$signature=new employee($this->credentials, $this->secure_db, $this->readonly_db, '', $signer);
				else
					$signature=$this->credentials->employee;
			}
			else
			{
				$signature=new employee($this->credentials, $this->secure_db, $this->readonly_db, '', $this->select('signer'));				
			}
			
			if (!$sign_date)
			{
				if ($this->select('sign_date'))
					$sign_date=$this->date($this->select('sign_date'), 'd-m-Y');
				else
					$sign_date=date('d-m-Y');
			}
			
			$result=
				"<div class='signature'>";			
			
			$result.="<img src='data:image/png;base64,$qr_code' alt='$uniqueid'>";
			
			$result.='<br>'.$signature->getName().' on '.$sign_date."<br>";
				
			if ($this->message)
				$result.="<p>$this->message</p>";
			$result.="</div>";
			
			return $result;
		}
	
		function load($uniqueid='', $data=array())
		{
			$uniqueid=$this->secure_db->SQLSafeString($this->uniqueid);
			
			$query="SELECT * FROM tableQRCheck WHERE uniqueid='$uniqueid'";
			$this->secure_db->query($query);
			
			if ($this->secure_db->num_rows()>0)
			{
				$row=$this->secure_db->next_result();

				$this->row_decoder($row['data']);												
				$this->exists=true;
			}
			else
			{
				$this->exists=false;
				$this->valid=false;
			}
		}
		
		function checksum()
		{
			$algo=$this->get_global('settings')->select('encrypt_parameters_auth');
			
			$content=$this->select('page1');
			$checksum=$this->hashiteration($content, 0, $algo);
			
			return $checksum;
		}
		
		function conclude()
		{
			return (($this->valid) && ($this->exists));
		}
		
		function flush($debug=false)
		{
			if ($this->credentials->valid_input_session())
			{					
				$uniqueid=$this->secure_db->SQLSafeString($this->uniqueid);
				$data=$this->secure_db->SQLSafeString($this->row_encoder($this->data, $this->credentials)['encrypted']);
				$query="INSERT INTO tableQRCheck (uniqueid, data) VALUES ('$uniqueid', '$data')";
				
				$this->secure_db->query($query);			
				$this->exists=true;				
			}
		}

		function qrprint()
		{
			$qr_contents='https://www.pandatex.id';
			if ($this->credentials->isValid())				
				$qr_contents=''.$this->uniqueid;
			//echo($qr_contents);
			QRCode::png($qr_contents, false, QR_ECLEVEL_H);
		}
		
		function qr_as_base64_img($mode='auth')
		/*
		 * mode can be default, auth, search, etc depending on the QR checking
		 * url that is needed, however the default is auth
		 */
		{
			$qr_settings=$this->get_global('settings')->select('qr');
			$qr_contents=$qr_settings['default'];
			if ($this->credentials->isValid())
				$qr_contents=$qr_settings[$mode].$this->uniqueid;
			//ob_start();
			$qr_code=ext_QRCode::headerless_png($qr_contents, false, QR_ECLEVEL_H);
			//$qr_code=ob_get_contents();
			//ob_end_clean();						
			return base64_encode($qr_code);
//			return "iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAABGdBTUEAALGPC/xhBQAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9YGARc5KB0XV+IAAAAddEVYdENvbW1lbnQAQ3JlYXRlZCB3aXRoIFRoZSBHSU1Q72QlbgAAAF1JREFUGNO9zL0NglAAxPEfdLTs4BZM4DIO4C7OwQg2JoQ9LE1exdlYvBBeZ7jqch9//q1uH4TLzw4d6+ErXMMcXuHWxId3KOETnnXXV6MJpcq2MLaI97CER3N0vr4MkhoXe0rZigAAAABJRU5ErkJggg==";
		}
	
		function __construct($credentials, $secure_db, $readonly_db, $table='tableQRCheck', $input=array(), $data=array())
		//omit data field on checking the QR code
		{
			parent::__construct($credentials, $credentials->primary(), $secure_db, $readonly_db, $data, 'tableQRCheck');
			$this->secure_db=$secure_db;
			$this->readonly_db=$readonly_db;
			$this->credentials=$credentials;
			$this->exists=false;
			$this->valid=true;
			
			$this->message="";
			
			if ($credentials)
			{
				if ($credentials->isValid())
				{
					if ($input['i'])
					{
						$this->uniqueid=$input['i'];
						if (!$input['p'])
							$this->load();
					}
				}
				else
				{
					$this->valid=false;
				}
			}
			else
			{
				if ($input['i'])
				{
					$this->uniqueid=$input['i'];
					$this->load();
				}
				else
				{
					$this->valid=false;
				}
			}
		}	
		
		function set_message($message)
		{
			$this->message=$message;
		}
	}		
	
	class qr_secure_url_pointer extends entity
	{
		var $top_message;
		var $bottom_message;
		
		function html_qrgen($url, $params)
		{	
			$secure_page=new secure_page($this->credentials, array(), $this->secure_db, $this->readonly_db);
			$params_string=$secure_page->http_build_secure_query($params);
			$qr_contents=$url.'?'.$params_string;
			$qr_code=$this->qr_as_base64_img($qr_contents);
								
			if (!$sign_date) $sign_date=date('d-m-Y');
						
			$result="<div class='signature'>";
			if ($this->top_message)	$result.="<p>$this->top_message</p>";

			$result.="<img src='data:image/png;base64,$qr_code' alt='$uniqueid'>";						

			if ($this->bottom_message)	$result.="<br>$this->bottom_message";			
			$result.="</div>";
							
			return $result;							
		}
		
		function qrprint($qr_contents)
		{
			if ($this->credentials->isValid())
				$qr_contents='https://www.ptgoldensun.com/check.php?i='.$this->uniqueid;
				//echo($qr_contents);
			QRCode::png($qr_contents, false, QR_ECLEVEL_H);
		}
		
		function qr_as_base64_img($qr_contents)
		{			
			ob_start();
			ext_QRCode::headerless_png($qr_contents, false, QR_ECLEVEL_H);
			$qr_code=ob_get_contents();
			ob_end_clean();
			return base64_encode($qr_code);
				//			return "iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAABGdBTUEAALGPC/xhBQAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9YGARc5KB0XV+IAAAAddEVYdENvbW1lbnQAQ3JlYXRlZCB3aXRoIFRoZSBHSU1Q72QlbgAAAF1JREFUGNO9zL0NglAAxPEfdLTs4BZM4DIO4C7OwQg2JoQ9LE1exdlYvBBeZ7jqch9//q1uH4TLzw4d6+ErXMMcXuHWxId3KOETnnXXV6MJpcq2MLaI97CER3N0vr4MkhoXe0rZigAAAABJRU5ErkJggg==";
		}	
		
		function __construct($credentials, $secure_db, $readonly_db, $input=array(), $data=array())
		//omit data field on checking the QR code
		{
			parent::__construct();
			
			$this->secure_db=$secure_db;
			$this->readonly_db=$readonly_db;
			$this->credentials=$credentials;
			$this->exists=false;
			$this->valid=true;
		}
		
		function set_message($top_message, $bottom_message)
		{
			$this->top_message=$top_message;
			$this->bottom_message=$bottom_message;
		}
	}
?>