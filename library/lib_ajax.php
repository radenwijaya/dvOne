<?php
/*
 * Architecture:
 * 1. an interface must use AJAX trait before it worked as AJAX interface to tell page that it is AJAX
 * 2. function toHTML from interface return ajax
 * 3. Invoker divided into two parts, first creating AJAX blank page and calls invoke_page with exact same parameters 
 * 	  plus asking for actual content 
 * 	  with additional input containing the actual content
 * 4. dom class must be put as page features
 * 5. on init form, action perhaps changed to 'function_name?' I'm still unsure
 */
	trait AJAX
	/*
	 * This trait is used by page to enable ajax functionality on the junction
	 * 
	 */
	{				
		static function ajax_enabled()
		{
			return true;
		}		
	}		
	
	trait event_driven
	/*
	 * This trait is used to add faux event driven funcionality on an interface and is closely tied
	 * with AJAX
	 *  
	 * General execution should pause before calling form->input
	 * Do not add in form->input itself as this is outside  
	 */
	{
		use AJAX;
		use unix_socket;
		
		var $event_fibre;
		
		static function event_driven()
		{
			return true;
		}
		
		function __construct($credentials, $input, $secure_db, $readonly_db, $event_fibre)
		/*
		 * Make this specification of constructor
		 */
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
			
			$this->event_fibre=$event_fibre;
		}
		
		static function eh_start($interface, $credentials, $input, $secure_db, $readonly_db)
		/*
		 * Start a event handler that can only handle 1 event 
		 * Use it only in 3 threads style multi tasking, but do not delete, one day might 
		 * be useable for overly complicated form perhaps
		 */
		{
			/*
			 * This will return the state of interface from event_handler function
			 * that will suspend the fiber
			 */
			$fibre=new Fiber(function($interface, $credentials, $input, $secure_db, $readonly_db, $fibre) {return new $interface($credentials, $input, $secure_db, $readonly_db, $fibre);});
			
			/*
			 * Interface received
			 */
//			var_dump($input); die;
			$if=$fibre->start($interface, $credentials, $input, $secure_db, $readonly_db, $fibre);
			
			/*
			 * If doing correctly then prepare to loop until terminated
			 */
			
			if ($fibre->isSuspended())
			{
				$event_id=$input['_event_id'];
				do
				{
					/*
					 * Preparing socket fiber
					 */						
					$socket_fibre=$if->prepare_server_socket($if->get_event_id().'.sock');
					
					if ($IPC_input=$socket_fibre->resume())
					/*
					 * If receiving a communication attempt
					 */
					{
						$result=[];
						
						if (($IPC_input['_event_id']==$event_id))
						{							
							$result=$fibre->resume($IPC_input);
//							var_dump($result); die;
//							$result=['target' => $if->form->name, 'value' => $output];
						}
						else
						{
							$result=['target' => 'alert', 'value' => 'Server Mismatch'];
						}						
						
						$if->socket_write('client', $result);						
					}
					else
						time_nanosleep(0, 500000000);
						
					$if->socket_close('client', 'server');
				}
				while (!$fibre->isTerminated());
			}
			
			return $if;
		}
							
		function eh_output()
		/*
		 * Bypass fibre output and just output the result
		 */
		{
			$result=$this->content_source() ? $this->content_source()->toString() : $this->form->toString();			
			
			return $result;
		}
						
		function event_handler($event_fibre, $input)
		/*
		 * Pause the fibre and return the to call this call this from constructor, before calling $form->input
		 * 
		 * example: $this->form->input($this->eh_input($input));
		 */
		{
			if ($event_fibre)
			{		
				/*
				 * If the fibre is initialised correctly then return the interface and 
				 * getting actual input
				 */
				$input=$event_fibre->suspend($this);
				
				do
				{
					/*
					 * Actual input to the form
					 */

/*					$result=$this->content_source()->input($input);
//z					var_dump($result, $this->content_source()->select('_event_id')); die;
					$input=$event_fibre->suspend($this->eh_output());*/
					/*
					 * Suspend so the output can written to output socket, and prepare to get another input
					 */
					$input=$event_fibre->suspend($this->content_source()->handle_event($input));
				}
				while (($input!='die') && (!$event_fibre->isTerminated()));
			}
			else 
			{
				$result=$this->content_source()->ed_input($input);				
			}
			
			return $result;
		}
		
		/*		function event_handler()
		{
			$fibre=$this->prepare_server_socket($this->credentials->input_session());
			
			$fibre->start();
			do
			{
				$input=$fibre->resume();
				$this->re_input($input);
				$this->s
			} while (!$fibre->terminated());
		}*/
		
		function get_event_id()
		{
			return $this->content_source()->select('_event_id');
		}
	}
	
	trait event_driven_form
	/*
	 * Add this to the form that is called from 
	 */
	{
		function event_driven()
		{
			return true;
		}
		
		function register_event($field, $event, $function)
		/*
		 * Please make sure function is on the list of supported functions and the function name
		 * on both PHP and JS script side (dom class) is the same to avoid confusion
		 */
		{
			if ((method_exists($this, $function)) || ($field==$this->submit_name))
				$this->events[$field][$event]=$function;
			else 
				/*
				 * Deliberately making fatal error!
				 */
				$this->{$function}();
		}
		
		function prepare_for_events($interface)
		/*
		 * Prepare the form for events with dom on the interface
		 */
		{
			$events=[];
			foreach ($this->events as $field => $events)
			{
				foreach ($events as $event => $function)
				{
					$ec=$this->prepare_event_ec($field, $event, $function, $interface->input['_event_id']);

					$this->add_specification($field, $event, $function."('".$this->name."', '".$ec."')");
					$events[$event]=$function;
				}				
			}
			
			foreach ($events as $event => $function)
			{
				$dom=new \event_driven_dom($this->credentials, ['page.php'], $event, $function);
				$interface->add_page_features($dom);				
			}
		}
		
		function prepare_event_ec($element, $event, $function, $event_id='')
		/*
		 * This method is part of event_driven, and used to prepare encrypted parameters of
		 * everything that is needed for the form to work
		 *
		 * Input: params is an array with 3 parameters that will dedicate where this ec belongs to
		 * params
		 *
		 * Only works when hidden parameters are encrypted
		 */
		{
			$ec=null;
			
			if ($this->get_global('settings')->select('encrypt_hidden_parameters'))
			{
				$params=['element' => $element, 'event' => $event, 'function' => $function];
				$this->add_mandatory_input('_input_step', $this->input_step);
							
				$this->add_mandatory_input('_event_id', $event_id ?: bin2hex(random_bytes(7)));
//					var_dump($this->select('_event_id'), $this->input);
				foreach ($this->mandatory_fields as $mandatory)
					$this->html_hidden($mandatory);
					
				$this->html_credential();
					
				foreach ($this->hidden_fields as $field)
				{
					switch ($this->fields[$field]['type'])
					{
						case 'dropdown':
							$params[$field]=$this->fields[$field]['selected'];
							break;
						default:
							$params[$field]=$this->fields[$field]['value'];
					}
				}
					
					//			if ($this->get_global('settings')->select('scramble_parameters'))
				foreach ($this->fields as $field)
					if (($field['type']!='hidden') && ($field['type']!='reserved') && 
						($field['type']!='credential'))
						$this->scramble_field_name($field['name']);
							
				$params['sf']=$this->credentials->get_scrambled_input();
				
				if ($this->upload_form)
					$params['uf']='upload_form';
					
				$ec=$this->encrypt_html_params($this->action, $params);
			}
			
			return $ec;
		}		
				
		function handle_event($input)
		/*
		 * This method is used to handle an event that is made by user input
		 * and musts only be called by event_handler in interface class
		 *
		 * This method will call handler function that is specified when writing
		 * the form class
		 *
		 * except when input event is onclick with submit name where it will call
		 * on_submit
		 *
		 */
		{
			if ($input['element']==$this->submit_name)
			{
				switch ($input['event'])
				{
					case 'onclick':						
						$result=$this->on_submit($input);						
						break;
					default:
						$f=$input['function'];
						$result=$this->{$f}($input);
						break;
				}
			}
			else
			{
				$f=$input['function'];				
				$result=$this->{$f}($input);
			}
			
			return $result;
		}
		
		function on_submit($input)
		/*
		 * Working example of on_submit method that will work on most reporting system
		 * 
		 * Always return an array of 'target' and 'value' of whatever you wanted to 
		 * target and change
		 *
		 * Override on child class to make this work for your requirements
		 */
		{
			$this->ed_input($input);
					
			return ['target' => $this->name, 'value' => $this->toString()];
		}
		
		function ed_input($input)
		/*
		 * Override when needed
		 */
		{
			if ($this->input($input)==$this->final_input_step())
				if (method_exists($this, 'insert'))
					$this->insert();					
		}
	}
	
	class event_driven_dom extends features
	/*
	 * Containing DOM and scripts such as XMLHTTPRequest is a made in this place
	 * 
	 * On this class, the initialisation variable script_function became the name of the function of javascriptimplements 
	 * And uniqueid became the DOM ID that is affected  
	 * 
	 * Data is passed to the used form
	 * 
	 */
	{
		use \lib_html;
		
		function event_loop($target_script, $input)
		/*
		 * An event loop that opens a PHP link that connect to a UNIX sockets
		 */
		{
			unset($input['pk']);
			$input['_ajax_contents']='true';
			
			$params=$this->encrypt_hyperlink_params('page.php', $input);
			$result='<script type="text/javascript">';
			$result.='function '.$this->script_function.'()';
			$result.="{";
			$result.="xhr = new XMLHttpRequest(); ";
			$result.='xhr.onreadystatechange = function (){';
/*						if (this.readyState == 4 && this.status == 200) 
						//get only the final warning
						{	
							if (this.responseText.length!=0)
							{
//								alert(this.responseText);
								obj=JSON.parse(this.responseText);								
								if (obj.target)
									document.getElementById(obj.target).innerHTML = obj.value;
							}
						}*/

			$result.='if (this.readyState == 4 && (this.status == 200 || this.status == 408))
						{
//							alert(this.responseText);
							'.$this->script_function.'();
						}
					};';
			
			$result.='xhr.open("POST", "'.$target_script.'", true);';
			$result.='xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");';
			$result.='xhr.send("'.$params.'");';
//			$result.='alert(xhr.status);';
			$result.='return xhr; }';
			$result.='</script>';
			
			return $result;
		}
		
		function event_loop_run()
		/*
		 * Run this at the end of the script
		 */
		{
			$result='<script type="text/javascript">';
			$result.=$this->script_function.'();';						
			$result.='</script>';
			
			return $result;			
		}
		
		function content_loader($target_script, $target_dom, $input)
		/*
		 * Depcrecated
		 * main content loader calling invoke_page again
		 */
		{			
			unset($input['pk']);
			$input['_ajax_contents']='true';
			
			$params=$this->encrypt_hyperlink_params('page.php', $input);
			$result='<script type="text/javascript">';
			$result.='function '.$this->uniqueid().'()';
			$result.='{ xhr = new XMLHttpRequest();';
			$result.='xhr.onreadystatechange = function (){
					if (this.readyState == 4 && this.status == 200) {
						document.getElementById("'.$target_dom.'").innerHTML =
						this.responseText;}};';
			$result.='xhr.open("POST", "'.$target_script.'", true);';
			$result.='xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");';
			$result.='xhr.send("'.$params.'");';
			$result.='return xhr; }';
			$result.='</script>';
			
			return $result;
		}
		
		function content_execute()
		/*
		 * Depcrecated
		 * Executing content
		 */
		{
			$result='<script type="text/javascript">';
			$result.=$this->script_function.'();';
			
			$result.='</script>';
			
			return $result;
		}

		function submit_form($target_script, $form)
		/*
		 * main content loader calling invoke_page again
		 */
		{					
			$result='<script type="text/javascript">';
			$result.='function '.$this->script_function.'(target)';
			$result.='{ xhr = new XMLHttpRequest();';
			$result.='xhr.onreadystatechange = function (){
					if (this.readyState == 4 && this.status == 200) {
						document.getElementById(target).innerHTML =
						this.responseText;}};';
			$result.='xhr.open("POST", "'.$target_script.'", true);';
			$result.='formData = new FormData(document.forms.'.$form.');';			
			$result.='xhr.send(formData);';
			$result.='return xhr; }';
			$result.='</script>';
			
			return $result;
		}
		
		function on_click($target_script)
		/*
		 * Script that is executed when designated button 
		 */
		{
			$result='<script type="text/javascript">';
			$result.='function '.$this->script_function.'(form, ec)';
			$result.='{';
//			$result.='alert(document.forms[form].shipment.value);';
			$result.="document.forms[form].ec.value=ec;";
//			$result.='alert(document.forms[form].shipment.value);';
			$result.='xhr = new XMLHttpRequest();';
			$result.='xhr.onreadystatechange = function (){
					  	if (this.readyState == 4 && this.status == 200)
						{
//							alert(this.responseText);
							if (this.responseText.length!=0)
							{
								obj=JSON.parse(this.responseText);
								
								if (obj.target=="alert")
									alert(obj.value)
								else
									document.getElementById(obj.target).innerHTML = obj.value;
							}
						} 
						};';			
			$result.='xhr.open("POST", "'.$target_script.'", true);';
			$result.='formData = new FormData(document.forms[form]);';
			$result.='xhr.send(formData);';
			$result.='return xhr; ';
			$result.='}';
			$result.='</script>';
			
			return $result;			
		}
				
		function register_script($script)			
		{
			$this->script=$script;
		}

		function script_function()
		{
			return $this->script;
		}
	}
	
	class client_interface extends junction 
	{
		use unix_socket;
	}
?>