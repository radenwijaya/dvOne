<?php	
	include_once("lib_form.php");
	
	abstract class lib_page extends s_lib
	{
		use lib_html;
		
		var $param;
		var $pageTitle;
		var $pageDescription;
		var $pageContents;
		var $pageKeywords;
		var $pageCanonical;
		var $H1Title;
		var $lang;
		var $richcard;
		
		var $credentials;
		
		var $visitor_stat;
		
		var $injector;
		
		var ?master_junction $interface;
		
		function getKeywords()
		{
			return $this->pageKeywords;
		}
		function getDescription()
		{
			return $this->pageDescription;
		}
		function getTitle()
		{
			return $this->pageTitle ?? $this->H1Title;
		}
		function getContents()
		{
			return $this->pageContents;
		}
		function getCanonical()
		{
			return $this->pageCanonical;
		}
				
		function getH1Title()
		{
			if ($this->H1Title)
				return $this->H1Title;
			else
				return $this->pageTitle;
		}
		function setInjector($injector)
		{
			$this->injector=$injector;
		}
		function setLanguage($lang)
		{
			$this->lang=$lang;
		}
		
		function makeRichCard($type)
		{
			$this->richcard=new richcard('http://schema.org/', $type, 'http://www.mesinlaser.com');
		}
		function writeRichcard()
		{
			$this->richcard->writeRichCard();
		}
		
		function emailpage($toemail, $toname='', $fromemail='', $fromname='', $bcc='')
		{
			$body=$this->getContents();
			$subject=$this->getH1Title();
			
			$headers  = 'MIME-Version: 1.0' . "\r\n";
			$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
			$headers .= "From: $fromname <$fromemail>" . "\r\n";
			
			$to=$toname." <".$toemail.">";
			mail($to, $subject, $body, $headers);
			
			if ($bcc!='') mail($bcc, $subject, $body, $headers);
		}
		
		var $page_features;
		var $compress_html;
		function add_features(...$features)
		{
			foreach ($features as $feature)
			{
				$this->page_features[$feature->get_name()]=$feature;
				
				if ($feature->check_dependencies())
				{
					$d_features=$feature->get_dependencies(...$features);
					foreach ($d_features as $df)
					{
						$features[]=$df;
						$this->page_features[$df->get_name()]=$df;
					}
				}
			}
		}
		
		function prepareHTML($header)
		{
			if ($this->compress_html ?? true)
				if (substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) ob_start("ob_gzhandler"); else ob_start();
				
			if ($header) header($header);
				
			if ((isset($this->interface)) && (!empty($this->interface->get_page_features())))
				$this->add_features(...$this->interface->get_page_features());
					
			foreach ($this->page_features as $feature)
				$scripts[]=$feature->get_script();
						
			$locale=setlocale(LC_ALL, 0);
			$result=
			'<!DOCTYPE html>
			<html lang="'.$locale.'">
				<head>
					<title>'.$this->getTitle().'</title>
					<meta charset="UTF-8">
					<meta name="viewport" content="width=device-width, initial-scale=1.0">
					'.implode(' ', $scripts).'
				</head>
				<body>';
//	    			<img id="logo" alt="logo" src="resources/logo.jpg">';
						
			if ($title=$this->getH1Title())
				$result.=\html::h1($title);
							
			return $result;
		}
		
		function toHTML($header='')
		{
			$prep=$this->prepareHTML($header);
			if ((isset($this->interface)) && ($this->interface->fibre_enabled('output')))
				echo($prep);
				
			if ((isset($this->interface)) && ($this->interface->fibre_enabled('output')))
			{
				$content=$this->getContents()['prefix'];
				echo($this->getContents()['prefix']);
				
				if (isset($content['family_menu']))
					echo($content['family_menu']);
				
				$f=$this->interface->get_fibre_loop();
					
				foreach ($f->fb_loop() as $c)
					echo($c);
						/*				$x='output';
						 $fibre=new Fiber(function($fibre, $x) {$this->interface->fibre_start($fibre, $x); $this->interface->fibre_output($x);});
						 
						 $c=$fibre->start($fibre, $x);
						 echo($c);
						 while ($this->interface->fibre_running($x))
						 {
						 $c=$fibre->resume();
						 echo($c);
						 $content.=$c;
						 }*/
						
				$content.=$this->getContents()['suffix'];
				echo($this->getContents()['suffix']);
			}
			else
			{
				$content='';
				
				$c=$this->getContents();
				if (isset($c['family_menu']))
					$content=$c['family_menu'];
					
				$content.=$c['value'];
			}
				
			$exec=[];
			foreach ($this->page_features as $feature)
				if ($feature->script_execute)
					$exec[]=$feature->get_execute();
						
			$final='</body>'.(is_array($exec) ? implode(' ', $exec) : '').'</html>';
						
			$result=$prep.$content.$final;
						
			if ((isset($this->interface)) && ($this->interface->fibre_enabled('output')))
				echo($final);
							
			return $result;
		}
		
		function AJAX_Form()
		/*
		 * Displays blank page, with main id='contents' ready to change
		 */
		{
			$locale=setlocale(LC_ALL, 0);
			
			if (($this->interface) && (!empty($this->interface->get_page_features())))
				$this->add_features(...$this->interface->get_page_features());
				//				var_dump(array_keys($this->page_features));
			$scripts=[];
			foreach ($this->page_features as $feature)
				$scripts[]=$feature->get_script();
					
			$exec=[];
			foreach ($this->page_features as $feature)
				if ($feature->script_execute)
					$exec[]=$feature->get_execute();
							
//		    			<img id="logo" alt="logo" src="resources/logo.jpg">
			$result=
				'<!DOCTYPE html>
				 <html lang="'.$locale.'">
					<head>
						<title>'.$this->getTitle().'</title>
						<meta name="viewport" content="width=device-width, initial-scale=1.0">
						'.implode(' ', $scripts).'
					</head>
					<body>
		    			<h1 id="title">'.$this->getH1Title().'</h1>
						<main id="contents">';
							echo($result);
							foreach ($this->output_content() as $oc)
							{
								echo($oc);
								$result.=$oc;
							}
							echo('</main>
					</body>'.(is_array($exec) ? implode(' ', $exec) : '').'
				</html>');
							
			return $result;
		}
			
		function toCSV($filename, $csv)
		{
			header("Content-Type: text/csv; charset=utf-8");
			header("Content-Disposition: attachment; filename=$filename");
			
			$output = fopen('php://output', 'w');
			foreach ($csv as $line)
				fputcsv($output, $line);
				
			fclose($output);
		}
		
		function toJSON($value)
		{
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode($value);
		}
		
		function output_content()
		/*
		 * Output only content of the interface
		 */
		{
			if (($this->interface) && ($this->interface->fibre_enabled('output')))
			{
				$content=$this->getContents()['prefix'];
				yield($this->getContents()['prefix']);
				
				$f=$this->interface->get_fibre_loop();
				
				foreach ($f->fb_loop() as $c)
					yield($c);
					
				yield($this->getContents()['suffix']);
			}
			else
			{
				if (is_array($c=$this->getContents()))
					$content=$c['value'];
				else
					$content=$c;
						
				yield($content);
			}
			
			//			return $content;
		}
		
		function output_page()
		/*
		 * Standard output
		 *
		 * If CSV then creates CSV
		 * If HTML then creates HTML
		 * If WebService creates JSON
		 */
		{
			$result=null;
			$contents=$this->getContents();
			
			if (is_array($contents))
			{
				/*
				 * If CSV or Fibre Enabled
				 */
				switch ($contents[type])
				{
					case 'text/csv':
						$this->toCSV($this->getTitle(), $contents[value]);
						break;
					case 'application/json':
						$this->toJSON($contents[value]);
						break;
					default: case 'html':
						$result=$this->toHTML();						
						break;
				}
			}
			else
				/*
				 * Only when fibre is disabled
				 */
			 	$result=$this->toHTML();
				 	
			if (($this->interface) && (!$this->interface->fibre_enabled()))
				echo($result);
				 		
			return $result;
		}
		
		function output()
		{
			if (isset($this->interface))
			{
				if ((method_exists($this->interface, 'ajax_enabled')) &&
					($this->interface->ajax_enabled()))
				{
					switch ($this->ajax_contents)
					{
						case 'event':
							/*
							 * Do Nothing, and should never be reached;
							 */
							break;
						case 'true':
							/*
							 * Output to the socket in old work model, but in this new work model
							 * it should never be reached
							 */
							break;
							/*						$value='';
							 foreach ($this->output_content() as $oc)
							 $value.=$oc;
							 $result=['target' => $this->interface->form->name, 'value' => $value];
							 
							 $this->interface->socket_write('client', $result);
							 break;*/
						default :
							/*
							 * Output the form and ready receiving content
							 */
							$input=$this->input;
							$input['_event_id']=$this->interface->get_event_id();
							//						var_dump($input['_event_id']);
							$dom=new event_driven_dom($this->credentials, ['page.php', $input], 'contents', 'event_loop', 'event_loop_run');
							$this->add_features($dom);
							
							$this->AJAX_Form();
							break;
					}
				}
				else
				{
					$this->output_page();
				}
			}
/*			else
			{
				echo($this->toHTML());
			}*/
		}
		
		function prepare_junction($junction, $credentials, $input, $secure_db, $readonly_db)
		{
			if ((method_exists($junction, 'event_driven')) && ($junction::event_driven()))
			{
				switch ($this->ajax_contents)
				{
					case 'true':
						/*
						 * Create event handler fibre with the interface waiting for an event to came
						 */
						/*						$result=new $interface($credentials, $input, $secure_db, $readonly_db, '');
						 $fibre=$result->prepare_server_socket('test_123.sock');//$this->credentials->input_session());
						 $result->eh_start($fibre);*/
						$result=null;
						//						var_dump($input); die;
						$credentials->active_interface_name=$junction;
						$junction::eh_start($junction, $credentials, $input, $secure_db, $readonly_db);
						break;
						/*
						 * Fallback if method not exists to normal mode
						 */
					case 'event':
						/*
						 * receiving AJAX event, returns success
						 */
						$junction=new client_interface($credentials, $input, $secure_db, $readonly_db);
						
						$fiber=$junction->prepare_client_socket($input['_event_id'].'.sock');
						//						var_dump($input['_event_id']); die;
						if ($fiber->isTerminated())
						{
							echo(json_encode(['target' => 'alert', 'value' => 'Server Connection Error']));
						}
						else
						{
							$junction->socket_write('client', $input);
							
							echo(json_encode($fiber->resume()));
							
							$junction->socket_close('client');
						}
						$result=null;
						break;
					default:
						/*
						 * Initial loading of a page
						 */
						$credentials->active_interface_name=$junction;
			
						$result=new $junction($credentials, $input, $secure_db, $readonly_db, '');
						break;
				}
			}
			else
				/*
				 * Standard non event driven mode
				 */
			{
				$credentials->active_interface_name=$junction;
				
				$result=new $junction($credentials, $input, $secure_db, $readonly_db);
			}
				 	
		 	return $result;
		}
		
		static function process_input($settings, $secure_db, $active_credentials, $get, $post, $files)
		{
			switch (true)
			{
				case (isset($get['ec'])): case (isset($post['ec'])):
					{
						/*
						 * Combined handler perhaps?  GET and POST together
						 */
						
						$uf=null;//uf is used to handle upload file
						
						if ($get['ec'])
							$login_data=$settings->decrypt_hyperlink_params($get['ec']);
						else
						{
							$ec=$post['ec'];
							unset($post['ec']);
								
							$login_data=$settings->decrypt_hyperlink_params($ec);
							$sf=explode(';', $login_data['sf']);
								
							$uf=$login_data['uf'];
						}
//						var_dump($login_data);
						$credentials=new credentials($secure_db, $login_data[cUsernameChkSec]);
						$credentials->addCredentials($active_credentials, $login_data);
							
						if ($post)
						{
							$post_data=$credentials->descramble_input($post, $sf);
							$login_data=array_merge($post_data, $login_data);
						}
							
						if ($uf)
						{
							$files_data=$credentials->descramble_input($files, $sf);
							$login_data=array_merge($login_data, $files_data);
						}
							
						break;
							//				var_dump($login_data);
					}
				case ((isset($post['mode'])) && ($post['mode']=='webservice') && (class_exists('ws_credentials'))):
					/*
					 * This works on post only, not get
					 * Since this deals with non encrypted data, hence needs some sanitation at this level
					 * 
					 * This section needs ext_webservice to function!
					 */
					{
						$login_data=$post;
						$credentials=ws_credentials::ws_authorise($post, $settings, $secure_db, $active_credentials);
//						echo(json_encode($post));
						
						break;
					}
				default:
					/*
					 * Legacy Mode, not secure.   
					 */
					{
						if ($get[cUsernameChkSec])
						{
							$login_data=$get;
						}
						else
						{
							$login_data=$post;
						}
						
						$credentials=new credentials($secure_db, $login_data[cUsernameChkSec]);
						$credentials->addCredentials($active_credentials, $login_data);
						
						break;
					}
			}
			
			return ['credentials' => $credentials, 'data' => $login_data];
		}
	}	
	
	class secure_page extends lib_page
	{
		var $secure_db;
		var $readonly_db;
		var $credentials;
		var $input;
		var $ajax_contents;
		
		var $payment_url;
		
		function __construct(credentials $credentials, &$input, $secure_db, $readonly_db)
		//credentials
		{
			$this->secure_db=$secure_db;
			$this->readonly_db=$readonly_db;
			$this->credentials=$credentials;
						
			$this->payment_url=array();
			
			if ($input['_ajax_contents'])
				$this->ajax_contents=$input['_ajax_contents'];
			else
				$this->ajax_contents=false;
					
			$this->input=array();
			foreach ($input as $key => $input_element)
			{
				if (!is_array($input_element))
				{
					$this->input[$key]=$this->secure_db->SQLSafeString($input_element);
				}
				else
				{
					$this->input[$key]=array();
					foreach ($input_element as $ie_key => $ie_value)
						$this->input[$key][$ie_key]=$this->secure_db->SQLSafeString($ie_value);
				}
			}
					
			$this->add_features(new css($this->credentials, 'resources/dv1.css', 'all', true));
//			$this->add_features(new css($this->credentials, 'resources/print.css', 'print', false));			
		}
		
		function emailpage($receiver, $toname='', $fromemail='', $fromname='', $bcc='')
		{
			$toemail=$receiver->getData('email');
			$toname=$receiver->getData('contact');
			$fromemail=$this->credentials->email();
			$fromname=ucwords($this->credentials->getName());
			parent::emailpage($toemail, $toname, $fromemail, $fromname);
		}
		
		function processing_time()
		{
			return microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
		}
		
		function redirect($url, $required_credentials=array())
		{
			$credential_string=$this->credentials->credentialString($required_credentials);
			
			$newurl=$url.$credential_string;
			header('Location: '.$newurl);
			exit();
		}
		
		function http_secure_query_data($params, $password=http_query_passwd)
		{
			$params_string=json_encode($params);
			$checksum=hash("sha256", $params_string);
			
			$params['_checksum']=$checksum;
			$params['_username']=qr_selfpay_guest_username->getValue();
			$params['_password']=qr_selfpay_guest_password->getValue();
			
			$iv=substr($checksum, 13, 10);
			$params_string=json_encode($params);
			$secure_param=$this->encrypt($params_string, $password, $iv);
			
			$query_data=['_secure' => 'true', '_checksum' => $checksum, 'data' => $secure_param];
			
			return $query_data;
		}
		
		function http_build_secure_query($params, $password=http_query_passwd)
		{
			$query_data=$this->http_secure_query_data($params, $password);
			
			return http_build_query($query_data);
		}
		
		function http_decode_secure_query($input, &$guest, $password=http_query_passwd)
		{
			$result=array();
			if ($input['_secure']='true')
			{
				$input_checksum=$input['_checksum'];
				$iv=substr($input_checksum, 13, 10);
				
				$input_string=$this->decrypt($input['data'], $password->getValue(), $iv);
				
				$secure_input=json_decode($input_string, true);
				
				$encoded_password=array_pop($secure_input);
				$encoded_username=array_pop($secure_input);
				$encoded_checksum=array_pop($secure_input);
				
				$rehash_checksum=hash("sha256", json_encode($secure_input));
				$valid=(hash_equals($input_checksum, $encoded_checksum)) && (hash_equals($encoded_checksum, $rehash_checksum));
				
				$guest=new credentials($this->secure_db, qr_selfpay_guest_username->getValue());
				
				if (($valid) && ($guest->login(qr_selfpay_guest_username->getValue(), qr_selfpay_guest_password->getValue())))
					$result=$secure_input;
			}
			
			return $result;
		}
		
		function create_payment_url($params, $gateway='paypal')
		{
			$param_string=$this->http_build_secure_query($params);
			$url=payment_gateways::select('payment_creator', $gateway).'?'.$param_string;
			
			return $url;
		}
		
		function create_authorise_url($params, $gateway='paypal')
		{
			$param_string=$this->http_build_secure_query($params);
			$url=payment_gateways::select('payment_authorise', $gateway).'?'.$param_string;
			
			return $url;
		}
		
		function create_display_url($params, $gateway='paypal')
		{
			$param_string=$this->http_build_secure_query($params);
			$url=payment_gateways::select('payment_display', $gateway).'?'.$param_string;
			
			return $url;
		}
		
		function get_junction($input) 
		{
			$junction=''; $ns=null;
			
			if ($input[_junction][0]=='\\')
			/*
			 * If junction is a full name then use it directly
			 */
				$junction=$input[_junction];
			else
			/*
			 * Junction is not a full name
			 */
			{
				if ($ns=$input['_namespace'])
				{
					if (mb_strpos($input[_junction], $ns)===0)
					{
						$junction='\\'.$input[_junction];
					}
					else
					{
						$junction='\\'.$ns.'\\'.$input[_junction];
					}
				}
				else
				{
					$junction=$input[_junction];
				}
			}
				//			var_dump($junction);
			return [$junction, $ns];				
		}
	}	
	
	class paginator extends s_lib
	/*
	 * Paginator is a class that will create paginated list 
	 * 
	 * This class worked by replacing list of whatever you wanted to list with a new list that complies with 
	 * paginator. 
	 * 
	 * It must be input in order to work!
	 */
	{
		var $page_data;
		var $interform_commons;
		var $form;
		var $interface;
		var $params;
		
		use lib_html;
		
		function __construct($credentials, $page_size, $uri, $list, $interface=null, $params=[])
		{
			$this->credentials=$credentials;
			$this->page_data=[];
			
			$this->page_data['size']=$page_size;
			
			$this->page_data['uri']=$uri ?? 'page.php';
			$this->page_data['list']=$list;
			
			$this->interform_commons=[];
			$this->set_junction($interface);
			
			$this->params=$params;
			/*			$this->interface=$interface;
			 $this->interform_commons=$interface->interform_commons;*/
		}
		
		function set_junction($interface)
		{
			$this->interface=$interface;
			$this->interform_commons=$interface->interform_commons;
		}
		
		function page_size_input($input)
		{
			$form=new inputform_control($this->credentials, 'paginator', $this->page_data['uri'], 'POST', $this->page_data['uri']);
			
			/*			$navigationals=[];
			 foreach ($this->essentials as ['name' => $)
			 $navigationals[]=*/
			 //			$form->add_interform_commons($input, ...array_keys($this->interform_commons));
			 
			 $form->rv_input_step=['Show'];
			 $form->add_number_input('Page Size', 'page_size', $this->page_data['size'], 1, 10000, ['required' => true]);
			 $form->add_submit_button('', 'save', 'Show');
			 $form->add_credential($this->credentials->primary());
			 
			 foreach ($this->params as $param => $value)
			 	$form->add_mandatory_input($param, $value);
			 
			 $form->set_junction($this->interface);
			 $form->input($input);
			 //var_dump($input);
			 /*			foreach ($form->fields as $field)
			  var_dump($field['name']);*/
			 
			 $this->form=$form;
		}
		
		function show_input()
		{
			/*			foreach ($this->form->fields as $f)
			 var_dump($f['name']);*/
			$this->form->revert_input_step(0);
			return $this->form->toStringFormat('&nbsp;');
		}
		
		function page_count()
		{
			$page_count=ceil($this->page_data['list']->count() / $this->page_data['size']);
			
			return $page_count;
		}
		
		function page_menu()
		{
			$page_count=$this->page_count();
			
			$pages=[];
			for ($i=0; $i<$page_count; $i++)
				$pages[]=['uri' => $this->page_data['uri'], 'caption' => $i+1, 'params' => ['page' => $i, ...$this->params]];
				
			$additional_input=['page_size' => $this->page_data['size']];
			foreach ($this->interform_commons as ['name' => $name, 'value' => $value])
				if (is_null($additional_input[$name]))
					$additional_input[$name]=$value;
					
			if ($this->interface)
				$additional_input[_junction]='\\'.get_class($this->interface);
				
			return $this->family_menu($additional_input, ...$pages);
		}
		
		function input($input, ...$fields)
		{
			if (is_numeric($input['page_size']))
				$this->page_data['size']=$input['page_size'];
				
			if (is_numeric($input['page']))
			{
				$this->set_page($input['page']);
			}
				
			foreach ($this->interform_commons as ['name' => $common])
				$this->interform_commons[$common]['value']=$input[$common];
					
			$this->page_size_input($input);
		}
		
		function set_page($page)
		{
			$this->page_data['current']=$page ?? 0;
		}
		
		function get_page()
		{
			return $this->page_data['current'];
		}
		
		function make_page()
		{
			$page=$this->page_data['current'];
			
			$page_count=$this->page_count();
			if ($page_count<=$page)
				$page=$page_count ? $page_count-1 : $page_count;
				
			$this->page_data['list']->set_range($this->page_data['size']*$page, $this->page_data['size']);
			$this->page_data['list']->elements_function('add_mandatory_input', 'page', $page);
			$this->page_data['list']->elements_function('add_mandatory_input', 'page_size', $this->page_data['size']);
				
			return $this->page_data['list']->get_range();
		}		
		
		function get_list()
		/*
		 * This will return a paginated list
		 */
		{
			$page=$this->page_data['current'];
			
			$page_count=$this->page_count();
			if ($page_count<=$page)
				$page=$page_count ? $page_count-1 : $page_count;
				
			$this->page_data['list']->set_range($this->page_data['size']*$page, $this->page_data['size']);
			$this->page_data['list']->elements_function('add_mandatory_input', 'page', $page);
			$this->page_data['list']->elements_function('add_mandatory_input', 'page_size', $this->page_data['size']);
				
			return $this->page_data['list']->as_range_list();				
		}
	}	
	
	class master_junction extends entity
	/*
	 * Master junction that super to webservice_junction and junction
	 */
	{
		use miniforms;
		
		function __construct(credentials $credentials, &$input, $secure_db=null, $readonly_db=null)
		{
			$this->secure_db=$secure_db;
			$this->readonly_db=$readonly_db;
			$this->credentials=$credentials;
			$this->cs_name='form';
			$this->forms=[];
			/*
			 * Filter the input!
			 */
			if ($input[_junction])
				unset($input[_junction]);
			if ($input['_namespace'])
				unset($input['_namespace']);
					
			$this->input=$input;
					
			$this->interform_commons=[];
					
			$this->memory_size=null;
					
			$this->enable_family_menu=false;
		}
		
		static function _self_invocable_junction(\credentials $credentials)
		/*
		 * By default get invocable interface will call self menu contents
		 * derive
		 *
		 * This will fix system failure on hyperlink on a page that is calling to itself
		 * and worked on most class
		 */
		{
			$result=[];
			
			$result[credential]=[$credentials->primary()];
			
			$result[elements][]=[junction => get_called_class()];
			
			return $result;
		}
		
		static function get_invocable_interface(credentials $credentials, settings $settings)
		/*
		 * this function is used to register callable class from within the
		 * calling class and associate it the credential manager.
		 *
		 * just one time registering invocable interface is enough for all of the
		 * class, except when requiring different credential
		 * ['f' => 'function', 'p' => ['params]]
		 *
		 * don't forget to call _self_menu_contents when overiding this function or simply
		 * calling parent::get_invocable_interface
		 */
		{
			$result=[];
			
			$result[]=[f => '_self_invocable_junction', p => [$credentials]];
			
			return $result;
		}			
		
		static function credential_check(credentials $credentials, lib_settings $settings, $login_data)
		/*
		 * credential_check will rely on menu credential for display process check,
		 * hence more secure and more practical to code.
		 *
		 * Although menu credential was mitigated by encrypting of parameter string, there
		 * are no guarantee that the parameter was temper proof.
		 *
		 * E.g. a class that accept 2 or more credentials will open by manually adjusting
		 * while running in non encrypted mode by user that is not supposed to open the credential
		 *
		 * credential_check rely data on each class itself when initialised hence cannot be intercepted by network
		 *
		 * It might breach if user who had 2 credentials by tempering the parameter string, but why?
		 * It should be okay, since the user have the rights to open all of them
		
		 * this will obsolete login_check, please remove all of login_check
		 */
		{
			$credential=$credentials->interface_credentials($settings, $login_data)[get_called_class()];
			
			//			var_dump(get_called_class(), $credential, "<br><BR>", $credentials->interface_credentials($settings, $login_data));
			return $credentials->check(...($credential ?? []));
		}
		
		/*
		 * Content Source Section
		 */
		function relegate_content_source($name)
		/*
		 * Used to set a list or form as the default form.
		 * Name must be 'form' or 'list'
		 * This method can be uased for normal form creation and registration
		 */
		{
			if (isset($this->{$name}))
			{
				//				$this->{$name}=$form;
				
				$this->cs_name=$name;
				
				//				if (method_exists($this->{$name}, 'set_junction'))
				//					$this->{$name}->set_junction($this);
			}
		}
		
		function register_content_source($name, lib_form | smart_list $content)
		/*
		 * Used to register a new form
		 */
		{
			if ((!isset($this->{$name})) || ($this->{$name}==$content))
			{
				$this->{$name}=$content;
				$this->relegate_content_source($name);
				
				if ($content instanceof inputform_control)
					$content->set_junction($this);
			}
		}
		
		function content_source() : lib_form | smart_list
		/*
		 * Returning the content source managed by this interface.  Content source can be a list or a form
		 */
		{
			return $this->{$this->cs_name};
		}
		
		function cs_execute()
		{
			return $this->content_source()->{$this->cs_function}(...$this->cs_params);
		}		
	}
	
	class junction extends master_junction
	/*
	 * junction is a class that junctions a page to form
	 * a page can contain a lot of form hence it needs junction
	 */
	{
		use lib_html;
		
		var $form; //used when this interface only have 1 form, which is the first form
		var $forms; //form is used when there are more than 1 form, the first forms is form
		
		var $secure_db;
		var $readonly_db;
		
		var $credentials;
		var $input;
		var $interform_commons;
		
		var $error;
		var $success;
		var $warning;
		var $notice;
		
		var $memory_size;
		
		var $page_features;
		var $cs_name;
		
		var $enable_family_menu;
		
		use form_title_symbols;
		
		function get_page_features()
		/*
		 * Page features of that is added from interface
		 */
		{
			return $this->page_features;
		}
		
		function add_page_features(...$features)
		{
			foreach ($features as $feature)
			{
				$this->page_features[$feature->get_name()]=$feature;
			}
		}
		
		function form_title($form_title=auto_title)
		{
			$result=null;
			
			if ($this->form)
			{
				if (method_exists($this->form, 'form_title'))
				{
					$result=$this->form->form_title($form_title);
				}
				
				if (!$result)
				{
					$result=$this->form->getvalue('id');
				}
				
				$result = $this->form_title_message($result, $this->error, $this->success, $this->warning,
								 $this->notice);				
			}
			else
			{
				$result = $this->form_title_message($form_title, $this->error, $this->success, $this->warning,
								 $this->notice);
			}
			
			return $result;
		}
		
		function family_menu($additional_input, ...$family)
		{
			$additional_input=!is_array($additional_input) ? [$additional_input] : $additional_input;
			
			foreach ($family as ['uri' => $uri, 'class' => $class, junction => $interface, 'caption' => $caption, 'params' => $params])
			{
				if (!$uri)
					$uri = 'page.php';
					
				if (($class) || ($interface))
				{
					if ($interface)
						$class=$interface;
							
					if (!class_exists($class))
						$class=self::decode_namespace_class($class, $this->find_namespace($this));;
								
					$params[_junction]=$class;
								
					if (!$caption)
						$caption=$class::form_caption();
				}
					
				$result_array[]=$this->make_hyperlink($uri, $caption,
					$this->credentials->primary(), array_merge($additional_input, $params ?? []), '_self').'&nbsp;';
			}
			
			return implode('&nbsp;|&nbsp;', $result_array);
		}
						
		function add_interform_commons(...$interforms)
		{
			foreach ($interforms as $common)
				$this->interform_commons[$common]=['name' => $common];
		}
		
		function description()
		{
			return '';
		}
		
		static function form_caption($mods='')
		{
			return 'Caption Undefined';
		}
		
		static function interface_form()
		/*
		 * override when needed in subclass
		 */
		{
			return 'fc_form';
		}
						
		function fibre_enabled($x=null)
		/*
		 * Used to check if the form class support fibre
		 *
		 * if x is specified then will check if it support only fibre, otherwise will check
		 * if it support any fibre
		 *
		 * $x = output is used to check in outputing result, e.g. when it support output fibre
		 * it will work as fibre mode during output.
		 *
		 * Otherwise if it dfoes not support output fibre, then output will not work as
		 * fibre mode, and instead work as conventional mode
		 */
		{
			return ((isset($this->{$this->cs_name})) && (method_exists($this->content_source(), 'fb_fibre_enabled')) &&
			($this->content_source()->fb_fibre_enabled($x)));
		}
		
		function get_fibre_loop()
		{
			if ($this->fibre_enabled())
				return $this->content_source();
		}
		
		final function output()
		/*
		 * If this is fibre output disabled then it will direcly use answer
		 *
		 * otherwise it will make promise only to be engaged later during to html by page class
		 */
		{
			if (method_exists($this, 'toHTML'))
			/*
			 * For compatibility reason, some function had self to HTML converter option
			 * It will take precedence.  ...
			 * toHTML in is not recommended on junction and needs to be deprecated/removed.
			 */
			{
				$result=[type => 'html', 'value' => $this->toHTML()];
			}
			else
			{
				if (!$this->fibre_enabled('output'))
				/*
				 * Gather output directly to result
				 */
				{			
					$cs=$this->content_source() ? $this->content_source() : ($this->form ? $this->form : throw new Exception(implementation_not_ready));
	
					$result=$cs->render();
					
					$result['family_menu']=$this->enable_family_menu ? $this->write_family_menu() : null;						
				}
				else
				/*
				 * Promise to output later during toHTML in page class
				 */
				{
					$result=['type' => 'html'];
					
					if ($this->enable_family_menu)
						$result['family_menu']=$this->write_family_menu();
				}
			}
			
			return $result;
			
			//			return $this->prep_HTML('form', 'toString');
			/*			if (!$this->fibre_enabled())
			 $result=$this->form()->toString();
			 else
			 {
			 $result['type']='html';
			 $result['family_menu']=$family_menu;
			 $this->relegate_form('form', 'toString', []);
			 }
			 
			 return $result;*/
		}
		
		function addForm(lib_form $inputform)
		/*
		 * Form name must be unique
		 */
		{
			if (is_array($this->forms))
				$this->forms[$inputform->name]=$inputform;
			else
				if ($this->forms instanceof smart_list)
					$this->forms->add($inputform);
		}
		
		function input($input, ...$fields)
		/*
		 * This will provide uniform and concise experience during handlding
		 * multiple inputform in an interface by automatically selecting
		 * suitable form with its input.
		 *
		 * This is a also should offers higher performance than
		 * brute force approach that is used
		 *
		 * it will retun default form if there is no form defined
		 */
		{
			if (($input['_form_name']) && ($this->forms[$input['_form_name']]))
			{
				$form=$this->forms[$input['_form_name']];
				
				$form->input($input, ...$fields);
			}
			else
				$form=$this->form;
				
			return $form;
		}
		
		function is_outputing(lib_form $inputform)
		/*
		 * Used to determine if this form is outputing
		 */
		{
			$outputing=true;
			$check_form=null;
			// if everyone is 0 then its outputing
			foreach ($this->forms as $form)
				if ($form->input_step!=0)
				{
					$check_form=$form;
					break;
				}
			
			if ($check_form)
			{
				$outputing=$inputform->name==$check_form->name;
			}
			
			return $outputing;
		}
		
		static function module_name()
		/*
		 * This method is supposed to returrn a module name, which 
		 * inherit on namesapce junction
		 * 
		 * This is used to work permision of get_expansion / get_list method
		 * for extra security
		 */
		{
			throw new Exception("ERROR: module not defined");			
		}
	}
	
	class SYSTEM_JUNCTION extends junction
	/*
	 * Use this system junction for system menus
	 */
	{
		static function module_name()
		{
			return 'SYSTEM';
		}
		
		function form_title($form_title=auto_title)
		{
			$class_name=array_pop(explode('\\', get_class($this)));
			
			return parent::form_title(SYSTEM::S($class_name.'_title'));
		}
		
		static function form_caption($mods=auto_caption)
		{
			$class_name=array_pop(explode('\\', get_called_class()));
			
			return SYSTEM::S($class_name.'_caption');
		}
		
	}
	
	class multipage_list_form extends junction
	/*
	 * This was deprecated and obsoleted by junction, that now supports multiple input forms
	 */
	{
		var $page_data;
		var $paginator;
		var $list;
		
		function __construct($credentials, $input, $secure_db, $readonly_db, $filters, $list_class='smart_list')
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
			
			$this->list=new $list_class($credentials, $secure_db, $readonly_db);
			
			$this->cs_name='list';
		}
		
		/*		function initialise_tabulator($page_size, $uri, $list)
		 {
		 $this->page_data=[];
		 
		 $this->page_data['tabulator']=true;
		 $this->page_data['size']=$page_size;
		 
		 $this->page_data['uri']=$uri;
		 $this->page_data['list']=$list;
		 }
		 
		 function page_menu()
		 {
		 $page_count=1;
		 
		 if ($this->page_data['tabulator'])
		 $page_count=$this->page_data['list']->count() / $this->page_data['size'];
		 
		 $pages=[];
		 for ($i=0; $i<$page_count; $i++)
		 $pages[]=['uri' => $this->page_data['uri'], 'caption' => $i+1, 'params' => ['page' => $i]];
		 
		 return $this->family_menu([], ...$pages);
		 }
		 
		 function set_page($page)
		 {
		 $this->page_data['current']=$page;
		 
		 $this->page_data['list']->set_range($this->page_data['size']*$page, $this->page_data['size']);
		 $this->page_data['list']->elements_function('add_mandatory_input', 'page', $page);
		 }*/
		 
		 function getList()
		 {
		 	return $this->list;
		 }
		 
		 function toHTML()
		 {
		 	if (!$this->fibre_enabled())
		 		$result=$this->content_source() ? $this->content_source()->toHTML() : $this->list->toHTML();
	 		else
	 			$result=['type' => 'html'];
		 			
			return $result;
		 }
		 
		 /*		function toCSV($name='', $nocsv=array('View' => 1))
		  {
		  if ($name=='')
		  $name=$this->form_title();
		  
		  $title=$name.".csv";
		  $this->list->toCSV($nocsv, $title);
		  }*/
		  
/*		  function select($name, $index='')
		  {
		  	return $this->list->select($name);
		  }*/
	}	
?>