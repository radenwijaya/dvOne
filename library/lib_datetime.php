<?php
	/*
	 * End of Time constant is year 2099, can be extended to whatever time you want but 
	 * it might cause some funny consequences
	 */
	define('EOT', mktime(23, 59, 59, 12, 31, 2099));
	
	class date
	/*
	 * Class date to be referred when calling statically, e.g. to make things quick 
	 */
	{
		use s_datetime;
				
		final static function today($format='Y-m-d')
		/*
		 * Today is asensitive functionn, in standard_day it worked as standarrd day's today
		 * It was prone to programmer's error to choose
		 * 
		 * Seperating today to  
		 */
		{
			switch ($format)
			{
				case 'timestamp': 
					$result=time();
					break;
				case 'begin_of_day':
					$result=self::begin_of_day(time(), 'timestamp', 'timestamp');
					break;					
				default:				
					$result=date($format);
					break;
			}
			
			return $result;
		}
	}
	
	trait s_datetime
	/*
	 * Static date time functions
	 */
	{
		static function date_string_change_format($datestring, $old_format, $new_format)
		{
			return date($new_format, self::date_string_to_timestamp($datestring, $old_format));
		}
		
		static function date_string_to_timestamp($datestring, $format='Y-m-d')
		{
			$date_array=date_parse_from_format($format, $datestring);
			$timestamp=mktime($date_array['hour'], $date_array['minute'], $date_array['second'], $date_array['month'], $date_array['day'], $date_array['year']);
			
			return $timestamp;
		}
		
		static function year_quarter($timestamp=null)
		{
			$month=date('m', $timestamp ?? time());
			return intdiv($month-1, 3)+1;
		}
		
		static function begin_of_year($output='string', $datestring='', $input='Y-m-d')
		{
			if ($datestring=='') $datestring=date::today();
			
			if (is_numeric($datestring))
			{
				$input='Y-m-d';
				$datestring=date($input, $datestring);
			}
			
			$date_array=date_parse_from_format($input, $datestring);
			$eom=mktime(0, 0, 0, 1, 1, $date_array['year']);
			
			switch ($output)
			{
				case 'string': $result=date('Y-m-d', $eom); break;
				case 'timestamp': $result=$eom; break;
				default: $result=date($output, $eom);
			}
			
			return $result;
		}
		
		static function end_of_year($output='string', $datestring='', $input='Y-m-d')
		{
			if ($datestring=='') $datestring=date::today();
			
			if (is_numeric($datestring))
			{
				$input='Y-m-d';
				$datestring=date($input, $datestring);
			}
			
			$date_array=date_parse_from_format($input, $datestring);
			$eom=mktime(0, 0, 0, 12, 1, $date_array['year']);
			
			switch ($output)
			{
				case 'string': $result=date('Y-m-d', $eom); break;
				case 'timestamp': $result=$eom; break;
				default: $result=date($output, $eom);
			}
			
			return $result;
		}
		
		static function next_x_hour($hour, $start=null, $format='timestamp')
		{
			$result=($start ?? time())+$hour*3600;
			if ($format=='timestamp')
				return $result;
				else
					return date($format, $result);
		}
		
		function days_ago($date, $format='Y-m-d', $days=1)
		/*
		 * Date is always in timestamp foramt
		 */
		{
			$yesterday=$date-86400*$days;
			
			if ($format!='timestamp')
				$yesterday=date($format, $yesterday);
				
				return $yesterday;
		}
		
		static function next_days($days, $timestamp=null, $format='timestamp')
		/*
		 * This will return future date from timestamp
		 */
		{
			$result=($timestamp ?? date::today('timestamp')) + 86400 * $days;
			
			if ($format!='timestamp')
				$result=date($format, $result);
				
			return $result;
		}
		
		static function yesterday($format='Y-m-d', $days=1)
		{
			$yesterday=date::today('begin_of_day')-86400*$days;
			
			if ($format!='timestamp')
				$yesterday=date($format, $yesterday);
				
			return $yesterday;
		}
		
		static function same_hour($time1, $time2)
		{
			return (date('Y-m-d H', $time1 ?: 0)==date('Y-m-d H', $time2 ?: 0));
		}
		
		static function in_date_range(int $day, int $begin, int $end)
		/*
		 * Input must be timestamp
		 */
		{
			
			return (date::begin_of_day($begin, 'timestamp', 'timestamp')<=$day) && 
				   ($day<=date::end_of_day($end, 'timestamp'));
		}
		
		static function same_day($day1, $day2)
		/*
		 * Checks if day1 and day2 is the same day,
		 *
		 * format must be timestamp.
		 */
		{
			return (date('Y-m-d', $day1 ?: 0)==date('Y-m-d', $day2 ?: 0));
		}
		
		static function same_month($day1, $day2)
		{
			return (date('Y-m', (int) $day1 ?: 0)==date('Y-m', (int) $day2 ?: 0));
		}
		
		static function same_week($day1, $day2, $year=true)
		/*
		 * When year is true, it will be same week of the year, other wise will be same week only
		 */
		{
			return $year ? (date('Y-W', $day1 ?: 0)==date('Y-W', $day2 ?: 0)) :
						   (date('Y', $day1 ?: 0)==date('Y', $day2 ?: 0));
		}
		
		static function this_weekday($date, $day)
		{
			if ($day<=0)
				$day+=7;
				
			$n=(int) date('N', $date);
			$ch_day=$day-$n;
				
			if ($ch_day<0)
				$ch_day+=7;
					
			return self::change_day($ch_day, $date, 'timestamp');
		}
		
		static function this_weekend($date)
		{
			return self::this_weekday($date, 7);
		}		
		
		static function get_day_of_week_timestamp($day, $date)
		{
			$difference=date('N', $date)-1;
			
			$date_array=date_parse_from_format('Y-m-d', date('Y-m-d', $date));
			
			return mktime(0, 0, 0, $date_array['month'], $date_array['day']-$difference+$day-1, $date_array['year']);
		}		
		
		static function date_difference($day1, $day2, $format)
		/*
		 * Attention: use format as %a to tell the real difference between days 
		 */
		{
			$datetime1=new DateTime('@'.$day1);
			$datetime2=new DateTime('@'.($day2 ?? date::today('timestamp')));
			
			$difference=$datetime1->diff($datetime2);
			
			return ($difference->format($format));
		}
		
		static function month_difference($day1, $day2=null)
		/*
		 * Returns month differences, because the function date differences just cover different months
		 * but did not cover different year
		 */
		{
			$datetime1=new DateTime('@'.$day1);
			$datetime2=new DateTime('@'.($day2 ?? date::today('timestamp')));
			
			$difference=$datetime1->diff($datetime2);
			
			$month_diff=$difference->format('%m');			
			
			$year_diff=$difference->format('%y');
			
			return $year_diff*12+$month_diff;
		}
		
		static function ts_date_difference($day1, $day2=null)
		{
			return ($day1-($day2 ?? date::today('timestamp')))/86400;
		}
		
		static function date_string_difference($date1, $date2, $absolute=true)
		{
			if ($absolute) $output_format="%a";
			else $output_format="%r%a";
			
			if (!$date1) $date1='1970-01-01';
			if (!$date2) $date2='1970-01-01';
			
			$datetime1=new DateTime($date1);
			$datetime2=new DateTime($date2);
			$difference=$datetime1->diff($datetime2, $absolute);
			
			return $difference->format($output_format);
		}
		
		static function change_day($difference, $datestring='', $output='string')
		{
			if ($datestring=='')
				$datestring=date::today($output);
				
				if (($output=='timestamp') && (is_int($datestring)))
					$datestring=date('Y-m-d', $datestring);
					
					$date_array=date_parse_from_format('Y-m-d', $datestring);
					$eom=mktime(0, 0, 0, $date_array['month'], $date_array['day']+$difference, $date_array['year']);
					
					if ($output!='timestamp')
						return date($output=='string' ? 'Y-m-d' : $output, $eom);
						else
							return $eom;
		}
		
		static function change_month($difference, $datestring='', $output='string')
		/*
		 * This will return the same date of previous month 
		 * 
		 * if previous month have no date, it will automatically go to eom
		 * 
		 * Newly Updated and Untested.  Needs more testing!
		 */
		{
			if ($datestring=='')
				$datestring=date::today($output);
				
			if (($output=='timestamp') && (is_int($datestring)))
				$datestring=date('Y-m-d', $datestring);
					
			$date_array=date_parse_from_format('Y-m-d', $datestring);
/*			var_dump($date_array);
			
			This functions are tested and working okay, but since I have no beta testing time
			i'll resort to use PHP default functions although it is slower
			One day please return to the fast version.
			
			$month_diff=$difference % 12; // Ensure its always less than 12
			$year_diff=floor($difference / 12);
			
			switch (true)
			{
				case $date_array['month']+$month_diff>12:
					$new_month=$date_array['month']+$month_diff-12;
					$new_year=$date_array['year']+$year_diff+1;
					break;
				case $date_array['month']+$month_diff<1:
					$new_month=$date_array['month']+$month_diff+12;
					$new_year=$date_array['year']+$year_diff-1;
					break;
				default:
					$new_month=$date_array['month']+$month_diff;
					$new_year=$date_array['year']+$year_diff;
					break;
			}
			
			$max_day=cal_days_in_month(CAL_GREGORIAN, $new_month, $new_year);			
			$eom=mktime(0, 0, -1, $date_array['month']+$difference, $date_array['day'] < $max_day ? $date_array['day'] : $max_day, $date_array['year']);*/
			
			$fom=date('Y-m-d', mktime(0, 0, 0, $date_array['month']+$difference, 1, $date_array['year']));

			$date_array_1=date_parse_from_format('Y-m-1', $fom);
			
			$max_day=cal_days_in_month(CAL_GREGORIAN, $date_array_1['month'], $date_array_1['year']);

			$target_date=mktime(0, 0, -1, $date_array['month']+$difference, 
				$date_array['day'] < $max_day ? $date_array['day'] : $max_day, $date_array['year']);			
			
			if ($output!='timestamp')
				return date($output=='string' ? 'Y-m-d' : $output, $target_date);
			else
				return $target_date;
		}
		
		static function first_of_month($datestring='', $output='string', $input='Y-m-d')
		{
			if ($datestring=='') $datestring=date::today();
			
			if (is_numeric($datestring))
			{
				/*
				 * Change to Y-m-d format
				 */
				$input='Y-m-d';
				$datestring=date($input, $datestring);
			}
			
			$date_array=date_parse_from_format($input, $datestring);
			$fom=mktime(0, 0, 0, $date_array['month'], 1, $date_array['year']);
			
			switch ($output)
			{
				case 'string': $result=date('Y-m-d', $fom); break;
				case 'timestamp': $result=$fom; break;
				default: $result=date($output, $fom);
			}
			
			return $result;
		}
		
		static function begin_of_month($datestring='', $output='string', $input='Y-m-d')
		{
			if ($datestring=='') $datestring=date::today();
			
			if (is_numeric($datestring))
			/*
			 * Converting timestamp to datestring
			 */
			{
				$input='Y-m-d';
				$datestring=date($input, $datestring);
			}
			
			$date_array=date_parse_from_format($input, $datestring);
			$eom=mktime(0, 0, 0, $date_array['month'], 1, $date_array['year']);
			
			switch ($output)
			{
				case 'string': $result=date('Y-m-d', $eom); break;
				case 'timestamp': $result=$eom; break;
				default: $result=date($output, $eom);
			}
			
			return $result;
		}
		
		static function end_of_month($datestring='', $output='string', $input='Y-m-d')
		{
			if ($datestring=='') $datestring=date::today();
			
			if (is_numeric($datestring))
			{
				$input='Y-m-d';
				$datestring=date($input, $datestring);
			}
			
			$date_array=date_parse_from_format($input, $datestring);
			$eom=mktime(23, 59, 59, $date_array['month']+1, 0, $date_array['year']);
			
			switch ($output)
			{
				case 'string': $result=date('Y-m-d', $eom); break;
				case 'timestamp': $result=$eom; break;
				default: $result=date($output, $eom);
			}
			
			return $result;
		}
		
		static function begin_of_day($datestring='', $output_format='timestamp', $input_format='Y-m-d')
		{
			if ($datestring=='') $datestring=date::today();
			
			if (is_numeric($datestring))
			{
				$input_format='Y-m-d';
				$datestring=date($input_format, $datestring);
			}
			
			if (is_string($datestring))
			{
				$date_array=date_parse_from_format($input_format, $datestring);
			}
			
			$bod=mktime(00, 00, 00, $date_array['month'], $date_array['day'], $date_array['year']);
			
			switch ($output_format)
			{
				case 'string': $result=date('Y-m-d H:i:s', $bod); break;
				case 'timestamp': $result=$bod; break;
				default: $result=date($output_format, $bod);
			}
			
			return $result;
		}
		
		static function mid_of_day($datestring='', $output='string')
		{
			$bod=self::begin_of_day($datestring, 'timestamp')+43200;
			
			switch ($output)
			{
				case 'string': $result=date('Y-m-d H:i:s', $bod); break;
				case 'timestamp': $result=$bod; break;
				default: $result=date($output, $bod);
			}
			
			return $result;
		}
		
		static function end_of_day($datestring='', $output='timestamp')
		{
			if ($datestring=='') $datestring=date::today();
			
			if (is_numeric($datestring))
			{
				$datestring=date('Y-m-d', $datestring);
			}
			
			if (is_string($datestring))
			{
				$date_array=date_parse_from_format('Y-m-d', $datestring);
			}
			$eod=mktime(24, 00, 00, $date_array['month'], $date_array['day'], $date_array['year'])-1;
			
			switch ($output)
			{
				case 'string': $result=date('Y-m-d H:i:s', $eod); break;
				case 'timestamp': $result=$eod; break;
				default: $result=date($output, $eod);
			}
			
			return $result;
		}
		
		static function time_of_day($time, $datestring='', $output='H:i:s')
		/*
		 * Time is an array of H:M:S
		 */
		{
			if ($datestring=='') $datestring=date::today();
			
			if (is_numeric($datestring))
			{
				$datestring=date('Y-m-d', $datestring);
			}
			
			if (is_string($datestring))
			{
				$date_array=date_parse_from_format('Y-m-d', $datestring);
			}
			$eod=mktime($time['h'] ?? 00, $time['m'] ?? 00, $time['s'] ?? 00, $date_array['month'], $date_array['day'], $date_array['year'])-1;
			
			switch ($output)
			{
				case 'string': $result=date('Y-m-d H:i:s', $eod); break;
				case 'timestamp': $result=$eod; break;
				default: $result=date($output, $eod);
			}
			
			return $result;
		}
		
		static function seconds_of_day($timestring, $format='H:i')
		/*
		 * Convert from timestring, e.g. 8:23 to second of the day
		 */
		{
			$date_array=date_parse_from_format($format, $timestring);
			
			return $date_array['hour']*3600+$date_array['minute']*60+$date_array['second'];
		}
		
		static function get_hour_string($hour)
		{
			$time=self::begin_of_day(\date::today(), 'timestamp')+$hour*3600;
			
			return date('H:i', $time);
		}
		
		static function hour_of_day($hour, $time)
		/*
		 * input timestamp
		 */
		{
			$result=self::begin_of_day($time, 'timestamp')+$hour*3600;
			
			return $result;
		}
		
		static function get_month_list(...$exceptions)
		{
			$month=[1 => ['value' => '1', 'display' => 'Januari'],
					2 => ['value' => '2', 'display' => 'Februari'],
					3 => ['value' => '3', 'display' => 'Maret'],
					4 => ['value' => '4', 'display' => 'April'],
					5 => ['value' => '5', 'display' => 'Mei'],
					6 => ['value' => '6', 'display' => 'Juni'],
					7 => ['value' => '7', 'display' => 'Juli'],
					8 => ['value' => '8', 'display' => 'Agustus'],
					9 => ['value' => '9', 'display' => 'September'],
					10 => ['value' => '10', 'display' => 'October'],
					11 => ['value' => '11', 'display' => 'November'],
					12 => ['value' => '12', 'display' => 'December']];
			
			$result=[];
			foreach ($month as $key => $value)
				if (!in_array($key, $exceptions))
					$result[$key]=$value;
					
			return $result;
		}
		
		static function get_day_of_week_list(...$exceptions)
		{
			$day=[1 => ['value' => '1', 'display' => 'Senin'],
				  2 => ['value' => '2', 'display' => 'Selasa'],
				  3 => ['value' => '3', 'display' => 'Rabu'],
				  4 => ['value' => '4', 'display' => 'Kamis'],
				  5 => ['value' => '5', 'display' => 'Jumat'],
				  6 => ['value' => '6', 'display' => 'Sabtu'],
				  7 => ['value' => '7', 'display' => 'Minggu']];
			
			$result=[];
			foreach ($day as $key => $value)
				if (!in_array($key, $exceptions))
					$result[$key]=$value;
					
			return $result;
		}
		
		static function get_hour_list()
		{
			$hours=[];
			for ($h=0; $h<24; $h++)
				$hours[$h]=['value' => $h, 'display' => "$h:00"];
			
			return $hours;
		}
		
		static function date($value, $format="Y-m-d")
		/*
		 * Return the date of a timestamp with output format
		 */
		{
			if (is_numeric($value))
				return date($format, $value);
			else
				return $value;
		}
		
		static function time($value, $format="H:i:s")
		{
			if (is_integer($value))
				return date($format, $value);
			else
				return $value;
		}		
	}
	
	trait t_datetime
	/*
	 * Datetime functions 
	 */
	{		
		use s_datetime;		
		
/*		function today($format='Y-m-d')
		{
			return \date::today($format);
		}*/
		
		function as_day_of_week()
		{
			return self::date($this->uniqueid, 'N');
		}
		
		function start_of_week($date, $local_start=false)
		{
			$date_array=date_parse_from_format('Y-m-d', date('Y-m-d', $date));
			
			$difference=date('N', $date)-($local_start ? $this->get_global('settings')->select('week_change_day') : 1);
			if ($difference<0) $difference+=7;
			
			return mktime(0, 0, 0, $date_array['month'], $date_array['day']-$difference, $date_array['year']);
		}
		
		function end_of_week($date, $local_start=false)
		{
			$date_array=date_parse_from_format('Y-m-d', date('Y-m-d', $date));
			
			$week_end=$local_start ? ($this->get_global('settings')->select('week_change_day')-1 ?: 7)  : 7;
			$difference=$week_end - date('N', $date);
			if ($difference<0) $difference+=7;
			
			return mktime(23, 59, 59, $date_array['month'], $date_array['day']+$difference, $date_array['year']);
		}		
						
		var $active_calendars;
		
		function init_calendars()
		/*
		 * Initialise calendarr system
		 */
		{
			$this->active_calendars=[];
			
			/*
			 * Add gregorian calendar
			 */
			$this->add_calendar('gregorian');
		}
		
		function add_calendar($calendar_system): IntlCalendar
		{
			$cal=IntlCalendar::createInstance(default_timezone, default_locale.'@calendar='.$calendar_system);
			
			$this->active_calendars[$calendar_system]=$cal;
			
			return $cal;
		}
		
		function get_calendar_timestamp($calendar_system, $timestamp, $d, $m)
		/*
		 * $d = datee, starts from 1
		 * $m = month of that original calendar, starts from 0
		 * 
		 * return timestamp
		 */
		{
			$this->active_calendars[$calendar_system]->setTime($timestamp*1000);

			$this->active_calendars[$calendar_system]->setDate($this->active_calendars[$calendar_system]->get(IntlCalendar::FIELD_YEAR), $m, $d);
			
			return $this->active_calendars[$calendar_system]->getTime()/1000;
		}
	}	
?>