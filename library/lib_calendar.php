<?php
	class lib_calendar
	{
		
	}
	
	class date_iterator extends s_lib implements Iterator
	{
		var $timescape;
		public function rewind(): void
		{
			reset($this->timescape);
		}
		
		public function current(): mixed
		{
			$var = current($this->timescape);
			return $var;
		}
		
		public function key(): mixed
		{
			$var = key($this->timescape);
			return $var;
		}
		
		public function next(): void
		{
			next($this->timescape);
			//			return $var;
		}
		
		public function valid(): bool
		{
			$key = key($this->timescape);
			$var = ($key !== NULL && $key !== FALSE);
			return $var;
		}
		
		function timestamps()
		/*
		 * Returns all the timestamp of this iterators
		 */
		{
			return array_keys($this->timescape);
		}
		
		function __construct($begin, $end, $interval=1)
		/*
		 * Begin - End Date is Inclusive
		 * Begin and end, if timestamp, must be at the beginning of a day.  Not middle of a day.
		 */
		{
			$this->timescape=array();
			
			if (!is_numeric($begin))
				$start_date=$this->date_string_to_timestamp($begin, 'Y-m-d');
			else
				$start_date=$begin;
					
			if (!is_numeric($end))
				$end_date=$this->date_string_to_timestamp($end, 'Y-m-d');
			else
				$end_date=$end;
							
			if ($start_date==$end_date)
				$end_date=$this->end_of_day($end_date, 'timestamp');
								
			$counter=$start_date;
			while ($counter<=$end_date)
			{
				$this->timescape[$counter]=array('day'=> (int) date('d', $counter),
					'month' => (int) date('m', $counter), 'year' => (int) date('Y', $counter),
					'dayofweek' => (int) date('N', $counter), 'Y-m-d' => date("Y-m-d", $counter),
					'timestamp' => $counter, 'd-m' => date("d-m", $counter), 'd' => date('d', $counter)
					);
				$counter=mktime(0, 0, 0, date('m', $counter), date('d', $counter)+$interval, date('Y', $counter));
			}
		}
		
	}
	
	class month_iterator implements Iterator
	{
		var $timescape;
		public function rewind(): void
		{
			reset($this->timescape);
		}
		
		public function current(): mixed
		{
			$var = current($this->timescape);
			return $var;
		}
		
		public function key(): mixed
		{
			$var = key($this->timescape);
			return $var;
		}
		
		public function next(): void
		{
			next($this->timescape);
			//			return $var;
		}
		
		public function valid(): bool
		{
			$key = key($this->timescape);
			$var = ($key !== NULL && $key !== FALSE);
			return $var;
		}
		
		function __construct($begin, $end)
		{
			$this->timescape=array();
			
			$date=date_parse_from_format('Y-m', $begin);
			$start_date=mktime(0, 0, 0, $date['month'], 1, $date['year']);
			$date=date_parse_from_format('Y-m', $end);
			$end_date=mktime(0, 0, 0, $date['month']+1, 0, $date['year']);
			
			$counter=$start_date;
			while ($counter<$end_date)
			{
				$this->timescape[$counter]=array('day'=> date('d', $counter),
					'month' => (int) date('m', $counter),	'year' => (int) date('Y', $counter));
				$counter=mktime(0, 0, 0, date('m', $counter)+1, 1, date('Y', $counter));
			}
		}
	}
		
	class days extends smart_list
	/*
	 * List of days
	 * 
	 * List of days is unique, becauses every element will be stored using timestamp as 
	 * its uniqueid
	 */
	{
		function __construct($credentials, $secure_db, $readonly_db, $table_name, $date_range=array())
		{
			parent::__construct($credentials, $secure_db, $readonly_db, $table_name, $date_range);
			
			/*
			 * Default element_class is day_data for compatibility reason, but there is no other 
			 * reason and should be changed to days in the future
			 */
			$this->element_class='day_data';
			
			if (!empty($date_range))
				$this->load();			
		}
		
		function get_day($timestamp, $round=true)
		/*
		 * Get a timestamp day, if not existed then create new
		 * 
		 * If round is true, it will timestamp to the beginning of the day
		 */
		{
			$uniqueid=$round ? $timestamp : $this->begin_of_day($timestamp, 'timestamp', 'timestamp');
			
			$day=$this->select_or_new($uniqueid);
			
			return $day;
		}
		
		function load_range($begin, $end)
		{
			$days=new date_iterator($begin, $end);
			
			return $this->db_lookup(...$days->timestamps());
		}
		
		function prepare($range)
		/*
		 * Initialising blank days, do not call with load
		 */
		{
			$dayclass=$this->element_class;
			
			$days=new date_iterator($range['startdate'], $range['enddate']);
			
			foreach ($days as ['timestamp' => $timestamp])
			{
				$dd=new $dayclass($this->credentials, $this->credentials->primary(),
					$this->secure_db, $this->readonly_db, $timestamp);
				
				$this->add($dd);
			}
		}
		
		function load()
		/*
		 * Automatically initialising days, do not call with initialise
		 */
		{
			$link=$this->secure_db;
			 
			$date_id_array=array();
			$days=new date_iterator($this->range['startdate'], $this->range['enddate']);

			$timestamps=[];
			foreach ($days as ['timestamp' => $timestamp])
			{				
				$date_id_array[]='uniqueid='.$timestamp;
				$timestamps[$timestamp]=false;
			}
			
			$date_id=implode(' OR ', $date_id_array);
			$query="SELECT * FROM $this->primaryTable WHERE ".$date_id;
			$query_no=$link->query($query);
			 			
			$dayclass=$this->element_class;
			
			while ($row=$link->next_result($query_no))
			{
				$dd=new $dayclass($this->credentials, $this->credentials->primary(),
					$this->secure_db, $this->readonly_db, $row['uniqueid']);

				$timestamps[$row['uniqueid']]=true;
				$dd->load($row['uniqueid'], $row);
				
				$this->add($dd);
			}
			
			/*
			 * Ensure to initialise non existant date data
			 */
			foreach ($timestamps as $timestamp => $exists)
				if (!$exists)
				{
					$dd=new $dayclass($this->credentials, $this->credentials->primary(),
						$this->secure_db, $this->readonly_db, $timestamp);
					
					$this->add($dd);					
				}			
		 }		
	}
	
	class day extends record
	{
		use t_datetime;
		
		function __construct($credentials, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $credentials->primary(), $secure_db, $readonly_db, []);
		}
		
		function more_recent($timestamp)
		/*
		 * This will returrn that this day is actualy younger than the timestamp 
		 * Input integer timestamp
		 */
		{
			return ($this->uniqueid>$timestamp);
		}
		
		function __toString()
		{
			return $this->date($this->get('uniqueid'), 'd-m');
		}
	}
	
	class day_list extends smart_list
	/*
	 * Calendar Shaped Day List
	 *
	 * Use init_days from calendar_shape interface to initialise this with form
	 * and each form is a single day for its modification.
	 *
	 */
	{
		var $credentials;
		function __construct($credentials, $secure_db, $readonly_db, $date_range=array())
		{
			$this->credentials=$credentials;
			parent::__construct($credentials, $secure_db, $readonly_db, ctableDays, $date_range);
			
//			if (!empty($date_range))
//				$this->load();
				
				//			$this->to_global('day_list');
		}
				
		function parentHTML($dateslot=array())
		{
			return parent::toHTML();
		}
				
		function toHTML($dateslot=array())
		{
			$week_count=ceil(count($dateslot) / 7);
			
			$result="<table><tr><th>Senin</th><th>Selasa</th><th>Rabu</th><th>Kamis</th><th>Jumat</th><th class='stl_holiday'>Sabtu</th><th class='stl_holiday'>Minggu</th></tr>";
			for ($j=0; $j<$week_count; $j++)
			{
				$result.='<tr>';
				for ($i=1; $i<8; $i++)
				{
					$result.='<td>';
					$dateslot_id=$i+($j*7);
					
					$day=$this->select($dateslot[$dateslot_id]);
					if ($day)
					{
						if ($day->public_holiday())
							$result.=$day->toInlineHTML($this->display, "<div class='stl_holiday'>", '</div>');
						else
							$result.=$day->toInlineHTML($this->display, '<div>', '</div>');
					}
					else
					{
						$result.=date('j-n', $dateslot[$dateslot_id]);
					}
					$result.='</td>';
				}
				$result.='</tr>';
			}
			$result.='</table>';
			return $result;
		}
		
		function get_error_message()
		{
			return $this->elements_function('get_error_message', []);
		}		
	}
	
	class day_input extends inputform_control
	/*
	 * Single day input
	 */
	{
		var $day_data;
		var $dataholder_functions;
		
		function __construct($credentials, $name, $action, $method, $return, $secure_db, $readonly_db, $date, $day_class='standard_day')
		{
			parent::__construct($credentials, $name, $action, $method, $return, $secure_db, $readonly_db, 'id');
			
			$this->add_hidden_input("id", $date);
			$this->add_hidden_input('reservation_date', date('Y-m-d', $date));
			
			$this->add_credential($this->credentials->primary());
			
			$this->set_dataholder('day_data', new $day_class($credentials, $credentials->anycredential(), $secure_db, $readonly_db, $date));
			$this->day_data=$this->dataholder();
		}
		
		function day_of_week()
		{
			return date('N', $this->select('id'));
		}
		
		function rf_date($callback)
		{
			return date($callback, $this->select('id'));
		}
		
		function public_holiday()
		{
			if ($this->day_data)
				return $this->day_data->public_holiday();
			else
				return false;
		}
		
		function submit_input($name, $caption, $submit, $required_data=[], $additional_data=[], $hint='')
		{
			$result=new inputform_control($this->credentials, $name, 
				$this->action, $this->method, $this->return);
			
			$result->set_junction($this->interface);
			
			$result->add_hidden_input('id', $this->select('id'));
			$result->add_hidden_input('reservation_date', $this->select('reservation_date'));

			foreach ($required_data as $data)
				$result->add_hidden_input($data, $this->select($data));
				
			foreach ($additional_data as $data => $value)
				$result->add_hidden_input($data, $value);
			
			$result->add_submit_button('', $submit, $caption, [], 0, $hint);
			
			$result->add_credential($this->credentials->primary());
			
			return $result;			
		}
	}	

	class calendar_shape extends junction
	/*
	 * Interface for day_list 
	 */
	{
		var $filters;
		var $action;
		var $method;
		
		var $dateslot;
		
		var $date_counter;
		
		var $days;
		
		function assign_dateslot()
		/*
		 * This method will assign a dateslot to day index, so information on that day will be
		 * assigned to that slot
		 */
		{
			$this->dateslot=array();
			
			$this->days->rewind();
			$first=$this->days->current();
			$first_dow=$first->day_of_week();
			$first_day=$first->select('id');
			
			$day_counter=0;
			for ($i=$first_dow-1; $i>0; $i--)
			{
				$day_counter++;
				$this->dateslot[$i]=mktime(0, 0, 0, date("m", $first_day), date("d", $first_day)-($first_dow-$i), date("Y", $first_day));
			}
			
			$i=$first_dow;
			foreach ($this->days as $day)
			{
				$day_counter++;
				$this->dateslot[$i]=$day->index();
				$i++;
			}
			
			while ($day_counter<42)
			{
				$day_counter++;
				$this->dateslot[$day_counter]=mktime(0, 0, 0, date("m", $first_day), date("d", $first_day)+($day_counter-$first_dow),
					date("Y", $first_day));
			}
		}
		
		function month_selector_form($callback, $return, $min_date, $max_date)
		{
			$date_input=new inputform_control($this->credentials, 'date_input', $callback, 'post', $return);
			$date_input->add_month_input('Enter Month', 'target_month', date('Y-m', $this->filters['startdate']),
				$min_date, $max_date, array('required' => true));
			$date_input->add_submit_button('Go', 'submit', 'View', array());
			$date_input->add_credential($this->credentials->primary());
		
			return $date_input;
		}
		
		function html_month_selector($callback, $return, $min_date, $max_date)
		{
			return $this->month_selector_form($callback, $return, $min_date, $max_date)->toStringFormat(' ', true);
		}
		
		function toHTML()
		{
			if (!$this->fibre_enabled())
				$result=$this->days->toHTML($this->dateslot);
			else
				$result=['type' => 'html'];
			
			return $result;
		}
		
		function __construct($credentials, $input, $secure_db, $readonly_db, $filters, $action='', $method='')
		{
			$this->action=$action;
			$this->method=$method;
			
			$this->filters=$filters;
			
			parent::__construct($credentials, $input, $secure_db, $readonly_db, $filters);
			
			//			$this->fill_customer_list();
			
			$this->days=new day_list($credentials, $secure_db, $readonly_db);
		}
		
		function date_filter($start_range, $end_range='')
		{
			$date_filter=array();
			$select_month=date_parse_from_format('Y-m', $start_range);
			$date_filter['startdate']=mktime(0, 0, 0, $select_month['month'], 1, $select_month['year']);
			
			if ($end_range=='')
			{
				$select_month=date_parse_from_format('Y-m', $start_range);
				$select_month['month']++;
			}
			else
			{
				$select_month=date_parse_from_format('Y-m', $end_range);
			}
			
			$date_filter['enddate']=mktime(23, 59, 59, $select_month['month']+1, 0, $select_month['year']);
			return $date_filter;
		}
		
		function init_days($class_name, $action, $method, $return, $input)
		/*
		 * This funciton will init dateslot according to the class name and assign input
		 * on when needed to the particular day
		 */
		{
			$activeday=$this->filters['startdate'];
			$date_counter=0;
			while ($activeday<=$this->filters['enddate'])
			{
				$day=new $class_name($this->credentials, "$date_counter", $action, $method, $return,
					$this->secure_db, $this->readonly_db, $activeday);
				$day->set_junction($this);
				
				if ((isset($input['id'])) && ($activeday==$input['id']))
				{
					$day->input($input);
				}
				
				/*				$day->day_data->addData('dayoff', 'revoked', 'linsony');
				 $day->day_data->addData('authority', 'linsony', 'linsony');*/
				
				$this->days->add($day);
				$date_counter++;
				
				$activeday=mktime(0, 0, 0, date("m", $activeday), date("d", $activeday)+1, date("Y", $activeday));
			}
			
			$this->date_counter=$date_counter-1;
			
			$this->validate();
			//			$this->days->init_swapwork_data($this->form->select('username'));
		}
		
		function html_date_input($csv=false)
		{
			$date_input=new lib_form($this->credentials, 'date_input', $this->action, $this->method, '');
			$date_input->add_month_input('Enter Month', 'target_month', date('Y-m', $this->filters['startdate']), '2016-10',
				date('Y-m'), array('required' => true));
			$date_input->add_submit_button('Go', 'submit', 'View', array());
			
			if ($csv) $date_input->add_submit_button('Go', 'csv', 'Download CSV', array());
			
			$date_input->add_credential($this->credentials->primary());
			
			return $date_input;
		}
		
		function html_date_range_input($csv=false, $max=5, $hidden_data=[])
		{
			$date_input=new lib_form($this->credentials, 'date_input', $this->action, $this->method, '');
			$date_input->add_month_input('Start Month', 'startdate', date('Y-m', $this->filters['startdate']), '2016-10',
				date('Y-m'), array('required' => true));
			
			$date_input->add_month_input('End Month', 'enddate', date('Y-m', $this->filters['enddate']), '2016-10',
				date('Y-m'), array('required' => true));
			
			foreach ($hidden_data as $key => $value)
				$date_input->add_hidden_input($key, $value);
				
			$date_input->add_number_input('Max Show', 'max_show', $max, 1, 10, array('required' => true));
				
			$date_input->add_submit_button('Go', 'submit', 'View', array());
				
			if ($csv) $date_input->add_submit_button('Go', 'csv', 'Download CSV', array());
				
			$date_input->add_credential($this->credentials->primary());
				
			return $date_input;
		}
		
		function fill_year_journal($begin=null, $end=null)
		{
			$year=date('Y', \date::today('timestamp'));
			$year_begin=$begin ?? "$year-01-01";
			$year_end=$end ?? "$year-12-31";
			
			$day_list=new day_list($this->credentials, $this->secure_db, $this->readonly_db, ['startdate' => $year_begin, 'enddate' => $year_end]);
			$day_list->to_global('day_list');
		}		
		
		function validate()
		{
			foreach ($this->days as $day)
			{
				if ($day->valid_input())
				{
					$this->error=$day->error;
					break;
				}
			}
		}
	}
	
	class standard_day extends record
	/*
	 * Standard recorded day, wanted to combine to class day
	 */
	{
		var $parent_list;
		
		use t_datetime;
		
		function today($format='Y-m-d')
		{
			return $this->date($this->select('uniqueid'), $format);
		}
		
		function __construct($credentials, $active_credential, $secure_db, $readonly_db, $date, $data=array())
		{
			parent::__construct($credentials, $active_credential, $secure_db, $readonly_db, $data, ctableDays, $date);
			
			$this->temp_data[]='_max_off';
			$this->temp_data[]='_division_count';
		}
				
		function get_error_message()
		{
			return $this->error;
		}		
	}	 
?>