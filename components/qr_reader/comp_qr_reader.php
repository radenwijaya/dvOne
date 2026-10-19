<?php
	/*
	 * PHP Wrapper for Java Script QR Reader 
	 * Source: https://blog.minhazav.dev/QR-and-barcode-scanner-using-html-and-javascript/
	 * 
	 * Location: components/qr_reader/html5-qrcode.min.js
	 */

	trait comp_qr_reader
	{
		/*
		 * This trait is used to extend lib_form or its descandents with QR code reading 
		 * input functionality
		 */
		function enable_qr_form()
		/*
		 * Adding all the javascript features to the interface
		 */
		{			
			$features=[new feature_qr_reader_import($this->credentials, 'qr_import')];
			
			foreach ($this->fields as $field)
				if ((isset($field['qr_input'])) && ($field['qr_input']==true))
					$features[]=new feature_qr_reader($this->credentials, $field['name']);
			
			return $features;
		}
		
		function qr_body($field)
		/*
		 * Making actual QR body
		 */
		{
			$result='';
			
			$multiple='false';
			if ($this->fields[$field]['multiple'])
				$multiple='true';

			if ($this->fields[$field]['caption'])
				$result.='<strong>'.$this->fields[$field]['caption'].'</strong>';
			$result.="<div id='".$field."_qr_render' style='width: 300px'></div>";
			$result.="<script type='text/javascript'>";
			$result.="	".$field."_qr_scanner=init_qr_".$field."('".$field."_qr_render', $multiple);";
			$result.='</script>';
			
			return $result;
		}
		
		function mark_qr_input($field)
		/*
		 * Marking as QR input must be done before enable_qr_form
		 */
		{
			$this->add_specification($field, 'qr_input', true);
		}
		
		function qr_input($field)
		/*
		 * Replaces normal html_dropdown input or text input
		 * 
		 * to use it will need to have a placeholder for it, and make the QR hidden
		 */
		{
			$result='';
			
			if ((isset($this->fields[$field]['qr_input'])) && ($this->fields[$field]['qr_input']))
			{
				$result.=$this->qr_html_input_placeholder($field);
				$result.=$this->qr_body($field);
			}
			
			return $result;
		}
		
		function qr_html_input_placeholder($field)
		{
			$result=$this->html_hidden_codes($field);
			
			return $result;
		}		
	}
	
	class feature_qr_reader_import extends features
	/*
	 * To ensure that only import this qr header once 
	 */
	{	
		var $name;
		
		function __construct($credentials, $name)
		{
			$this->name=$name;
			parent::__construct($credentials, [], 'qr_reader_import', 'import_qr_reader');
		}
		
		function import_qr_reader()
		{
			return '<script src="./components/qr_reader/html5-qrcode.min.js"></script>';
		}				
	}
	
	class feature_qr_reader extends features
	/*
	 * Use one feature for each QR
	 */		
	{
		var $field_name;
		
		function __construct($credentials, $field_name)
		/*
		 * Field name must be consistent with field name used by 
		 */
		{
			$this->field_name=$field_name;
			parent::__construct($credentials, [], $field_name, ['multi_scan_sucess', 'scan_sucess', 'init_qr']);
		}
		
		function multi_scan_sucess()
		/*
		 * Scan for multiple qr code and check if its more than one, only register it as one
		 */
		{
			$result="<script type='text/javascript'>";
			$result.='function multi_scan_success_'.$this->field_name.'(decodedText, decodedResult)';
			$result.='{';
			$result.='	if (buffer_'.$this->field_name.'.length==0)';
			$result.='		buffer_'.$this->field_name.'=decodedText;';
			$result.='	else';
			$result.='	{';
			$result.='		if (buffer_'.$this->field_name.'.indexOf(decodedText)==-1)';
			$result.='			buffer_'.$this->field_name.'+=";"+decodedText;';
			$result.='	}';
			$result.='	alert(buffer_'.$this->field_name.');';
			$result.="	document.getElementById('".$this->field_name."').value=buffer_".$this->field_name.';';
			$result.='}';
			$result.='</script>';
			
			return $result;			
		}
		
		function scan_sucess()
		/*
		 * This will rewrite the QR code for each scan... Should just disable after scan?
		 * if combined with fibre and multitasking it can be enabled to check the state of the QR 
		 * whether its valid or not
		 */
		{
			$result="<script type='text/javascript'>";
			$result.='function scan_success_'.$this->field_name.'(decodedText, decodedResult)';
			$result.='{';
			$result.='	buffer_'.$this->field_name.'=decodedText;';
			$result.="	document.getElementById('".$this->field_name."').value=buffer_".$this->field_name.';';
			$result.='	alert("Sukses!");';
			//			$result.='	document.getElementById('.$this->field_name.'_qr_scanner).style.display="none";';
			$result.='}';
			$result.='</script>';
			
			return $result;
		}		
				 	
		function init_qr()
		/*
		 * Write on top
		 */
		{
			$result="<script type='text/javascript'>";
			$result.='let buffer_'.$this->field_name."='';";
			$result.='function init_qr_'.$this->field_name.'(qr_reader, multiple)';
			$result.='{';
			$result.='	var html5QrcodeScanner = new Html5QrcodeScanner(qr_reader, { fps: 10, qrbox: 250 });';
			$result.='	if (multiple)';
			$result.='		html5QrcodeScanner.render(multi_scan_success_'.$this->field_name.');';
			$result.='	else';
			$result.='		html5QrcodeScanner.render(scan_success_'.$this->field_name.');';
			$result.='	return html5QrcodeScanner;';
			$result.='}';
			$result.='</script>';
			
			return $result;
		}
	}
?>