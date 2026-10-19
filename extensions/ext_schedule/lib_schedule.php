<?php
	/*
	 * Schedule for human to follow
	 * not for machine to
	 * New schedule (for self or division), if admin then global
	 * See schedule
	 * Do schedule (your schedule, regardless of your division)
	 */
	
	class lib_schedule extends extensions
	{
		function __construct($settings, $credentials, $login_data)
		{
			$extension_credentials=[];
			
			parent::__construct($settings, $credentials, $extension_credentials, $login_data);
			
			$this->dm->create_standard_datatable('table_schedule', cUsernameChkSec);
			$this->new_list('schedule_list', 'smart_list', 'schedule', cUsernameChkSec, 'table_schedule');
		}
		
		function menu_set_1()
		{
			$result['credential']=$this->credentials->available_credentials();
			$result['title']='Jadwal';
			
			$result['elements'][]=['caption' => 'Buat Jadwal', 
									junction => 'schedule_form',
								   'params' => [], 'target' => ''];
			
			$result['elements'][]=['caption' => 'Lihat Jadwal',
									junction => 'schedule_view',
								   'params' => [], 'target' => ''];
			
			$result['elements'][]=['caption' => 'Pelaksanaan Jadwal',
									junction => 'schedule_list',
								   'params' => [], 'target' => ''];			
			
			return $result;
		}

		function available_menu()
		{
			$result[]=['f' => 'menu_set_1', 'p' => []];
			
			return $result;
		}
	}
	
	class schedule extends record
	{		
		static function schedule_type()
		{
			$result=['new_schedule_yearly' => ["value" => 'new_schedule_yearly', 'display' => 'Jadwal Tahunan'],
					 'new_schedule_monthly' => ["value" => 'new_schedule_monthly', 'display' => 'Jadwal Bulanan'],
					 'new_schedule_weekly' => ["value" => 'new_schedule_weekly', 'display' => 'Jadwal Mingguan'],
					 'new_schedule_daily' => ["value" => 'new_schedule_daily', 'display' => 'Jadwal Harian']
//				'new_schedule_daily' => ["value" => 'new_schedule_daily', 'display' => 'Jadwal Harian']
			];
			
			return $result;
		}
		
		static function report_class()
		{
			$result=['button_result' => ["value" => 'button_result', "display" => 'Tombol'],
					 'number_result' => ["value" => 'number_result', "display" => 'Angka'],
					 'text_result' => ["value" => 'text_result', "display" => 'Tulisan']
			];
			
			return $result;
		}
		
		function get_report_class()
		{
			return $this->select('report');
		}
		
		function __construct($credentials, $secure_db, $readonly_db)
		{
			$settings=$this->get_global('settings');
			
			parent::__construct($credentials, $credentials->anycredential(), $secure_db, $readonly_db, [],
				$settings->select(cUsernameChkSec, 'table_schedule'));						
		}
		
		function add_daily($type, $name, $report, $division, $hours)
		{
			$this->addData('name', $name);
			$this->addData('type', $type);
			$this->addData('report', $report);
			$this->addData('division', $division);
			$this->addData('hours', $hours);
			$this->addData('starting', \date::today('timestamp'));
		}
		
		function add_weekly($type, $name, $report, $division, $days_of_week)
		{
			$this->addData('name', $name);
			$this->addData('type', $type);
			$this->addData('report', $report);
			$this->addData('division', $division);
			$this->addData('days_of_week', $days_of_week);
			$this->addData('starting', \date::today('timestamp'));
		}
		
		function add_monthly($type, $name, $report, $division, $date)
		{
			$this->addData('name', $name);
			$this->addData('type', $type);
			$this->addData('report', $report);
			$this->addData('division', $division);
			$this->addData('date', $date);
			$this->addData('starting', \date::today('timestamp'));
		}		
		
		function add_yearly($type, $name, $report, $division, $month, $date)
		{
			$this->addData('name', $name);
			$this->addData('type', $type);
			$this->addData('report', $report);
			$this->addData('division', $division);
			$this->addData('month', $month);
			$this->addData('date', $date);
			$this->addData('starting', \date::today('timestamp'));
		}	
		
		function previous_schedule()
		/* 
		 * this will return previouse and today schedule
		 */
		{
			$result=[];
			$users=$this->select("users") ?: [];  
			$starting=array_key_last($users) ?? $this->select('starting');
			
			switch ($this->select("type"))
			{
				case "new_schedule_daily":
					$hours=$this->select('hours');
					$month=date('n');
					$date=date('j');
					$schedule_time=\date::today('timestamp');					
					while ($starting<$schedule_time)
					{
						foreach ($hours as $hour)
						{								
							$time=mktime($hour, 0, 1, $month, $date);
							if ((($time>$starting) && ($time<=\date::today('timestamp'))) || 
								($this->same_day($time, \date::today('timestamp'))))
							{
								$result[$time]=$time;
								if (!$this->select('timings', $time))
									$this->addData('timings', 0, $time);									
							}
							$schedule_time=$time;							
						}
						$date--;
					}					
					break;
				case "new_schedule_weekly":
					$dows=$this->select("days_of_week");
					$date_match=[];					
					
					$month=date("n");
					$date=date("j");					
					for ($i=0; $i<7; $i++)
					{
						$time=mktime(0, 0, 1, $month, $date-$i);
						if (array_search(date("N", $time), $dows)!==false)
							$date_match[]=$time;
					}
					
					$schedule_time=\date::today('timestamp');					
					while ($starting<$schedule_time)
					{
						foreach ($date_match as $key => $time)
						{
							if (($time>$starting) && ($time<=\date::today('timestamp')))
							{
								$result[$time]=$time;
								if (!$this->select('timings', $time))
									$this->addData('timings', 0, $time);
							}
							$schedule_time=$time;
							
							$date_match[$key]=$time-7*86400;
						}						
					}
					break;
				case "new_schedule_monthly":
					$dates=$this->select("date");
					
					$schedule_time=\date::today('timestamp');
					$month=date("n");
					while ($starting<$schedule_time)
					{
						foreach ($dates as $date)
						{
							$time=mktime(0, 0, 1, $month, $date);
							if ((($time>$starting) && ($time<=\date::today('timestamp'))) || ($this->same_day($time, \date::today('timestamp'))))
							{
								$result[$time]=$time;
								if (!$this->select('timings', $time))
									$this->addData('timings', 0, $time);
							}
							$schedule_time=$time;
						}
						
						$month--;
					}
					break;
				case "new_schedule_yearly":
					$date=$this->select("date");
					
					$months=$this->select("month");
					$year=date("Y");
					$schedule_time=\date::today('timestamp');
					while ($starting<$schedule_time)
					{
						foreach ($months as $month)
						{
							$time=mktime(0, 0, 1, $month, $date, $year);
							if (($time>$starting) && ($time<=\date::today('timestamp')))
							{
								$result[$time]=$time;
								if (!$this->select('timings', $time))
									$this->addData('timings', 0, $time);
							}
							$schedule_time=$time;
						}
						
						$year--;						
					}
					break;
			}
//			$this->today('timestamp');

			return $result;
		}
		
		function execute($timing, $time, $result)
		/*
		 * users is the executer
		 * result is the result of the function
		 * timing is the time when it should be executed
		 */
		{
			$this->addData('users', $this->credentials->username, $timing);
			$this->addData('results', $result, $timing);
			$this->addData('timings', $time, $timing);
		}
			
		function unexecuted()
		/*
		 * I will have previous schedule as array until beginning or skipped
		 */
		{
			$result=[];
			
			$timings=$this->select("timings");
			foreach ($timings as $timing => $time)
				if ($time==0) $result[]=$timing;
			
			return $result;
		}
		
		function execution_list()
		{
			$result=[];
			
			$timings=$this->select("timings");
			foreach ($timings as $timing => $time)
				if ($time!=0) 
				{
					$result[$timing]=['time' => $time, 
									  'user' => $this->select('users', $timing), 
									  'result' => $this->select('results', $timing)];
				}
				
			return $result;				
		}
		
		function execution_history()
		{
			$result=[];
			
			$timings=$this->select("timings");
			foreach ($timings as $timing => $time)
				if ($time!=0)
				{
					$result[$timing]=['time' => $time,
						'user' => $this->select('users', $timing),
						'result' => $this->select('results', $timing)];
				}
				else
				{
					$result[$timing]=[];
				}
				
			return $result;				
		}
		
		function is_relevant()
		{			
			return $this->credentials->check($this->select('division'));
		}
		
		function task_division()
		{
			$credentials_list=$this->get_global('divisions');			
			return $credentials_list[$this->select('division')]['display'];
		}
		
		function get_schedule_type()
		{
			$result='';
			$type=$this->schedule_type()[$this->select('type')];
			switch ($this->select("type"))
			{
				case "new_schedule_daily":
					$hours=$this->select('hours');
					$hours_list=$this->get_hour_list();
					foreach ($hours as $hour)
						$string[]=$hours_list[$hour]['display'];
					$result=implode(', ', $string);
					break;
				case "new_schedule_weekly":
					$dows=$this->select('days_of_week');
					$dow_list=new_schedule::get_day_of_week_list();
					foreach ($dows as $dow)
						$string[]=$dow_list[$dow]['display'];
					$result=implode(', ', $string);
					break;
				case "new_schedule_monthly":
					$dates=$this->select('date');					
					foreach ($dates as $date)
						$string[]=$date;
					$result=implode(', ', $string);
					break;
				case "new_schedule_yearly":
					$date=$this->select("date");
					
					$months=$this->select("month");
					$month_list=new_schedule::get_month_list();
					foreach ($months as $month)
						$string[]=$month_list[$month]['display'];
					$result=$date.' '.implode(', ', $string);
					break;
			}			
			
			return $type['display'].'<br>'.$result;
		}
		
		function get_report_type()
		{
			$type=$this->report_class()[$this->select('report')];
			return $type['display'];
		}		
	}
	
	class new_schedule extends inputform_control
	{		
		var $schedule_list;
		
		function get_divisions()
		{
			if (!$this->get_global('divisions'))
			{
				$result=[];
				$defined_credentials=defined_credentials;
				
				$settings=$this->get_global("settings");			
				$divisions=$settings->get_dynamic_credentials($defined_credentials);
				
				foreach ($divisions as ['name' => $name, 'f_name' => $f_name])
				{
					if ($this->credentials->check($name))
						$result[$name]=['value' => $name, 'display' => $f_name];
				}
				
				$this->to_global('divisions', $result);
			}
			else 
				$result=$this->get_global('divisions');
			
			return $result;
		}
		
		function task_division()
		{
			return $this->dataholder()->task_division();
		}
				
		function get_report_type()
		{
			return $this->dataholder()->get_report_type();
		}
		
		function get_schedule_type()
		{
			return $this->dataholder()->get_schedule_type();
		}		
		
		function stepZero()
		{			
			$result='';
			
			$schedule_types=schedule::schedule_type();
			
			$links=[];
			foreach ($schedule_types as ['value' => $type, 'display' => $display])
			{
				$links[]=$this->make_hyperlink(null, $display, 
					$this->credentials->available_credentials(), ['type' => $type, _junction => 'schedule_form'], '_self');
			}
			$result.=implode('&nbsp;|&nbsp;', $links);

			$result.='<h2>Rutinitas</h2>';
			$i=0;
			$this->schedule_list=$this->get_global('schedule_list');			
			$result.=$this->open_html_table([], 'No', 'Nama', 'Repetisi', 'Laporan', 'Divisi', 'Hapus');
			foreach ($this->schedule_list as $schedule)
			{
				if ($schedule->is_showing())
				{
					$i++;
					$schedule_form=new new_schedule($this->credentials, $schedule->select('uniqueid'), $this->action, $this->post, 
						$this->return, $this->secure_db, $this->readonly_db);
					$schedule_form->set_junction($this->get_junction());
					$schedule_form->set_dataholder('schedule', $schedule);
					
					$result.='<tr>'.$schedule_form->toInlineHTML([['mode' => '_echo', 'value' => $i],
							 ['mode' => 'dataholder', 'value' => 'name'], ['mode' => 'get_schedule_type'], 
							 ['mode' => 'get_report_type'], ['mode' => 'task_division'],
							 //['mode' => 'inline_pause'], 
							 ['mode' => 'inline_delete']]).'</tr>';
				}
			}
			$result.=$this->close_html_table();
			
			return $result;
		}
				
		static function get_date_list()
		{
			$date=[];
			for ($i=1; $i<=31; $i++)
				$date[$i]=['value' => $i, 'display' => $i];
			
			return $date;
		}
		
		function stepOne_button_result()
		{
			return '<h2>Detail Input</h2>'.$this->html_text('submit').'<br><br>';		
		}
		
		function stepOne_number_result()
		{
			$result='<h2>Detail Input</h2>';
			$result.=$this->html_text('submit').'<br>';
			$result.=$this->html_number('value').'<br>';
			$result.=$this->html_number('min').'<br>';
			$result.=$this->html_number('max').'<br><br>';
			
			return $result;
		}

		function stepOne_text_result()
		{
			$result='<h2>Detail Input</h2>';
			$result.=$this->html_text('submit').'<br>';
			$result.=$this->html_text('value').'<br>';
			$result.=$this->html_text('placeholder').'<br>';
			$result.=$this->html_number('maxlength').'<br><br>';
			
			return $result;
		}
		
		function stepTwo_button_result()
		{
			return '<h2>Detail Input</h2>'.$this->html_hidden_display('submit').'<br><br>';			
		}
		
		function stepTwo_number_result()
		{
			$result='<h2>Detail Input</h2>';
			$result.=$this->html_hidden_display('submit').'<br>';
			$result.=$this->html_hidden_display('value').'<br>';
			$result.=$this->html_hidden_display('min').'<br>';
			$result.=$this->html_hidden_display('max').'<br><br>';
			
			return $result;
		}
				
		function stepTwo_text_result()
		{
			$result='<h2>Detail Input</h2>';
			$result.=$this->html_hidden_display('submit').'<br>';
			$result.=$this->html_hidden_display('value').'<br>';
			$result.=$this->html_hidden_display('placeholder').'<br>';
			$result.=$this->html_hidden_display('maxlength').'<br><br>';
			
			return $result;
		}
		
		function prep_button_result()
		{
			$this->add_text_input('Nama Tombol', 'submit', '', '', 8, []);
		}
		
		function prep_number_result()
		{
			$this->prep_button_result();
			
			$this->add_number_input('Default', 'value', '', '', '', []);
			$this->add_number_input('Min', 'min', '', '', '', ['required' => true]);
			$this->add_number_input('Max', 'max', '', '', '', ['required' => true]);
		}
		
		function prep_text_result()
		{
			$this->prep_button_result();
			
			$this->add_text_input('Default', 'value', '', '', 32, ['required' => true]);
			$this->add_text_input('Placeholder', 'placeholder', '', '', 32, []);
			$this->add_number_input('Max length', 'maxlength', '', '', '', ['required' => true]);
		}
		
		function add_detail($schedule)
		{
			$report_input=$this->select('report');
			switch ($report_input)
			{
				case 'button_result': 
					$schedule->addData('submit', $this->select('submit'), 'caption');
					break;
				case 'number_result':
					$schedule->addData('submit', $this->select('submit'), 'caption');
					$schedule->addData('number', $this->select('value'), 'value');
					$schedule->addData('number', $this->select('min'), 'min');
					$schedule->addData('number', $this->select('max'), 'max');
					break;
				case 'text_result':
					$schedule->addData('submit', $this->select('submit'), 'caption');
					$schedule->addData('text', $this->select('value'), 'value');
					$schedule->addData('text', $this->select('placeholder'), 'placeholder');
					$schedule->addData('text', $this->select('maxlength'), 'maxlength');
					break;
			}
		}

		function input($input, ...$fields)
		{
			parent::input($input);
			
			if ($input['delete'])
			{
				$schedule=$this->get_global('schedule_list')->db_select($input['uniqueid']);
				$schedule->delete();
			}
			
			if ($this->select('report'))
			{
				$report_type='prep_'.$this->select('report');			
				$this->{$report_type}();
			}
			
			return parent::input($input);			
		}
		
		function __construct($credentials, $name, $action, $method, $return, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $name, $action, $method, $return, $secure_db, $readonly_db);			
			$this->rv_output_step[]='stepZero';
			$this->rv_output_step[]='stepOne';
			$this->rv_output_step[]='stepTwo';
			
			$this->rv_input_step[]='Save';
			$this->rv_input_step[]='Confirm';
			
			$report_class=schedule::report_class();
			$division=$this->get_divisions();
			
			$this->add_dropdown('Jenis Laporan', 'report', $report_class, '', ['required' => true]);
			$this->add_dropdown('Divisi', 'division', $division, '', ['required' => true]);
			
			$this->add_hidden_input('type', $name);
			
			$this->add_credential(...$credentials->available_credentials());
			
			$this->add_submit_button('', 'save', 'Save');					
		}
		
		function inline_delete()
		{
			$form=new inputform_control($this->credentials, $this->name, $this->action, $this->method, $this->return);
			$form->set_junction($this->get_junction());
			
			$form->add_credential(...$this->credentials->available_credentials());
			$form->add_hidden_input('uniqueid', $this->name);
			$form->add_submit_button('', 'delete', 'Hapus');
			
			return $form->html_simple_input($this->name, 'uniqueid', 'delete');
		}
		
		function inline_pause()
		{
			$form=new inputform_control($this->credentials, $this->name, $this->action, $this->method, $this->return);
			$form->set_junction($this->get_junction());
			
			$form->add_credential(...$this->credentials->available_credentials());
			$form->add_hidden_input('uniqueid', $this->name);
			$form->add_submit_button('', 'pause', 'Pause');
			
			return $form->html_simple_input($this->name, 'uniqueid', 'pause');
		}		
	}
	
	class new_schedule_yearly extends new_schedule
	{
		function __construct($credentials, $name, $action, $method, $return, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $name, $action, $method, $return, $secure_db, $readonly_db);
			
			$month=$this->get_month_list();
			$date=$this->get_date_list();
						
			$this->add_text_input('Nama', 'name', '', '', 64, ['required' => true]);		
			
			$this->add_dropdown('Tanggal', 'date', $date, '', ['required' => true]);
			$this->add_dropdown('Bulan', 'month', $month, '', ['required' => true, 'size' => 12, 'multiple' => true]);						
		}
		
		function stepZero()
		{
			$result=$this->form_open();
			
			$result.=$this->html_text('name').'<br><br>';
			
			$result.=$this->html_dropdown('date').'<br><br>';
			$result.=$this->html_dropdown('month').'<br><br>';
			
			$result.=$this->html_dropdown('report').'<br><br>';
			$result.=$this->html_dropdown('division').'<br><br>';
			
			$result.=$this->html_submit('save');
			
			$result.=$this->html_hidden('type');
			$result.=$this->html_credential();
			
			$result.=$this->form_close();
			
			return $result;
		}
		
		function stepOne()
		{
			$result=$this->form_open();
			
			$result.=$this->html_hidden_display('name').'<br><br>';
			
			$result.=$this->html_hidden_display('date').'<br><br>';
			$result.=$this->html_hidden_display('month').'<br><br>';
			
			$result.=$this->html_hidden_display('report').'<br><br>';
			$result.=$this->html_hidden_display('division').'<br><br>';
			
			$report_type='stepOne_'.$this->select('report');
			$result.=$this->{$report_type}();

			$result.=$this->html_submit('save');
			
			$result.=$this->html_hidden('type');
			$result.=$this->html_credential();
			
			$result.=$this->form_close();
			
			return $result;
		}
		
		function stepTwo()
		{
			$result=$this->form_open();
			
			$result.=$this->html_hidden_display('name').'<br><br>';
			
			$result.=$this->html_hidden_display('date').'<br><br>';
			$result.=$this->html_hidden_display('month').'<br><br>';
			
			$result.=$this->html_hidden_display('report').'<br><br>';
			$result.=$this->html_hidden_display('division').'<br><br>';
			
			$report_type='stepTwo_'.$this->select('report');
			$result.=$this->{$report_type}();
			//$result.=$this->html_submit('save');
			$result.=$this->html_hidden('type');
			$result.=$this->html_credential();
			
			$result.=$this->form_close();
			
			$result.=$this->advanced_exit_form([], $this->final_input_step(), ['close' => 'Close']);
			
			return $result;
		}		
		
		function insert()
		{			
			$schedule=new schedule($this->credentials, $this->secure_db, $this->readonly_db);
			$schedule->add_yearly($this->select('type'), $this->select('name'), $this->select('report'), $this->select('division'), 
				$this->select('month'), $this->select('date'));
			$this->add_detail($schedule);

//			$schedule->addData('starting', mktime(1, 1, 1, 1, 1, 2020));
			$schedule->flush();
//			var_dump($schedule->data);
/*			$schedules=$schedule->previous_schedule();
			foreach ($schedules as $time)
				var_dump(date("Y-m-d", $time));*/	
		}
	}
	
	class new_schedule_monthly extends new_schedule
	{
		function __construct($credentials, $name, $action, $method, $return, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $name, $action, $method, $return, $secure_db, $readonly_db);
			
			$date=$this->get_date_list();
			
			$this->add_text_input('Nama', 'name', '', '', 64, ['required' => true]);
			
			$this->add_dropdown('Tanggal', 'date', $date, '', ['required' => true, 'size' => 31, 'multiple' => true]);
		}
		
		function stepZero()
		{
			$result=$this->form_open();
			
			$result.=$this->html_text('name').'<br><br>';
			
			$result.=$this->html_dropdown('date').'<br><br>';
			
			$result.=$this->html_dropdown('report').'<br><br>';
			$result.=$this->html_dropdown('division').'<br><br>';
			
			$result.=$this->html_submit('save');
			
			$result.=$this->html_hidden('type');
			$result.=$this->html_credential();
			
			$result.=$this->form_close();
			
			return $result;
		}
		
		function stepOne()
		{
			$result=$this->form_open();
			
			$result.=$this->html_hidden_display('name').'<br><br>';
			
			$result.=$this->html_hidden_display('date').'<br><br>';
			
			$result.=$this->html_hidden_display('report').'<br><br>';
			$result.=$this->html_hidden_display('division').'<br><br>';
			
			$report_type='stepOne_'.$this->select('report');
			$result.=$this->{$report_type}();
			
			$result.=$this->html_submit('save');
			
			$result.=$this->html_hidden('type');
			$result.=$this->html_credential();
			
			$result.=$this->form_close();
			
			return $result;
		}		
		
		function stepTwo()
		{
			$result=$this->form_open();
			
			$result.=$this->html_hidden_display('name').'<br><br>';
			
			$result.=$this->html_hidden_display('date').'<br><br>';
			
			$result.=$this->html_hidden_display('report').'<br><br>';
			$result.=$this->html_hidden_display('division').'<br><br>';
			
//			$result.=$this->html_submit('save');
			$report_type='stepTwo_'.$this->select('report');
			$result.=$this->{$report_type}();

			$result.=$this->html_hidden('type');
			$result.=$this->html_credential();
			
			$result.=$this->form_close();
			
			$result.=$this->advanced_exit_form([], $this->final_input_step(), ['close' => 'Close']);
			
			return $result;
		}
		
		function insert()
		{			
			$schedule=new schedule($this->credentials, $this->secure_db, $this->readonly_db);
			$schedule->add_monthly($this->select('type'), $this->select('name'), $this->select('report'), $this->select('division'), $this->select('date'));
			$this->add_detail($schedule);
		
//			$schedule->addData('starting', mktime(1, 1, 1, 1, 1, 2020));

//var_dump($schedule->data);
			$schedule->flush();
/*			$schedules=$schedule->previous_schedule();
			foreach ($schedules as $time)
				var_dump(date("Y-m-d", $time));*/
		}		
	}
	
	class new_schedule_weekly extends new_schedule
	{
		function __construct($credentials, $name, $action, $method, $return, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $name, $action, $method, $return, $secure_db, $readonly_db);
			
			$day_of_week=$this->get_day_of_week_list();
			
			$this->add_text_input('Nama', 'name', '', '', 64, ['required' => true]);
			
			$this->add_dropdown('Hari', 'day_of_week', $day_of_week, '', ['required' => true, 'size' => 7, 'multiple' => true]);
		}
		
		function stepZero()
		{
			$result=$this->form_open();
			
			$result.=$this->html_text('name').'<br><br>';
			
			$result.=$this->html_dropdown('day_of_week').'<br><br>';
			
			$result.=$this->html_dropdown('report').'<br><br>';
			$result.=$this->html_dropdown('division').'<br><br>';
			
			$result.=$this->html_submit('save');
			
			$result.=$this->html_hidden('type');
			$result.=$this->html_credential();
			
			$result.=$this->form_close();
			
			return $result;
		}
		
		function stepOne()
		{
			$result=$this->form_open();
			
			$result.=$this->html_hidden_display('name').'<br><br>';
			
			$result.=$this->html_hidden_display('day_of_week').'<br><br>';
			
			$result.=$this->html_hidden_display('report').'<br><br>';
			$result.=$this->html_hidden_display('division').'<br><br>';
			
			$report_type='stepOne_'.$this->select('report');
			$result.=$this->{$report_type}();
			
			$result.=$this->html_submit('save');
			
			$result.=$this->html_hidden('type');
			$result.=$this->html_credential();
			
			$result.=$this->form_close();
			
			return $result;
		}
		
		function stepTwo()
		{
			$result=$this->form_open();
			
			$result.=$this->html_hidden_display('name').'<br><br>';
			
			$result.=$this->html_hidden_display('day_of_week').'<br><br>';
			
			$result.=$this->html_hidden_display('report').'<br><br>';
			$result.=$this->html_hidden_display('division').'<br><br>';
			
//			$result.=$this->html_submit('save');
			$report_type='stepTwo_'.$this->select('report');
			$result.=$this->{$report_type}();
			
			$result.=$this->html_hidden('type');
			$result.=$this->html_credential();
			
			$result.=$this->form_close();
			
			$result.=$this->advanced_exit_form([], $this->final_input_step(), ['close' => 'Close']);
			
			return $result;
		}		
				
		function insert()
		{			
			$schedule=new schedule($this->credentials, $this->secure_db, $this->readonly_db);
			$schedule->add_weekly($this->select('type'), $this->select('name'), $this->select('report'), $this->select('division'), $this->select('day_of_week'));
			$this->add_detail($schedule);

//			$schedule->addData('starting', mktime(1, 1, 1, 7, 1, 2020));

			$schedule->flush();			
/*			$schedules=$schedule->previous_schedule();
			foreach ($schedules as $time)
				var_dump(date("Y-m-d", $time));*/				
		}
	}

	class new_schedule_daily extends new_schedule
	{
		function __construct($credentials, $name, $action, $method, $return, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $name, $action, $method, $return, $secure_db, $readonly_db);
			
			$hours=$this->get_hour_list();
			
			$this->add_text_input('Nama', 'name', '', '', 64, ['required' => true]);
			
			$this->add_dropdown('Jam', 'hours', $hours, '', ['required' => true, 'size' => 7, 'multiple' => true]);
		}
		
		function stepZero()
		{
			$result=$this->form_open();
			
			$result.=$this->html_text('name').'<br><br>';
			
			$result.=$this->html_dropdown('hours').'<br><br>';
			
			$result.=$this->html_dropdown('report').'<br><br>';
			$result.=$this->html_dropdown('division').'<br><br>';
			
			$result.=$this->html_submit('save');
			
			$result.=$this->html_hidden('type');
			$result.=$this->html_credential();
			
			$result.=$this->form_close();
			
			return $result;
		}
		
		function stepOne()
		{
			$result=$this->form_open();
			
			$result.=$this->html_hidden_display('name').'<br><br>';
			
			$result.=$this->html_hidden_display('hours').'<br><br>';
			
			$result.=$this->html_hidden_display('report').'<br><br>';
			$result.=$this->html_hidden_display('division').'<br><br>';
			
			$report_type='stepOne_'.$this->select('report');
			$result.=$this->{$report_type}();
			
			$result.=$this->html_submit('save');
			
			$result.=$this->html_hidden('type');
			$result.=$this->html_credential();
			
			$result.=$this->form_close();
			
			return $result;
		}
		
		function stepTwo()
		{
			$result=$this->form_open();
			
			$result.=$this->html_hidden_display('name').'<br><br>';
			
			$result.=$this->html_hidden_display('hours').'<br><br>';
			
			$result.=$this->html_hidden_display('report').'<br><br>';
			$result.=$this->html_hidden_display('division').'<br><br>';
			
			$report_type='stepTwo_'.$this->select('report');
			$result.=$this->{$report_type}();
			
//			$result.=$this->html_submit('save');
			
			$result.=$this->html_hidden('type');
			$result.=$this->html_credential();
			
			$result.=$this->form_close();
			
			$result.=$this->advanced_exit_form([], $this->final_input_step(), ['close' => 'Close']);			
			
			return $result;
		}		
		
		function insert()
		{			
			$schedule=new schedule($this->credentials, $this->secure_db, $this->readonly_db);
			$schedule->add_daily($this->select('type'), $this->select('name'), $this->select('report'), $this->select('division'), $this->select('hours'));
			$this->add_detail($schedule);
			
			//			$schedule->addData('starting', mktime(1, 1, 1, 7, 1, 2020));
//			var_dump($schedule->data);
			$schedule->flush();
			
			/*			$schedules=$schedule->previous_schedule();
			 foreach ($schedules as $time)
			 var_dump(date("Y-m-d", $time));*/
		}		
	}
	
	class schedule_result extends inputform_control
	{
		function __construct($credentials, $name, $action, $method, $return, $schedule, $date)
		{
			parent::__construct($credentials, $name, $action, $method, $return);
			
			$this->add_hidden_input('uniqueid', $schedule->select('uniqueid'));
			$this->add_mandatory_input('exec_id', $this->hash($schedule->select('uniqueid'), $date));
			$this->add_mandatory_input('date', $date);
			
			$this->set_dataholder('schedule', $schedule);
			$this->add_credential(...$credentials->available_credentials());
		}
				
		function name()
		{
			return $this->dataholder('name');
		}
		
		function schedule_date($date)
		{
			$result='';
			
			if ($this->dataholder('type')!='new_schedule_daily')
				$result=$this->date($date);
			else
				$result=$this->date($date, 'Y-m-d (H:i)');
			
			return $result;
		}	
		
		function schedule_result($execution)
		{
			$result=$execution;
			
			if ($this->dataholder('report')!='button_result')
				$result.=' '.$this->dataholder()->select('submit', 'caption');
			
			return $result;
		}
		
		function inline_input()
		{
			return '';
		}
				
		function execute($result)
		{
			$date=$this->select('date');
			$this->dataholder()->execute($date, \date::today('timestamp'), $result);
			$this->dataholder()->update();
		}
	}
	
	class button_result extends schedule_result
	{
		function __construct($credentials, $name, $action, $method, $return, $schedule, $date)
		{
			parent::__construct($credentials, $name, $action, $method, $return, $schedule, $date);
			
			$this->add_submit_button("", "save", $schedule->select('submit', 'caption'));
//			$this->add_credential($credentials->primary());
		}		
		
		function inline_input()
		{
			$result=$this->form_open();
			$result.=$this->html_hidden('uniqueid');
			//$result.=$this->html_hidden('date');
			$result.=$this->html_credential();
			$result.=$this->html_submit('save');
			$result.=$this->form_close();
			
			return $result;
		}
	
		function execute($result)
		{
			parent::execute($this->dataholder('submit')['caption']);
		}
	}
	
	class number_result extends schedule_result
	{
		function __construct($credentials, $name, $action, $method, $return, $schedule, $date)
		{
			parent::__construct($credentials, $name, $action, $method, $return, $schedule, $date);
			
			$this->add_submit_button("", "save", $schedule->select('submit', 'caption'));
			$this->add_number_input("", "number", $schedule->select('number', 'value'), 
				$schedule->select('number', 'min'), $schedule->select('number', 'max'), 
				['required' => true]);
//			$this->add_credential($credentials->primary());			
		}	
		
		function inline_input()
		{
			$result=$this->form_open();
			$result.=$this->html_hidden('uniqueid');
			//$result.=$this->html_hidden('date');
			$result.=$this->html_credential();
			$result.=$this->html_number('number');
			$result.=$this->html_submit('save');
			$result.=$this->form_close();
			
			return $result;
		}		
		
		function execute($result)
		{
			parent::execute($result['number']);
		}		
	}
	
	class text_result extends schedule_result
	{
		function __construct($credentials, $name, $action, $method, $return, $schedule, $date)
		{
			parent::__construct($credentials, $name, $action, $method, $return, $schedule, $date);
			
			$this->add_submit_button("", "save", $schedule->select('submit', 'caption'));
			$this->add_text_input("", "text", $schedule->select('text', 'value'), 
				$schedule->select('text', 'placeholder'), $schedule->select('text', 'maxlength'), 
				['required' => true]);
//			$this->add_credential($credentials->primary());			
		}
		
		function inline_input()
		{
			$result=$this->form_open();
			$result.=$this->html_hidden('uniqueid');
			//$result.=$this->html_hidden('date');
			$result.=$this->html_credential();
			$result.=$this->html_text('text');
			$result.=$this->html_submit('save');
			$result.=$this->form_close();
			
			return $result;
		}		
		
		function execute($result)
		{
			parent::execute($result['text']);
		}		
	}
	
	class schedule_form extends junction
	{		
		var $input_type;
		var $schedule_list;
		
		static function get_invocable_interface(credentials $credentials, settings $settings)
		{
			$result=[];
			
			$result[]=['f' => 'mode_switching_menu', 'p' => [$credentials->primary()]];
			
			return $result;
		}
		
		static function mode_switching_menu($credential)
		{
			$result=[];

			$result[credential]=$credential;
			$result[elements][]=[junction => 'schedule_form'];
			
			return $result;
		}
		
		function __construct($credentials, $input, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
			
			$this->input_type=isset($input['type']) ? $input['type'] : null;
			
			if ($type=schedule::schedule_type()[$input['type']])
			{								
				$this->form=new $type['value']($credentials, $input['type'], null, POST, null, $secure_db, $readonly_db);
				$this->form->set_junction($this);
				
				if ($this->form->input($input)==$this->form->final_input_step())
				{
					$this->form->insert();
				}
			}
			else
			{
				$this->schedule_list=$this->get_global('lib_schedule')->get_list('schedule_list')->db_fill([[f => 'is_relevant', p => []]]);
				
//				$this->schedule_list=$this->get_global('data_module')->x_list_fill('schedule_list', cUsernameChkSec, [['f' => 'is_relevant', 'p' => []]]);
				$this->form=new new_schedule($credentials, 'schedule', null, POST, null, $secure_db, $readonly_db);
				$this->form->set_junction($this);
				
				$this->form->input($input);
			}
		}		
		
		function form_title($form_title='')
		{
			if ($type=schedule::schedule_type()[$this->input_type])
				return $type['display'];
			else
				return parent::form_title('New Schedule');
		}
	}
	
	class schedule_view extends junction
	/*
	 * Executing/Running schedule is done here
	 */
	{
		var $executed_date;
		var $input;
		
		function form_title($form_title='')
		{
			return parent::form_title("Daftar Pekerjaan Rutin");
		}
		
		function __construct($credentials, $input, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
			
			//later add credential checker so only people with correct credential is added
//			$this->schedule_list=$this->get_global('data_module')->x_list_fill('schedule_list', cUsernameChkSec, [['f' => 'is_relevant', 'p' => []]]);
						
			$this->schedule_list=$this->get_global('lib_schedule')->get_list('schedule_list')->db_fill([[f => 'is_relevant', p => []]]);
			
			$this->input=$input;
/*			$schedule=$this->schedule_list->db_select($input['uniqueid']);
			$schedule->execute($input['date'], $this->today('timestamp'), )*/
		}
		
		function toHTML()
		{
			$result='';
			$result.=$this->open_html_table([], 'No', 'Nama', 'Tanggal', 'Hasil');
			$i=0;
			foreach ($this->schedule_list as $schedule)
			{
				$schedule->previous_schedule();
				$unexecuted=$schedule->unexecuted();
				sort($unexecuted);
//				var_dump($unexecuted);echo("<br><br>");
//				if ($schedule->get('submit', 'caption')=='') $schedule->dispose();
										
				foreach ($unexecuted as $date)
				{
					if ($date>$this->yesterday('timestamp', 90))
					{										
						$input_class=$schedule->get_report_class();
						$execution_form=new $input_class($this->credentials, $schedule->select('uniqueid'), null, POST, null, $schedule, $date);
						$execution_form->set_junction($this);
						
						if ((isset($this->input['exec_id'])) && ($this->input['exec_id']==$execution_form->select('exec_id')))
						{
							$execution_form->execute($this->input);
	//						var_dump($execution_form->dataholder()->data);
						}
						else
						{
							$i++;
							$result.='<tr>'.$execution_form->toInlineHTML([['mode' => '_echo', 'value' => $i],
								['mode' => 'name'], ['mode' => 'schedule_date', 'value' => $date], ['mode' => 'inline_input']]).'</tr>';
						}						
					}
				}
				
//				$schedule->dispose();
				//new button result class
				//make it as inline HTML
				//combine it to table
			}
			$result.=$this->close_html_table();
			return $result;
		}
	}
	
	class schedule_list extends junction
	{		
		function form_title($form_title='')
		{
			return parent::form_title("Laporan Pekerjaan Rutin");
		}
				
		function __construct($credentials, $input, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
			
			$this->get_global('lib_credential')->get_list('employee_list')->db_fill();
			//later add credential checker so only people with correct credential is added
			$this->schedule_list=$this->get_global('lib_schedule')->get_list('schedule_list')->db_fill([[f => 'is_relevant', p => []]]);
			
			$this->form=$this->miniform_month($input, array_keys($this->credentials->system_credentials_list));
		}
		
		function toHTML()
		{
			$result='';
			$result.=$this->open_html_table([], 'No', 'Nama', 'Jadwal', 'Pelaksana', 'Tgl Jalan', 'Hasil');
			$i=0;
			foreach ($this->schedule_list as $schedule)
			{
				$schedule->previous_schedule();
				$executed=$schedule->execution_list();
				
				ksort($executed);

				foreach ($executed as $date => $e_info)
				{
					if (($this->begin_of_month($this->form->select('target_month'), 'timestamp', 'timestamp')<=$date) &&
						($date<=$this->end_of_month($this->form->select('target_month'), 'timestamp', 'timestamp')))
					{
						$i++;
						$input_class=$schedule->get_report_class();
						$user=$this->get_global('employee_list')->select($e_info['user'])->getName();
						$execution_form=new $input_class($this->credentials, $schedule->select('uniqueid'), 
							null, POST, null, $schedule, $date);
						$result.='<tr>'.$execution_form->toInlineHTML([['mode' => '_echo', 'value' => $i],
							['mode' => 'name'], ['mode' => 'schedule_date', 'value' => $date], 
							['mode' => '_echo', 'value' => $user],
							['mode' => 'schedule_date', 'value' => $e_info['time']],
							['mode' => 'schedule_result', 'value' => $e_info['result']]
						]).'</tr>';
					}
				}
				
				//				$schedule->dispose();
				//new button result class
				//make it as inline HTML
				//combine it to table
			}
			$result.=$this->close_html_table().'<br>';
			
			$result.=$this->form->toStringFormat();
			
			return $result;
		}
	}	
?>