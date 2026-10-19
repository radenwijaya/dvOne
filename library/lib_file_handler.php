<?php
	class lib_file_handler extends s_lib
	{
		var $handlers;
		
		static function registered_file_handlers()
		{
			$result=[];
			
			$result[]='csv_handler';
			$result[]='ssv_handler';
			$result[]='json_handler';
			$result[]='jpeg_handler';
			
			return $result;
		}
			
		function __construct($file_name, $mime, $permission)
		/*
		 * Permission at this time only 'r'
		 */
		{
			$this->handlers=[];
			
			$handlers=$this->registered_file_handlers();
			
			foreach ($handlers as $handler)
				if ($handler::supports($mime))
					$this->handlers[$handler]=new $handler($file_name, $permission);							
		}
		
		function exists()
		{
			return $this->handlers[array_key_first($this->handlers)]->exists();
		}
		
		function read($handler, $mime)
		/*
		 * Read the file according the the supplied mime that is on the handler
		 * the reading is actually done by the handler, and non exhaustive
		 */
		{		
/*			$result=null;
			
			foreach ($this->handlers as $handler)
				if (($handler->exists()) && ($handler->supports($mime)))
				{
					$result=$handler->read();
					break;
				}
			
			return $result;*/
			
			if (($this->handlers[$handler]->exists()) && ($this->handlers[$handler]->supports($mime)))
				$result=$this->handlers[$handler]->read();
			
			return $result;
		}
		
		function get($handler)
		/*
		 * Returns the contents of a file handler, hence file handler is not directly used by
		 * the other class
		 */
		{
			return $this->handlers[$handler]->contents();
		}
		
		function update_filename($file_name)
		{
			foreach ($this->handlers as $handler)
				$handler->file_name=$file_name;
		}
		
		function handler($handler)
		{
			if (isset($this->handlers[$handler]))
				$result=$this->handlers[$handler];
			else
				throw new Exception('Handler Unavailable');
			
			return $result;
		}
	}

	abstract class file_handler extends s_lib
	{
		var $file;
		var $contents;
		var $file_size;
		var $file_name;
		
		/*
		 * Fill the mime constant on each class descending from this
		 */
		const mime = [];
		
		static function upload_args()
		{
			
		}
		
		static function upload_placeholder()
		/*
		 * Override with show upload version
		 */
		{
			return '';
		}
		
		static function loader_function()
		{
			
		}
		
		static function supports($mime)
		/*
		 * Checking whether this class supoort the mime, since its abstract
		 * then it always result in no
		 */
		{
			return in_array($mime, static::mime);
		}
				
		function __construct($filename, $permission)
		/*
		 * Opening file handler here
		 */
		{			
			$this->file_name=$filename;
			
			$this->file=fopen($filename, 'r');
			$this->file_size=filesize($filename);
			
			$this->contents=[];
		}
		
		function exists()
		{
			return $this->file!==false;
		}
		
		function read()
		/*
		 * Just read everything om the file
		 */
		{			
			$this->contents['raw']=fread($this->file, $this->file_size);
		}
		
		function __destruct()
		{
			if ($this->exists())
				fclose($this->file);
		}
		
		function contents($format='raw')
		/*
		 * Only supports contents in raw format
		 */
		{
			return $this->contents[$format];
		}
	}
	
	class csv_handler extends file_handler
	{
		const mime = ['text/csv', 'application/csv', 'csv'];
				
		function __construct($filename, $permission)
		{
			parent::__construct($filename, $permission);			
		}		
		
		function read()
		/*
		 * Reading the file and saving it to contents
		 */
		{
			parent::read();
			
			$rows=explode(PHP_EOL, $this->contents['raw']);
			
			$data=[];
			$header=preg_split('/,/', $rows[0]);
			
			foreach ($header as $key => $element)
				$header[$key]=trim($element, " \n\r\t\v\x00'\"");

			$j=0;
			$row_count=count($rows);		
			
			for ($i=1; $i<$row_count; $i++)
			{
				if ($rows[$i]!='')
				{
					$row=preg_split('/,/', $rows[$i]);
					foreach ($header as $key => $row_name)
						$data[$j][$row_name]=trim($row[$key], '"\'');
				
					$j++;
				}
			}
			
			$this->contents['csv']['header']=$header;
			$this->contents['csv']['data']=$data;
			
			return $data;
		}
		
		function contents($format='csv')
		{
			return parent::contents($format);
		}
	}
	
	class ssv_handler extends file_handler
	/*
	 * Semicolon seperated value
	 */
	{
		const mime = ['text/csv', 'application/csv', 'csv', 'text/csv; delimiter=semicolon', 'semicolon-separated-values', 'ssv'];
				
		function __construct($filename, $permission)
		{
			parent::__construct($filename, $permission);
		}
		
		function read()
		/*
		 * Reading the file and saving it to contents
		 */
		{
			parent::read();
			
			$rows=explode(PHP_EOL, $this->contents['raw']);
			
			$data=[];
			$header=preg_split('/;/', $rows[0]);
			
			foreach ($header as $key => $element)
				$header[$key]=trim($element, " \n\r\t\v\x00'\"");
				
			$j=0;
			$row_count=count($rows);
				
			for ($i=1; $i<$row_count; $i++)
			{
				if ($rows[$i]!='')
				{
					$row=preg_split('/;/', $rows[$i]);
					foreach ($header as $key => $row_name)
						$data[$j][$row_name]=trim($row[$key], '"\'');
							
					$j++;
				}
			}
				
			$this->contents['ssv']['header']=$header;
			$this->contents['ssv']['data']=$data;
			return $data;
		}
		
		function contents($format='ssv')
		{
			return parent::contents($format);
		}
	}

	class json_handler extends file_handler
	/*
	 * JSON File as Header and Values
	 */
	{		
		const mime = ['application/json', 'json'];
				
		function __construct($filename, $permission)
		{
			parent::__construct($filename, $permission);
		}
		
		function read()
		/*
		 * Reading the file and saving it to contents
		 * UNTESTED
		 */
		{
			parent::read();
			
			$data=json_decode($this->contents['raw'], true);
							
			$first=array_key_first($data);
			$this->contents['json']['header']=$data[$first];
			
			$result=[];
			foreach ($data as $key => $values)
				if ($key!==$first)
				{
					foreach ($this->contents['json']['header'] as $header_key => $value)
						$result[$value]=$values[$header_key];
				}
				
			$this->contents['json']['data']=$result;
			return $result;
		}
		
		function contents($format='json')
		{
			return parent::contents($format);
		}
	}	
?>