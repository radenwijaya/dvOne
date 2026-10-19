<?php	
	define("html_sym_no_access", '&#9940;');
	define("html_sym_error", '&#128721;');
	define("html_sym_warning", '&#9888;&#65039;');
	define("html_sym_notice", '&#8505;&#65039;');
	
	define("html_sym_success", '&#9989;');
	define("html_sym_failure", '&#10060;');
	define("html_sym_not_urgent", '&#10062;');
	define("html_sym_unverified", '&#10067;');
	define("html_sym_adjustment_mass", '&#x2696;&#xFE0F;');
	
	define("html_sym_therefore", '&there4;');	
	define("html_sym_permil", '&permil;');
	define("html_sym_pertenk", '&pertenk;');

	define("html_sym_mu", '&mu;');
	define("html_sym_standard_dev", '&sigma;');
	define("html_sym_xbar", 'x&#772;');
	define("html_sym_xtilde", 'x&#771;');
	define("html_sym_implies", '&rArr;');
	define('html_sym_checkmark', '&#x2713;');
	
	//conditions, use html_cond_0 if there just 1 occurance,
	//for 2 use cond 1 or 2, 
	//for more than 2 use 0, 1, 2
	define("html_cond_0", '&#128261;'); //sunstar
	define("html_cond_1", '&#128312;'); // 
	define("html_cond_2", '&#128313;');	
	define("html_cond_3", '&#9670;');
	define("html_cond_4", '&#9671;');
	define("html_cond_5", '&#9672;'); 
	
	define("html_alpha", '&alpha;');
	
	define("html_bull", '&#9650;');
	define("html_sideways", '&#8669;');
	define("html_bear", '&#9660;');
	
	define("html_unavailable", '&#128939;');
	
	define("html_blank", '&nbsp;');
	
	define('auto_caption', '_autocaption');
	define('auto_title', '_autotitle');
	
	trait messages
	/*
	 * This will get messages from Strings class if present, on the particular namespace
	 * 
	 * On class Strings contains class_name and appeneded by _errors, _warnings, or _strings
	 * because it will be easier to mend between errors and warning if its named by stirng
	 * and not array
	 * 
	 * for the id, you can use integer or string according to your own preferences
	 * 
	 * This only worked on class that is in a namespace
	 * 
	 * Changes is going to be changes needed, the forrmat is as follows 
	 * ['#changed0' => 'whatever you wannted', '#changed1' => 'whatever you wannted 1']
	 */
	{		
		var $_namespace;
		var $_string_resource = null;
		
		function _set_string_resource($string_resource)
		/*
		 * Used to force string resource
		 */
		{
			$this->_string_resource=$string_resource;
		}
		
		function _SUCCESS($id, $changes=[])
		{
			$ol=$this->find_object_location($this);
			
			$message_name=$ol['object']."_strings";
			
			$classname=$this->_string_resource ?? $ol['namespace']."\Strings";
			
			if ($this instanceof inputform_control)
				$this->get_junction()->success=$GLOBALS['Strings']->get(constant($classname.'::'.$message_name)[$id], $changes);
			else
				$this->success=$GLOBALS['Strings']->get(constant($classname.'::'.$message_name)[$id], $changes);
			
			return $this->success;
		}
		
		function _NOTICE($id, $changes=[])
		{
			$ol=$this->find_object_location($this);
			
			$message_name=$ol['object']."_notice";
			
			$classname=$this->_string_resource ?? $ol['namespace']."\Strings";
			
			if ($this instanceof inputform_control)
				$this->get_junction()->notice=$GLOBALS['Strings']->get(constant($classname.'::'.$message_name)[$id], $changes);
			else
				$this->notice=$GLOBALS['Strings']->get(constant($classname.'::'.$message_name)[$id], $changes);
				
			return $this->notice;
		}
		
		function _ERROR($id, $changes=[])
		{
			$ol=$this->find_object_location($this);
			
			$message_name=$ol['object']."_errors";
			
			$classname=$this->_string_resource ?? $ol['namespace']."\Strings";
			
			if ($this instanceof inputform_control)			
				$this->get_junction()->error=$GLOBALS['Strings']->get(constant($classname.'::'.$message_name)[$id], $changes);
			else
				$this->error=$GLOBALS['Strings']->get(constant($classname.'::'.$message_name)[$id], $changes);
			
			return $this->error;
		}
		
		function _WARNING($id, $changes=[])
		{			
			$ol=$this->find_object_location($this);
			
			$message_name=$ol['object']."_warnings";
			
			$classname=$this->_string_resource ?? ($this->_namespace ?: $ol['namespace'])."\Strings";
			
			if ($this instanceof inputform_control)
				$this->get_junction()->warning=$GLOBALS['Strings']->get(constant($classname.'::'.$message_name)[$id], $changes);
			else
				$this->warning=$GLOBALS['Strings']->get(constant($classname.'::'.$message_name)[$id], $changes);
			
			return $this->warning;
		}
		
		function _assign_namespace($namespace)
		/*
		 * This will assign namespace, for accessing strings on a namespace
		 * 
		 * Mostly no need to use but for classes outside namespace that wanted to access strings resource on a namespace,
		 * use this method 
		 */
		{
			$this->_namespace=$namespace;
		}
	}
	
	abstract class Lexicon
	/*
	 * Lexicon is different to messages in how this worked.extends 
	 * 
	 * messages is used to tell user what kind of response they expected
	 * 
	 * Lexicon on the other hand used to translate junction, hence
	 * they serve similar function but for different purposes 
	 * 
	 * messages is only available for the class it used the trait to avoid
	 * confusion namespace.
	 * 
	 * while this thing availabe statically   
	 */
	{
	 	static function _CONTENTS()
		/*
		 * Contains default dictionary, extend on the class
		 */
		{
			return
				['test' => ['id_ID' => 'coba'],
						   ['en_ID' => 'test']
				];
		}
		
		static function _GET($class, $id, $changes=[])
		/*
		 * ID is not str to lowered or upper cased because it
		 */
		{
			return $GLOBALS['Strings']->get($class::_CONTENTS()[$id], $changes);
		}
		
		abstract static function S($id, $changes=[]);
	}
	
	class L extends Lexicon
	/*
	 * An alias to Dictionary to make things short
	 *
	 * For dictionary items in under strings class please Extends from D class
	 */
	{
		static function S($id, $changes=[])
		/*
		 * Example of S function, copy to the descendant class from this
		 */
		{			
			return self::_GET(get_called_class(), $id, $changes);
		}
	}
		
	class String_Resources 
	/*
	 * This is inspired by kotlin resources. 
	 * This can translate the system into different languages relatively easily 
	 * You have to initialise this resource in primary class with a name as String
	 * 
	 * For example initialise in lib_main.php or whenever needed to prepare this with locale as
	 * the PHP super global 
		$GLOBALS["Strings"]->get(ext_weaver\Strings::best_sellers_form_error_0);
		
	 * After that, simply use trait resources in a class that requires it and just
	 * call the name of the string
	 */
	{
		var $locale;
		
		function __construct($locale)
		{
			$this->locale=$locale;
		}
		
		function get($message, $changes=[])
		/*
		 * This funciton will return string resource 
		 *
		 * changes contains an array that consisted of
		 * pattern => what to be replaced
		 * new => what to replace
		 */
		{
			$msg=$message[$this->locale];

			foreach ($changes as $search => $replace)
			{				
				$msg=str_replace($search, $replace, $msg);
			}
			
			return $msg;
		}						
	}
	
	trait resources
	/*
	 * This is the accessor of the String Resources just call this method from a class that needed this
	 * with the name of the resource (namespace independent) and the id of the resource
	 */
	{
		function _string($resource, $id)
		/*
		 * This will return value of string
		 */
		{
			return $GLOBALS['Strings']->get($resource[$id]);
		}		
	}
	
	trait form_title_symbols
	/*
	 * Seperate this from form, and put on the junction (was interface)
	 */
	{		
		static function form_title_message($form_title, $error, $success, $warning, $notice)
		/*
		 * Used to comment on form_title
		 */
		{
			$msg='';
			
			if ($error)
				$msg=' &lt;'.html_sym_error.': '.$error.'&gt;';
			else if ($success)
				$msg=' &lt;'.html_sym_success.': '.$success.'&gt;';
			else if ($warning)
				$msg=' &lt;'.html_sym_warning.': '.$warning.'&gt;';
			else if ($notice)
				$msg=' &lt;'.html_sym_notice.': '.$notice.'&gt;';
							
			return $form_title ? ($msg ? $form_title.'<br>'.$msg : $form_title) : $msg;
		}
	}

	/*
	 * Local form interface for each namespace, use this to get lazyimplements fomr_title and form_caption
	 *
	 * This is not working in trait anyway
	 *  
	class junction extends \junction
	{
		function form_title($form_title=auto_title)
		{
			$class_name=array_pop(explode('\\', get_class($this)));
			
			return parent::form_title(L::S($class_name.'_title'));
		}
		
		static function form_caption($mods=auto_caption)
		{
			$class_name=array_pop(explode('\\', get_called_class()));
			
			return L::S($class_name.'_caption');
		}
	}
	*/
	
	class emoji
	{
		const flag_chequered = '&#x1F3C1;';
		
		const flag_indonesia = '&#x1F1EE; &#x1F1E9;';
		
		const sym_success = '&#9989;';
		const sym_warning = '&#9888;&#65039;';
		const sym_failure = '&#10060;';
		const sym_notice = '&#8505;&#65039;';
	}
?>