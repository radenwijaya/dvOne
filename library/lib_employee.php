<?php	
	include_once("lib_baseclass.php");
	
	trait lib_employee
	{
		function f_employee($username)
		{
			if ($username)
			{				
				if ($e=$this->get_global('employee_list')->db_select($username))
					$result=$e->getName();
				else
					$result=$username;
			}
			else				
				$result='-';
			
			return $result;
		}
		
		function employee_array($division=[])
		{
//			$employee=$this->get_global('employee_list')->get_sub_list(['is_credential' => cChauffeurChkSec])->asDropdownArray(['value' => 'getIndex', 'display' => 'getName'],
//				'display');

			$employee=$this->get_global('employee_list')->asDropdownArray(['value' => 'getIndex', 'display' => 'getName'], 'display');
			
			return $employee;
		}
		
		function load_employees()
		{
			$this->get_global('employee_list')->db_fill();
		}
	}
	
	class employee extends record
	{
		use faceapi_scandata;
		
	    var $seal;
	    var $self_credential;
	    
	    var $username;
	    
	    function __construct($credentials, $secure_db, $readonly_db, $data, $id)
	    {
	        parent::__construct($credentials, $credentials->primary(), $secure_db, $readonly_db,
	            $data, ctableUsername, $id, 'username');
	        
	        $this->makeArray('fines');
	        
	        $this->make_temporal('shift', 'salary', 'payment_group', 'bank_account', 'union', 'salary_ded2', 
	        	'misc1_privilege', 'misc2_privilege', 'misc1_privilege', 'misc4_privilege', 'union', 
	        	'salary_bonus1', 'salary_bonus2', 'salary_bonus3', 'workdays', 'perm_p_holiday');	        	        
	    }
	    
	    function load($uniqueid='', $data=[])
	    {
	        parent::load($uniqueid, $data);
	        
	        $this->addData($this->id_field, $this->{$this->id_field});
	        
/*	        $this->p_holiday_update();
	        $this->update();*/
	    }
	    	    
	    function load_seal($seal_id)
	    {
	    	$seal=new qr_container($this->credentials, $this->secure_db, $this->readonly_db, 'tableQRCheck', ['i' => $seal_id]);

	    	return $seal;
	    }
	    
	    function load_signature($seal)
	    {
	    	return $seal->html_qrgen();
	    }
	    
	    function valid_seal(record $dataholder, qr_container $seal)
	    {	    
	    	return (hash_equals($dataholder->select('_seal', $seal->uniqueid) ?? '', $seal->checksum()) ??'');
	    }
	    
	    function prepare_seal($seal_id='')
	    /*
	     * Always prepare seal on insert so the seal data is stored on dataholder.
	     * So it will syncrhonise during final display step. 
	     * 
	     * However contents must be added on final step so it can be stored for
	     * reviews later on.
	     * 
	     * Call prepare_seal on final step or other occassion to show on later time. 
	     */
	    {
        	$this->seal=new qr_container($this->credentials(), $this->secure_db, $this->readonly_db, array(), array());

	        return $this->seal->uniqueid;
	    }
	    
	    function sign($contents, $date=null)
	    {
	        $this->seal->set('page1', $contents);
	        $this->seal->set('sign_date', $date ?? \date::today('timestamp'));
	        $this->seal->set('signer', $this->credentials->username);
	        
	        return $this->seal->html_qrgen($this, $date);
	    }
	    
	    function seal(record $dataholder)
	    /*
	     * This method is used to save the signature
	     */
	    {
	    	if (!$this->seal->exists)
	    	{	    		
	    		$this->seal->flush();
	    		
//	    		$dataholder->makeArray('_seal');
	    		$dataholder->addData('_seal', $this->seal->checksum(), $this->seal->uniqueid);
	    		
	    		$dataholder->update();
	    	}
	    }
	    
	    function credentials()
	    /*
	     * This method will return the credential of this employee.
	     * the variable this->credential will still record who called this employee
	     */
	    {
	        if ($this->credentials->username==$this->select('username'))
	            $this->self_credential=$this->credentials;
            else
                if (!$this->self_credential)
	               $this->self_credential=new credentials($this->secure_db, $this->select('username'));
	                    
            return $this->self_credential;
	    }
	    
	    function is_corrupt()
	    {
	        $credentials=$this->credentials();
	        return ((!$credentials->input_session['next_session']) || (!$credentials->input_session['this_session']));
	    }
	    
	    function get_yearly_dayoff()
	    {
	        $dayoff=$this->select('dayoff_yearly');
	        if (!$dayoff) $dayoff=12;
	        
	        return $dayoff;
	    }
	    
	    function get_taken_dayoffs($date)
	    //this function should be moved to object employee
	    //this function is extremely fast
	    {
	        $dayoff=['public' => 0, 'private' => 0];
	        
	        /*			$year=date('Y', $date);
	         $year_begin="$year-01-01";
	         $year_end="$year-12-31";
	         $iterator=new date_iterator($year_begin, $year_end);
	         
	         $dayoff=array('public' => 0, 'private' => 0);//change with object employee
	         foreach($iterator as $timestamp => $date)
	         {
	         $day=new day_data($this->credentials, $this->credentials->anycredential(),
	         $this->secure_db, $this->readonly_db, $timestamp);
	         
	         if ($day->day_off($this->credentials->username))
	         {
	         $dayoff['private']++; //ambil off sendiri, mau cuti bersama atau kaga sebodo amat
	         }
	         else if ($day->public_dayoff()) //cuti bersama
	         {
	         $dayoff['public']++;
	         
	         //if (!$day->day_off($this->credentials->username))
	         //$dayoff--; //special case ketika dia masuk dihari cuti bersama
	         }
	         }*/
	         $day_list=$this->get_global('day_list');
	         foreach ($day_list as $day)
	         {
	             if ($day->day_off($this->select('username')))
	             {
	                 $dayoff['private']++; //ambil off sendiri, mau cuti bersama atau kaga sebodo amat
	             }
	             else if ($day->public_dayoff()) //cuti bersama
	             {
	                 $dayoff['public']++;
	             }
	         }
	         
	         return $dayoff;
	    }
	    
	    function is_nameless()
	    {
	    	return (strlen($this->get('firstname') ?? '')==0) && (strlen($this->get('lastname') ?? '')==0);
	    }
	    
	    function getName()
	    {
	    	$first_name=$this->get('firstname');
	    	$last_name=$this->get('lastname'); 
	    		
	    	$full_name=[];
	    	if ($first_name)
	    		$full_name[]=$first_name;
	    	
	    	if ($last_name)
	    		$full_name[]=$last_name;
	    		
	    	return (empty($full_name)) ? $this->get('username') : implode(' ', $full_name);
	    }
	    
	    function getHourlyCost($day=null)
	    /*
	     * This method will run cost of working per hour of a person 
	     */
	    {	    	
	    	$bonus=$this->get_skill_bonus($day)+$this->t_select($day, 'salary_bonus1');
	    	return ($bonus+$this->t_select($day, 'salary') ?? 0) / 173;
	    }
	    
	    function daily_working_costs($day=null)
	    /*
	     * How much this person costs one day
	     */
	    {
	    	if ($day)
	    		$work_hour=$this->get_global('settings')->select('variable_shift_length', $this->get_workdays(), $this->workday_number($day->uniqueid));
	    	else 
	    		$work_hour=$this->get_global('settings')->select('variable_shift_length', $this->get_workdays(), 1);
	    	
	    	return $work_hour*$this->getHourlyCost($day);
	    }
	    
	    function setName($fullname)
	    {
	        $rev_name=strrev($fullname);
	        $name=explode(" ", $rev_name, 2);
	        
	        $this->addData('firstname', strrev($name[1]));
	        $this->addData('lastname', strrev($name[0]));
	    }
	    
	    function get_remaining_dayoff($date)
	    {
	        $dayoffs=$this->get_taken_dayoffs($date);
	        $public_dayoff=$dayoffs['public'];
	        $private_dayoff=$dayoffs['private'];
	        $total_dayoff=$public_dayoff+$private_dayoff;
	        $allowed_dayoff=$this->get_yearly_dayoff();
	        $remaining_dayoff=$allowed_dayoff-$total_dayoff;
	        
	        return $remaining_dayoff;
	    }
	    
	    function has_misc1_privilege()
	    /*
	     * H1
	     */
	    {
	        return $this->select('misc1_privilege')=='on';
	    }
	    
	    function no_misc1_privilege()
	    /*
	     * H1
	     */
	    {
	    	return $this->select('misc1_privilege')!='on';
	    }
	    
	    function has_maternity_privilege($timestamp=null)
	    /*
	     * Has an an currently active maternity leave period
	     */
	    {
	    	$mp_count=$this->select('maternity', 'count') ?? 0;
	    	$result=false;
	    	
	    	if ($mp_count>0)
	    	{
	    		$timestamp=$timestamp ?? \date::today('timestamp');
	    		
	    		$end=$this->select('maternity', 'end_'.$mp_count);
	    		
	    		$result=$end>=$timestamp;
	    	}
	    	
	    	return $result;
	    }
	    
	    function current_maternity($status)
	    {
	    	$mp_count=$this->select('maternity', 'count') ?? 0;
	    	$result=null;
	    	
	    	if ($mp_count>0)
	    	{
/*	    		use this when eclipse support php match statement for safer code
 * 				$result=match($status)
	    		{
	    			'begin' => $this->select('maternity', 'begin_'.$mp_count),
	    			'end' => $this->select('maternity', 'end_'.$mp_count),
	    		};*/
	    		$result=$this->select('maternity', $status.'_'.$mp_count);
	    	}
	    	
	    	return $result;
	    }
	    
	    function on_maternity_privilege($timestamp=null)
	    /*
	     * Currently in an active maternity leave period
	     */
	    {
	    	$mp_count=$this->select('maternity', 'count') ?? 0;
	    	$result=false;
	    	
	    	if ($mp_count>0)
	    	{
	    		$timestamp=$timestamp ?? \date::today('timestamp');
	    		
	    		$begin=$this->select('maternity', 'begin_'.$mp_count);	    		
	    		$end=$this->select('maternity', 'end_'.$mp_count);
	    		
	    		$result=($begin<=$timestamp) && ($timestamp<=$end);
	    	}
	    	
	    	return $result;
	    }
	    
	    function maternity_privilege_end($mp_count=null)
	    /*
	     * Get the end of the maternity privilege
	     */
	    {
	    	$result=null;
	    	if ($this->has_maternity_privilege())
	    	{
	    		$mp_count=$mp_count ?? $this->select('maternity', 'count');
	    		$result=$this->select('maternity', 'end_'.$mp_count);	    		
	    	}
	    	
	    	return $result;
	    }
	    
	    function no_maternity_privilege($timestamp=null)
	    {
	    	return ((!$this->has_maternity_privilege($timestamp)) && ($this->no_misc1_privilege()));
	    }
	    
	    function create_maternity_privilege($timestamp, $length)
	    /*
	     * Creating a new maternity privilege
	     */
	    {
	    	$mp_count=($this->select('maternity_privilege', 'count') ?? 0) + 1;

	    	$end=$this->next_days($length, $timestamp);
	    	$this->addData('maternity', $timestamp, 'begin_'.$mp_count);
	    	$this->addData('maternity', $end, 'end_'.$mp_count);
	    	$this->addData('maternity', $mp_count, 'count');
	    }
	    
	    function cancel_maternity_privilege()
	    {
	    	$mp_count=$this->select('maternity', 'count') ?? 0;
	    	
	    	if ($mp_count>0)
	    		$this->addData('maternity', $this->end_of_day(\date::today('timestamp'), 'timestamp'), 'end_'.$mp_count);
	    }
	    
	    function has_misc2_privilege()
	    /*
	     * Ekstra Lembur
	     */
	    {
	        return $this->select('misc2_privilege')=='on';
	    }
	    
	    function has_misc3_privilege($date=null)
	    /*
	     * Friday Prayers
	     */
	    {
	        if ($date)
	        {
	            $result=(($this->select('misc3_privilege')=='on') && ($this->date($date, 'N')==5) && ($this->get_shift_sequence($date)==0));
	        }
	        else
	            $result=($this->select('misc3_privilege')=='on');
	            //				var_dump("misc3", $this->select('misc3_privilege'));
            return $result;
	    }
	    	    
	    function select($name, ...$indices)
	    {
	        $result='';
	        switch ($name)
	        {
	            case 'salary_history': case 'salary_overtime_history': case 'salary_visit_history':
	            	if (empty($indices)) $index=\date::today();
	                
	                $salary_list=parent::select($name);
	                if ($salary_list)
	                {
	                    if ($salary_list[$index])
	                    {
	                        $result=$salary_list[$index];
	                    }
	                    else
	                    {
	                        $dates=array_keys($salary_list);
	                        sort($dates);
	                        
	                        $count=count($dates);
	                        $i=0;
	                        while ($i<$count)
	                        {
	                            $difference=$this->date_string_difference($dates[$i], $index[0], false);
	                            //echo("$dates[$i] $index $difference<br>");
	                            if ($difference<=0)
	                            {
	                                $result=$salary_list[$dates[$i]];
	                                break;
	                            }
	                            $i++;
	                        }
	                    }
	                }
	                
	                if (!$result)
	                {
	                    $name=str_ireplace('_history', '', $name);
	                    
	                    $result=parent::select($name);
	                }
	                break;
	            default:
	                $result=parent::select($name, ...$indices);
	        }
	        
	        return $result;
	    }
	    	    
	    function is_paycheck_made($target_date)
	    {
	        $paycheck_dates=$this->select('paycheck_date');
	        
	        $search=array_search($target_date, $paycheck_dates ?? []);
	        
	        return (($search!==null) && ($search!==false));
	    }
	    
	    function is_unpaid($target_date)
	    {
	        return !$this->is_paycheck_made($target_date);
	    }
	    
	    function short_vanity()
	    {
	        return $this->select('username').' '.$this->getName();
	    }
	    
	    function vanity_name()
	    {
	        return 'NIK: '.$this->select('username').' Nama: '.$this->getName();
	    }
	    
	    function get_shift()
	    {
	        return ($this->select('shift')) ?: "0";
	    }
	    
	    function get_divisions($show_shift=false)
	    /*
	     * Getting user code division name of an employee
	     * Warning: this is a slow function, as it always refresh from database
	     * 
	     * Do not use in loops
	     */
	    {	    
	    	$result=[];
	    		    	
	        $shift=$show_shift ? $this->get_shift() : null;
	        	        
	        foreach ($this->credentials->system_credentials_list as ['table' => $table, 'name' => $division])
	        {
	        	if (!$this->secure_db->is_table_cached($table))
	        		$this->secure_db->table_cache($table, 'username', ['*']);
	        	$table=$this->secure_db->get_table_cache($table);
	        	
	        	if ($this->credentials->check_other_credentials($table, $this->{$this->id_field}))
	                $result[]=$division . ($show_shift ? '_'.$shift : '');
	        }
	        
	        return $result;
	    }
	    	    
	    function get_divisions_name()
	    /*
	     * Getting user readable division name of an employee
	     */
	    {
	        $result=[];
	        $divisions=$this->get_divisions();
	        
	        foreach ($divisions as $division)
	        {
	            if ($this->credentials->extensions[$division])
	                $result[]=$this->credentials->extensions[$division]->get_credential_name($division);
	        }
	        
	        return implode('&nbsp;', $result);
	    }
	    
	    function is_ondivision(...$divisions)
	    {
	    	foreach ($divisions as $division)
	    	{
	            $table=$this->credentials->system_credentials_list[$division]['table'];
	            
	            if ($result=$this->credentials->check_other_credentials($table, $this->{$this->id_field}))
					break;
	    	}
	    	
	        return $result;
	    }
	    	    
	    function remove_warning($id)
	    {
	        $this->remove('warning_date', $id);
	        $this->remove('warning_cause', $id);
	        $this->remove('warning_auth', $id);
	        $this->remove('warning_no', $id);
	    }
	    
	    function add_warning($date, $cause, $auth)
	    {
	        $id=$this->random_uniqueid(4);
	        $this->addData('warning_date', $date, $id);
	        $this->addData('warning_cause', $cause, $id);
	        $this->addData('warning_auth', $auth, $id);
	        $this->addData('warning_no', $this->count_warning(), $id);
	    }
	    
	    function has_warnings($date=null)
	    {
	        return $this->count_warning($date)>0;
	    }
	    
	    function count_offwork($leave=['sickleave', 'skipwork', 'approved_leave', 'special_leave', 'medical_certificate', 'approved_day_off', 'misc1leave'])
	    {	    	
//	    	var_dump($this->data);echo("<br><br>");
	    	$count=[];
	    	foreach ($leave as $name)
	    	{
	    		$count[$name]=$this->count($name);
	    	}

	    	return array_sum($count);
	    }
	    
	    function has_warned_in($date)
	    /*
	     * Returns all the wanring that this particular employee has been warned in this input month
	     */
	    {
	    	$result=[];
	    	$start=$this->first_of_month($date, 'timestamp');
	    	$end=$this->end_of_month($date, 'timestamp');
	    	foreach ($this->select('warning_date') as $id => $w_date)
	    	{
	    		if (($start<=$w_date) && ($w_date<=$end) && (!$result[$id]))
	    			$result[$id]=['date' => $w_date, 'cause' => $this->select('warning_cause', $id), 'auth' => $this->select('warning_auth', $id), 'no' => $this->select('warning_no', $id)];
	    	}
	    	
	    	return empty($result) ? null : $result;
	    }
	    
	    function count_warning($date=null)
	    {
	    	$date=$date ?: \date::today('timestamp');
	        return count($this->active_warning($date));
	    }
	    	    	    
	    function active_warning($date)
	    {
	    	/*
	    	 * New Method
	    	 */
	    	if (is_array($this->select('warning_date')))
	    	{	    		 
		    	$latest=$date;
		    	
		    	$wd=$this->select('warning_date');
		    	arsort($wd);
		    	 
		    	$result=[];
		    	foreach ($wd as $id => $w_date)
		    	{		    		
		    		if ($w_date>$this->days_ago($latest, 'timestamp', warning_expirity))
		    		{		    				    					    			
		    			if ($latest>$w_date)
		    				$latest=$w_date;
		    				
		    			$result[$id]=['date' => $w_date, 'cause' => $this->select('warning_cause', $id), 
		    				'auth' => $this->select('warning_auth', $id), 'no' => $this->select('warning_no', $id)];		    			
		    		}
		    	}

	    	}
	    	
	    	return $result ?? [];
	    	
/*		Old Method
 	        $result=[];
	        $days_ago=$this->days_ago($date, 'timestamp', warning_expirity);
	        
	        foreach ($this->select('warning_date') as $x)
	            foreach ($this->select('warning_date') as $id => $w_date)
	            {
	                if (($w_date>=$days_ago) && (!$result[$id]))
	                {
	                    $result[$id]=['date' => $w_date, 'cause' => $this->select('warning_cause', $id), 'auth' => $this->select('warning_auth', $id), 'no' => $this->select('warning_no', $id)];
	                    $days_ago=$this->days_ago($w_date, 'timestamp', warning_expirity);
	                }
	            }
	        
	        return $result;*/
	    }
	    
	    function past_warning($date)
	    {
	    	if (is_array($this->select('warning_date')))
	    	{
	    		$latest=$date;
		    	
		    	$wd=$this->select('warning_date');
		    	arsort($wd);
		    	
		    	foreach ($wd as $id => $w_date)
		    	{
		    		if ($w_date>$this->days_ago($latest, 'timestamp', warning_expirity))
		    		{
		    			if ($latest>$w_date)
		    				$latest=$w_date;
		    				
		    			unset($wd[$id]);
		    		}
		    	}
		    	
		    	$result=[];
		    	foreach ($wd as $id => $w_date)
		    		$result[$id]=['date' => $w_date, 'cause' => $this->select('warning_cause', $id), 'auth' => $this->select('warning_auth', $id), 'no' => $this->select('warning_no', $id)];
		    	
/*		    	if (count($result)>=3)
		    		var_dump($this->select('username'));*/
	    	}
//	    	var_dump($wd);
	    	return $result ?? [];
	    	
/*		Old Method
	        $result=[];
	        $days_ago=$this->days_ago($date, 'timestamp', warning_expirity);
	        foreach ($this->select('warning_date') as $x)
	            foreach ($this->select('warning_date') as $id => $w_date)
	            {
	                if (($w_date<$days_ago) && (!$result[$id]))
	                {
	                    $result[$id]=['date' => $w_date, 'cause' => $this->select('warning_cause', $id), 'auth' => $this->select('warning_auth', $id), 'no' => $this->select('warning_no', $id)];
	                    $days_ago=$this->days_ago($w_date, 'timestamp', warning_expirity);
	                }
	            }
	        
	        return $result;*/
	    }
	    
	    function to_show_attendance()
	    {
	        return ($this->select('overtime') || $this->select('overwork') || $this->select('dayoff') ||
	            $this->select('sickleave') || $this->select('leave') || $this->select('skipwork') ||
	            $this->select('medical_certificate') || $this->select('special_leave'));
	    }
	    
	    function is_onshift($shift)
	    {
	        return $this->select('shift')===$shift;
	    }
	    
	    function get_shift_data($day)
	    {	   	        
	    	return ['begin' => $this->get_startwork($day), 'end' => $this->get_endwork($day)];
	    }
	    
	    function get_shift_sequence($date)
	    {
	        if (($this->select('shift')) && ($facility=$this->get_facility()))
	        {
	            $shift=$this->get_global('shift');
	            
	            $shift_arrangement=$shift->get_shift_arrangement($date,	$facility);
	            
	            $result=array_search($this->select('shift'), $shift_arrangement);
	        }
	        else
	            $result=0;
	            
	        return $result;
	    }
	    
	    function workday_number($date): int
	    /*
	     * Returns the work day number of a particular person
	     * 
	     * Personal holiday must be no 0, which is the last personal holiday in a week, e.g. when will the week
	     * change. 
	     */
	    {
	    	$phs=[];
	    	for ($i=0; $i<3; $i++)
	    		$phs[]=$this->get_perm_personal_holiday($date, $i);
	    	
	    	$d=$this->date($date, 'N');
	    	
	    	$wd=0;
	    	/*
	    	 * Assume workday is always 0, e.g. always holiday
	    	 */
	    	
	    	if (!in_array($d, $phs))
	    	/*
	    	 * If that day is not in personal holiday list
	    	 * return day of week adjusted to personal holiday 0
	    	 */
	    		$wd=$d-$this->get_perm_personal_holiday($date, 0);
		    	
	        return ($wd < 0) ? $wd+7 : $wd;
	    }
	    
	    function get_next_personal_holiday(?int $date)
	    {
	        if (!$date)
	        	$date=\date::today('timestamp');
	            //			var_dump($date);
	            
	        $result=$this->change_day(7-$this->workday_number($date), $date, 'timestamp');
	            
	        return $result;
	    }
	    
	    function adjust_working_time($date, $work_time)
	    /*
	     * obsolete, and integrated to get breaklength
	     */
	    {
	        return ($this->has_misc3_privilege($date)) ? $work_time-0.5 : $work_time;
	    }
	    
	    function daily_attendance_summary($username, $weekchange, $day, $date)
	    {
	    	$total_worktime=$this->select('wt_'.$weekchange) ?? [];
	    	//					var_dump(count($total_worktime));
	    	if (count($total_worktime)>=6)
	    	{
	    		if ($this->get_shift()==='0')
	    			$std_week_worktime=array_sum($this->get_global('settings')->select('variable_nonshift_length', $this->get_workdays($date)));
	    		else
	    			$std_week_worktime=array_sum($this->get_global('settings')->select('variable_shift_length', $this->get_workdays($date)));
	    				//						else
	    				//							$std_worktime=
	    				
	    		$work_overtime=array_sum($total_worktime)-$std_week_worktime;
	    				
	    		if (($work_overtime>0) && ($this->get_shift()!=='0') && ($weekchange==$date))
	    					/*
	    					 * Only for shift worker, if working overtime then
	    					 * mark shift overtime only the end of the working week
	    					 */
	    			$day->mark_shift_overtime($username, $work_overtime);
	    	}	    		    	 	 
	    }
	    
	    function set_attendance(day_data $day, attendance $attendance, $date)
	    /*
	     * This method is used too set attendance to a day_data frrom attendance class
	     * so it can be displayed on the attendance table
	     */
	    {
	    	$data=$attendance->select($this->username);
	    	
	    	if (!$this->on_maternity_privilege($date))
	        {
	        	$username=$this->username;
	
		        $day->add_attendance_data($username);
		        
		        if ($this->get_global('settings')->select('free_workday', 1))	        
		        	if ($day->approved_day_off($username))
		        		$this->set_free_workday($day);	        	
		        	
		        if ($this->get_global('settings')->select('free_workday', 18))
	       			if (($day->public_holiday_status()=='holiday'))
	       				$this->set_free_workday($day);	        				
		        
//		        if (($attendance->select($username, 'att_status')=='True') || (is_null($attendance->select($username, 'att_status'))))
				if (!$attendance->is_present($username))
		        {
		            $day->mark_absent($username);
		            
		            $weekchange=$this->this_weekday($date, $this->get_global('settings')->select('week_change_day')-1);
		            
		            $this->daily_attendance_summary($username, $weekchange, $day, $date);
		        }
		        else
		        {
		            if (!$attendance->is_discipline($username))
		                $day->mark_indiscipline($username);
		                
		            if ($manual=$attendance->is_manual_attendance($username))
		            	$day->mark_manual_attendance($username, $manual);
		                
		            if ($day->overtime($username))
	                    $day->mark_overtime($username, $attendance);
		                    
	                if (false)
		                    /*
		                     * false to use new calcuation method
		                     */
		            {
	                    if ($attendance->working_time($username)>misc2_overtime_minimum_worktime)
	                    {
		               //					if ($this->date($date)=='2021-05-28')
	/*	               var_dump($this->has_misc2_privilege(), 
		               	$this->has_misc3_privilege($date), 
		               	$this->is_personal_holiday($this->change_day(1, $day->today(), 'N'), $day->uniqueid));*/
	                        if (!$this->has_misc3_privilege($date))
	                        {
		                    	if (($this->workday_number($day->uniqueid)==6) && ($this->get_shift()!='0'))
	                            {                            	
	                                $day->mark_shift_overtime($username, 2);
	                            }
		                                    
	                            if ($this->has_misc2_privilege())
	                                $day->mark_misc2_overtime($username);
		                    }
		                    else
		                    {	                    	
	                            if (($this->workday_number($day->uniqueid)==6) && ($this->get_shift()!='0'))                            	
		                            $day->mark_shift_overtime($username, 1.5);
		                    }
						}
					}
		            else
		            {
		            	if (!$this->is_personal_holiday($this->date($day->uniqueid, 'N'), $day->uniqueid))
		                	$std_worktime=$attendance->actual_working_time($this, $day, 'h');
		            	else 
		            		$std_worktime=0;
	//	                	var_dump($this->date($day->uniqueid, 'd N'), $std_worktime);
						$weekchange=$this->this_weekday($date, $this->get_global('settings')->select('week_change_day')-1);
	//					var_dump($this->date($weekchange));
						$this->addData('wt_'.$weekchange, $std_worktime, $date);
		                            
						if (!$this->has_misc3_privilege($date))
							if ($this->has_misc2_privilege())
								$day->mark_misc2_overtime($username);
		                
		                                    //					if ($this->workday_number($date)==6) // lemburan hilang, jika tukar libur
		                                    //					if ($this->is_personal_holiday($this->change_day(1, $this->date($day->uniqueid), 'N'), $day->uniqueid))
		                                    //tapi jika tidak tukar libur maka bisa kasih lemburan
	
		                                    //					var_dump(count($total_worktime));
						$this->daily_attendance_summary($username, $weekchange, $day, $date);
						
						if (($this->workday_number($day->uniqueid)==6) && ($this->t_select($day->uniqueid, 'misc4_privilege')))
						{
							$this->addData('_misc4_privilege', 1, $day->uniqueid);
						}
					}
		        }
		        
		        $this->addData('attendance', $day, $date);		        
	        }
	        
	        return $data;
	    }
	    
	    function set_free_workday(day_data $day)
	    /*
	     * Assume working at that certain dayu according to the work day number
	     * this will add std worktime
	     */
	    {
	        if (!$this->is_personal_holiday($day->today('N'), $day->uniqueid))
	        {	        	
	        	if ($this->get_shift()==='0')
	        		$std_worktime=$this->get_global('settings')->select('variable_nonshift_length', $this->get_workdays($day->uniqueid),
	        			$day->today('N'));
	        	else
	        		$std_worktime=$this->get_global('settings')->select('variable_shift_length', $this->get_workdays($day->uniqueid),
		        		$this->workday_number($day->uniqueid));
	            
	        	$weekchange=$this->this_weekday((int) $day->uniqueid, $this->get_global('settings')->select('week_change_day')-1);
	        	
	        	$this->addData('wt_'.$weekchange, $std_worktime, $day->uniqueid);
	        }
	    }
	    
	    function get_attendance(day_data $day, $short=true)
	    /*
	     * This will used to prepare values for filling detailed attendance data
	     * plus will add attendance status to the user
	     */
	    {
	    	$returns=[];
	    	$is_short=$short ? 'short' : 'long';
	    	
	    	switch (true)
	    	{
	    		case $day->public_holiday_status():
	    		{
		    		/*
		    		 * National holiday
		    		 */
		    		$result=$returns[2]=='off' ?
			    		(attendance::conclusion_map([20])[20][$is_short]) :
			    		(attendance::conclusion_map([21])[21][$is_short]);
		    		break;
	    		}
	    		case $this->is_personal_holiday($day->as_day_of_week(), $day->uniqueid):
	    		{
	    			$result=attendance::conclusion_map([19])[19][$is_short];
	    			break;	    			
	    		}
	    		case $this->on_maternity_privilege($day->uniqueid):
	    		{
	    			if (!is_array($this->select('att_array')))
	    				$this->makeArray('att_18');
	    				
	    			$result=attendance::conclusion_map([18])[18][$is_short];
	    			$this->addData('att_18', $day->uniqueid);	    	
	    			break;
	    		}
	    		case $day->forcedleave($this->username):
	    		{
	    			$result=attendance::conclusion_map([23])[23][$is_short];
	    			$this->addData('att_23', $day->uniqueid);
	    			break;
	    		}
	    		default:
	    		{
	    			$att_status=$day->attendance_status($this);
	    			
	    			foreach ($att_status as $status)
	    			{
	    				$this->makeArray('att_'.$status);
	    				$this->addData('att_'.$status, $day->uniqueid);
	    			}
	    			
	    			$result=$day->f_attendance_status($att_status, $this, $short);
	    			if ($day->select('manual_'.$this->select('username')))
	    			{
	    				if ($short)
	    					$result.='*';
	    				else
	    				{
	    					$timing=array_key_first($day->select('manual_'.$this->select('username')));
	    					$or=$day->get_manual_overrider($this->select('username'), $timing);
	    						
	    					$name=$this->get_global('employee_list')->db_select($or)->getName();
	    					$result.='* ('.$name.')';
	    				}
	    			}
	    			break;
	    		}
	    	}
				            
			return $result;
	    }
	    
	    function get_discipline(day_data $day, $required)
	    {
	        if ($this->is_personal_holiday($this->date($day->uniqueid, 'N'), $day->uniqueid))
	            $result=[];
			else
	        {
	        	if ($day->public_holiday_status())
	            	$result=[];
				else
	                $result=$day->attendance_status($this);
	        }
	            
	        return array_search($required, $result)!==false;
	    }
	    	    
	    function get_attendance_conclusion($to_conclude, $days)
	    /*
	     * This method is used to calculate attendance data according to_conclude 
	     * for example, how many days that employee overtime, skipwork, less work, etc
	     * within the time frame, so each and everyday must be unique
	     *   
	     */
	    {
	    	$result=[];
	    	foreach ($to_conclude as $att_status)
	    		$result[$att_status]=0;
	    	
	        $at8=0;
	        
	        foreach ($to_conclude as $att_status)
	        {
	            switch ($att_status)
	            {
	                case 7:	                	 
	                	$at7=0;
	                	foreach ($days as $day)
	                	{
	                		$overtime=$day->calculate_overtime($this->select('username'));
	                		if ($overtime<1)
	                			$at7+=$overtime;
	                		else
	                		{
	                			$at7+=1;
	                			$at8+=$overtime-1;
	                		}
	                	}
/*	                    if ($attendance=$this->select('att_'.$att_status))
	                    { 
	                        foreach ($attendance as $date)
	                        {
	                            $overtime=$days[$date]->calculate_overtime($this->select('username'));
	                            if ($overtime<1)
	                                $at7+=$overtime;
	                            else
	                            {
	                                $at7+=1;
	                                $at8+=$overtime-1;
	                            }
	                        }
	                    }*/
	                    
	                    $result[$att_status]=(float) $at7 ?: 0;
	                    break;
	                case 8:
	                	$result[$att_status]=(float) $at8 ?: 0;
	                    break;	                    
	                case 11: 
	                	$at11=0;
	                    if ($attendance=$this->select('att_'.$att_status))
	                        foreach ($attendance as $date)
	                        {
	                        	$at11+=$days[$date]->actual_undertime($this->select('username'));
	                        }
	                    $result[$att_status]=$at11;
	                    break;
	                case 14:
	                    if ($attendance=$this->select('att_'.$att_status))
	                        $result[$att_status]=(float) count($attendance)/2;
	                    else
	                        $result[$att_status]=0;
	                    break;
	                case 18:
	                	$result[$att_status]=0;
	                	$attendance=$this->select('att_'.$att_status);
	                	foreach ($attendance as $date)
	                	{
	                		$day=$days[$date];
	                		if ((!$this->is_personal_holiday($day->as_day_of_week(), $day->uniqueid)) &&
	                			(!$day->public_holiday_status()) && 
	                			($this->on_maternity_privilege($day->uniqueid)))
	                			$result[$att_status]++;
	                	}
	                	break;
	                case 23:
	                	foreach ($days as $day)
	                		if ($day->forcedleave($this->select('username')))
	                			$result[$att_status]++;
	                	break;
	                default:
	                    if ($attendance=$this->select('att_'.$att_status))
	                        $result[$att_status]=count($attendance);
	                    else
	                   		$result[$att_status]=0;
	                    break;
	            }
	        }
	        
	        return $result;
	    }
	    
	    static function salary_map(...$choices)
	    {
	        $conclusions=
		        ['salary' => ['short' => 'G Pokok', 'long' => 'Gaji Pokok'],
	             'salary_bonus1' => ['short' => 'Jabatan', 'long' => ' Tunjangan Jabatan'],
	             'salary_bonus2' => ['short' => 'Skill', 'long' => 'Tunjangan Skill'],
	             'recruit_date' => ['short' => 'M Kerja', 'long' => 'Masa Kerja'],
	             'overtime' => ['short' => 'Lem', 'long' => 'Lemburan'],
	             'misc4_privilege' => ['short' => 'Trans', 'long' => 'Transport'],
		         'misc1_privilege' => ['short' => 'H1', 'long' => 'Penggantian H1'],
		         'others_earning' => ['short' => '+ Lain', 'long' => 'Tambahan Insidentil'],
		         'earnings' => ['short' => 'T Terima', 'long' => 'Penerimaan'],
				 'salary_deduction' => ['short' => 'Pot Absen', 'long' => 'Potongan Absensi'],
		         'salary_undertime' => ['short' => 'Pot Ijin', 'long' => 'Potongan Ijin'],
		         'tax' => ['short' => 'PPh', 'long' => 'PPh 21'],
				 'subscription' => ['short' => 'Anggota', 'long' => 'Potongan Anggota'],		        	
		         'salary_ded1' => ['short' => 'Rutin', 'long' => 'Potongan Rutin'],
		         'others_deduction' => ['short' => '- Lain', 'long' => 'Potongan Insidentil'],
		         'fines' => ['short' => 'Denda', 'long' => 'Cicilan Denda'],
		         'bills' => ['short' => 'Cic', 'long' => 'Cicilan'],
		         'deductions' => ['short' => 'T Potongan', 'long' => 'Potongan'],
	             'total' => ['short' => 'Pen', 'long' => 'Total Penerimaan']];
	        
	        $result=[];
	        if (!empty($choices))
	        {
	            foreach ($choices as $choice)
	                $result[$choice]=$conclusions[$choice];
	        }
	        else
	            $result=$conclusions;
	            
            return $result;
	    }
	    
	    function get_income_tax($earnings)
	    {
	    	$yearly=$earnings*12;

	    	$tax=0;
	    	$pos_cut=($yearly*$this->get_global('settings')->select('employee_tax_position', 'p')/100<
	    			  $this->get_global('settings')->select('employee_tax_position', 'v')) ?
	    			  $yearly*$this->get_global('settings')->select('employee_tax_position', 'p')/100 :
	    			  $this->get_global('settings')->select('employee_tax_position', 'v');
	    			  
   			$yearly=$yearly-($this->select('employee_tax_free') ?: $this->get_global('settings')->select('employee_tax_limit', 0))-$pos_cut;

	    	if ($yearly>0)
	    	{	    		
		    	$tax_values=$this->get_global('settings')->select('employee_tax');
		    	$tier=1;
		    	$tax_tiers=count($tax_values);
		    	
		    	while (($tier<=$tax_tiers) && ($yearly>$this->get_global('settings')->select('employee_tax_limit', $tier)))
		    	{
		    		$yearly=$yearly-$this->get_global('settings')->select('employee_tax_limit', $tier);
		    		if ($yearly>0)
		    			$tax+=$this->get_global('settings')->select('employee_tax_limit', $tier)*$this->get_global('settings')->select('employee_tax', $tier)/100;
		    			
		    		$tier++;
		    	}
		    	
		    	$tax+=$yearly*$this->get_global('settings')->select('employee_tax', $tier)/100;
	    	}

	    	return $tax;
	    }
	    
	    function get_deduction_calculation($attendance, $month)
	    {	    	
	    	$otc=$this->getHourlyCost($month);
	    	
//	    	if ($this->username==107)
//	    		var_dump($this->daily_working_costs(), $this->count_workday($month));
	    	$cuttings=($attendance[17]+$attendance[3]+$attendance[6]+$attendance[23])*
	    				$this->daily_working_costs();//potongan mangkir + ijin + sakit + dirumahkan
	    	
	    	$salary=$this->select('salary');
	    	/*
	    	 * Maternity cut!
	    	 */
	    	if ($attendance[18]>0)
		    	$cuttings+=$salary-$salary*($attendance[7]/
		    		$this->get_global('settings')->select('monthly_workdays', 'maternity'));
	    		    	
	    	$undertimes=$otc*$attendance[11]; //potongan ijin keluar	    				
	    					    	
	    	$result=[];
	    	$result['salary_deduction']=$cuttings;
	    	$result['salary_undertime']=$undertimes;
	    	$result['fines']=$this->fine_installment($month);
	    	if ($attendance[23]>0)
	    		$result['salary_forcedleave']=0.1*$this->select('salary')/30*$attendance[23];
	    		
	    	$deductions=[];
	    		    	
	    	$deductions['insurance1']=($this->select('insurance1') && $this->select('insurance1')=='on') ? 
	    			$this->get_global('settings')->select('insurance1', 'value')*$salary/100 : 0; 
	    	$deductions['insurance2']=($this->select('insurance2') && $this->select('insurance2')=='on') ? 
	    			$this->get_global('settings')->select('insurance2', 'value')*$salary/100 : 0;
	    	$deductions['insurance3']=($this->select('insurance3') && $this->select('insurance3')=='on') ? 
					$this->get_global('settings')->select('insurance3', 'value')*$salary/100 : 0;
	    	
	    	$deductions['union']=$this->select('union') ? $this->get_global('settings')->select('union', $this->select('union')) : 0;
	    	
	    	$deductions['coop']=$this->select('coop') ? $this->get_global('settings')->select('coop') : 0;
	    	$deductions['salary_ded1']=$this->select('salary_ded1');
	    	
	    	$result=array_merge($result, $deductions);
	    	
	    	$result['subscription']=array_sum($deductions);
	    	
	    	/*
	    	 * Bills is installment that is paid by users
	    	 */
	    	foreach ($this->select('bills', $month) as $id => $value)
	    		$result[$id]=$value;
	    		
    		$result['total_bills']=array_sum($this->select('bills', $month) ?? []);
	    	
	    	return $result;
	    }
	    
	    function get_skill_bonus($month)
	    {	    	
	    	$result=$this->t_select($month, 'salary_bonus2');
	    	/*
	    	 * Compatibility reason but obsolete and removed!
	    	 */
/*	    	{	    		
		    	$creds=$this->get_global('system_credentials_list');
		    	
		    	foreach ($creds as ['name' => $name, 'module' => $module])
		    	{		    		
		    		if ($this->is_credential($name))
		    		{		    			
		    			if ($module)
		    				$value=$this->get_global('lib_human_resources')->select('salary_bonus2', $module);
		    			else
		    				$value=$this->get_global('lib_human_resources')->select('salary_bonus2', $name);
		    		}

		    		$result=$value>$result? $value : $result;
		    	}
	    	}*/
	    	 
	    	return $result;
	    }
	    
	    function get_worklength_bonus($month)
	    {
	    	$work_length=$this->date_difference($month, $this->select('recruit_date'), '%y');
	    	
	    	$max_wl=max(array_keys($this->get_global('lib_human_resources')->select('work_length')));
	    		    	
	    	if ($work_length>$max_wl)
	    		$work_length=$max_wl;
	    	
	    	$result=$this->get_global('lib_human_resources')->select('work_length', $work_length);
	    	
	    	return $result;
	    }
	    
	    function calculate_misc4_privilege($month)
	    {
	    	$date=$this->date_string_to_timestamp(date('Y-m', $month).'-'.$this->get_global('settings')->select('monthchange_date'));
	    	$begin=$this->change_month(-1, $date, 'timestamp');
	    	$end=$date;
	    	
	    	$result=0;
	    	
	    	foreach ($this->select('_misc4_privilege') as $time => $value)
	    	{	    		
	    		if (($begin<=$time) && ($time<=$end))
	    			$result+=$value;
	    	}

	    	return $result*$this->get_global('lib_human_resources')->select('misc4_privilege');
	    }
	    
	    function get_paycheck($days, $month, $payment_group)
	    {
	    	$result=$this->get_salary_calculation($days, $month);
	    	
	    	return $result['total']-$this->get_global('lib_human_resources')->select('payment_group_cash', $payment_group);
	    }
	    
	    function get_salary_calculation($days, $month, $attendance=null)
	    /*
	     * This method is used to caculate salary from attendance
	     */
	    {	    
	    	if (!$attendance)
		    	$attendance=$this->get_attendance_conclusion(array_keys(attendance::conclusion_map()), $days);
	    	$deductions=$this->get_deduction_calculation($attendance, $month);
	    	$otc=$this->getHourlyCost($month);
	    	
	    	$overtime=$attendance[14]*$otc*1.5;
	    	$overtime+=$attendance[7]*$otc*1.5;
	    	$overtime+=$attendance[8]*$otc*2;
	    		    	
    		$result['salary']=(float) $this->select('salary');
    		$result['salary_bonus1']=(float) $this->select('salary_bonus1');
    		$result['salary_bonus2']=$this->get_skill_bonus($month);
	        
	        $result['recruit_date']=(float) $this->get_worklength_bonus($month);
	        
	        $result['overtime']=(float) $overtime;
	        $result['misc4_privilege']=(float) $this->calculate_misc4_privilege($month);
	        				        
	        if (($this->has_misc1_privilege()) && 
	        	(($limit=$this->get_global('lib_human_resources')->select('misc1_privilege', 'monthly_limit')-$attendance[9])>0))	        
	        	$result['misc1_privilege']=$limit*$this->daily_working_costs();
	        else
	        	$result['misc1_privilege']=0;
	        
	        $result['others_earning']=(float) $this->select('others_earning', $month);	        	
	        	
	        $result['earnings']=array_sum($result);
	        
	        $result['salary_deduction']=(float) $deductions['salary_deduction'];	        
	        $result['salary_undertime']=(float) $deductions['salary_undertime'];
	        
	        $result['tax']=$this->get_income_tax($result['earnings']-$deductions['salary_deduction']-$deductions['salary_undertime']);
	        
	        $result['subscription']=$deductions['subscription'];
	        $result['salary_ded1']=$deductions['salary_ded1'];
	        	        
	        $result['others_deduction']=(float) $this->select('others_deduction', $month);
			$result['bills']=$deductions['total_bills'];
			
			$result['fines']=$deductions['fines'];			
			
			$result['deductions']=
					$deductions['salary_deduction']+$deductions['salary_undertime']+
					$result['tax']+$deductions['subscription']+
					$deductions['salary_ded1']+$result['others_deduction']+
					$deductions['total_bills']+$result['fines'];
	        
	        $result['total']=$result['earnings']-$result['deductions']+$deductions['salary_forcedleave'];
	        	        
	        return $result;
	    }
	    	    
	    function has_personal_holiday($date=null, $no=0)
	    /*
	     * This will check if it got personal holiday in a week
	     */
	    {
	    	$p_holiday=$this->get_personal_holiday_data($date, $no);
//			var_dump($this->get('p_holiday'));
	    	return $p_holiday['dayofweek'] ?? null;
	    }
	    
	    function in_personal_holiday(int $day_of_week, $no, $date=null)
	    /*
	     * This will check if that day in a week is a personal holiday 
	     */
	    {
	    	return $day_of_week==$this->get_personal_holiday($date, $no);
	    }
	    
	    function is_personal_holiday($day_of_week, $date=null, $return_holiday_no=false)
	    /*
	     * This will return false if the day is not personal holiday,
	     * otherwise it will return true when $return_holiday_no is false
	     * otherwise will return holiday number
	     */
	    {	        
	    	$result=false;
	    	
	    	for ($i=0; $i<7-$this->get_workdays($date); $i++)
	    	{//var_dump($i);
	    		if ($day_of_week==$this->get_personal_holiday($date, $i))
	    		{	    			
	    			$result=!$return_holiday_no ? true : $i;	    			
	    		}
	    		
/*	    		if ($day_of_week==6)
	    			if ($i==1)
	    			var_dump($i, $this->get('p_holiday', $i));*/
	    	}
	    		
	    	return $result;
	    }
	    
	    function move_personal_holiday($day)
	    /*
	     * Possibly obsolete
	     */
	    {
	        $this->set_personal_holiday($this->select('p_holiday', 'dayofweek')+$day);
	    }
	    
	    function reset_personal_holiday_data()
	    /*
	     * Remove old PHD variables to save space
	     */
	    {
	    	$this->remove('p_holiday_start');
	    	$this->remove('p_holiday_end');
	    	$this->remove('p_holiday_quarter');
	    	$this->remove('p_holiday_dayofweek');
	    	$this->remove('p_holiday_auth');	    	
	    }
	    
	    function reset_personal_holiday($target_date)
	    {
	        $this->remove('p_holiday');
	        
	        $this->reset_personal_holiday_data();
	        
	        $this->update_p_holiday(0);
	        	        
	        $this->update();
	    }
	    
	    function swap_personal_holiday($original, $temporary, $start, $end, $no=0)
	    /*
	     * This is used to swap personal holiday with to a temporary holiday in a week
	     * Original and temporary is the day of the week
	     * 
	     * start and end is the begining and the end of the particular week where the
	     * holiday is swapped
	     */
	    {
	    	$start_holiday=$this->get_personal_holiday_data($start, $no);
	    	$result=0;
	    	
	    	if (!empty($start_holiday))
	    	{
	    		/*
	    		 * End the original
	    		 */	    		
		    	$shd=$start_holiday['start'];
		    	$this->set_p_holiday($no, $shd, $start, $start_holiday['quarter'], $start_holiday['dayofweek'], $start_holiday['id']);
		    	
		    	/*
		    	 * Begin and the swap
		    	 */
		    	$this->set_p_holiday($no, $start+1, $end, $this->year_quarter(), $temporary);	    	
		    	/*
		    	 * Begin and end the continuation
		    	 */
		    	$this->set_p_holiday($no, $end+1, EOT, $this->year_quarter(), $original);
		    	
		    	$result=1;
	    	}
	    	
	    	return $result; 
//	    	var_dump($no);
	    	/*
	        $this->makeArray('p_holiday');
	        $this->makeArray('p_holiday_start');
	        $this->makeArray('p_holiday_end');
	        $this->makeArray('p_holiday_dayofweek');
	        $this->makeArray('p_holiday_auth');
	        
	        if ($diffs=array_diff_key($this->select('p_holiday_start'), $this->select('p_holiday_end')))
	        {//var_dump($diffs);
	            foreach (array_keys($diffs) as $unpaired_id)
	            {
	                //var_dump($diff);
	                if ($start>$this->select('p_holiday_start', $unpaired_id))
	                    $this->addData('p_holiday_end', $start, $unpaired_id);
	            }
	        }
	        
	        $id=$this->select('p_holiday', $start) ?? count($this->select('p_holiday'));
	        
	        $this->addData('p_holiday', $id, $start);
	        $this->addData('p_holiday_start', $start, $id);
	        $this->addData('p_holiday_end', $end, $id);
	        $this->addData('p_holiday_quarter', $this->year_quarter($start), $id);
	        $this->addData('p_holiday_dayofweek', $temporary, $id);
	        $this->addData('p_holiday_auth', $this->credentials->username);
	        
	        if (!$this->select('p_holiday', $end))
	        {	            
	            $id=$this->select('p_holiday', $end) ?? count($this->select('p_holiday'));
	            $this->addData('p_holiday', $id, $end);
	            $this->addData('p_holiday_start', $end, $id);
	            $this->addData('p_holiday_quarter', $this->year_quarter($end), $id);
	            $this->addData('p_holiday_dayofweek', $original, $id);
	            $this->addData('p_holiday_auth', $this->credentials->username);
	        }	        */
	    }
	    
	    function set_personal_holiday($dayofweek, $no, $time=null)
	    /*
	     * Setting personal holiday permanently
	     */
	    {
	        while ($dayofweek > 7)
	            $dayofweek -= 7;
	            
	        $start=$time ?? time();
	        	        
	        $end=EOT; //Please fix in the future!
	        	
//			$id=count($this->get('p_holiday'));

	        $start_holiday=$this->get_personal_holiday_data($start, $no);
	        if (!empty($start_holiday))
	        {	        		        	
	        	/*
	        	 * End the original if existed
	        	 */
	        	$shd=$start_holiday['start'];	        	
	        	$this->set_p_holiday($no, $shd, $start-1, $start_holiday['quarter'], $start_holiday['dayofweek'], $start_holiday['id']);
	        	
	        	$this->set_p_holiday($no, $start, EOT, $this->year_quarter(), $dayofweek);	        	
	        }
	        else
	        	$this->set_p_holiday($no, $start, $end, $this->year_quarter(), $dayofweek);
	        
	        $this->set_perm_personal_holiday($dayofweek, $no);
			
/*			$this->set('p_holiday', $id, $no, $start, 'id');
	        $this->set('p_holiday', $start, $no, $start, 'start');
	        $this->set('p_holiday', $end, $no, $start, 'end');
	        $this->set('p_holiday', $this->year_quarter(), $no, $start, 'quarter');
	        $this->set('p_holiday', $this->credentials->username, $no, $start, 'auth');*/
	        	        	            
/*            $this->makeArray('p_holiday');
            $this->makeArray('p_holiday_start');
            $this->makeArray('p_holiday_end');
            $this->makeArray('p_holiday_dayofweek');
            $this->makeArray('p_holiday_auth');
	            	            
            $time=$time ?? time();*/
	            
	            /*
	             * Bug Here, if add to past holiday then no idea where to go
	             * Holiday pairing was nice but when adding past holiday
	             * must fix
	             */
/*            $id=$this->select('p_holiday', $time) ?? count($this->select('p_holiday'));

            if ($diffs=array_diff_key($this->select('p_holiday_start'), $this->select('p_holiday_end')))
            {
                foreach (array_keys($diffs) as $diff)
                {
                    if ($time>$this->select('p_holiday_start', $diff))
                        $this->addData('p_holiday_end', $time, $diff);
                }
            }
	            
            $this->addData('p_holiday', $id, $time);
            $this->addData('p_holiday_start', $time, $id);
            $this->addData('p_holiday_quarter', $this->year_quarter(), $id);
            $this->addData('p_holiday_dayofweek', $dayofweek, $id);
            $this->addData('p_holiday_auth', $this->credentials->username);*/
	    }
	    	    
	    function get_personal_holiday_data(?int $date, int $no)
	    /*
	     * Return the latest personal holiday
	     * input personal holiday no and date
	     */
	    {
	    	if (!$date) $date=\date::today('timestamp');
	    		    		    	
	    	if (!$this->temp_data['p_holiday_sorted'])
	    	{
		    	$p_holiday=$this->get('p_holiday', $no) ?? [];
		    	ksort($p_holiday);
	    	}
	    	
	    	$result=[];
	    	
    		foreach ($p_holiday as ['start' => $start])
    		{
    			if (($date>=$this->select('p_holiday', $no, $start, 'start')) &&
    				($date<=$this->select('p_holiday', $no, $start, 'end')))
    			{
    				$result=$p_holiday[$start];
    				break;
    			}
    		}
	    	 
	    	return $result;
	    	
	    }
	    	    
	    function get_personal_holiday_auth($date=null, $no=0)
	    {
	    	return $this->get_personal_holiday_data($date, $no)['auth'];
	    }
	    
	    function get_personal_holiday($date=null, $no=0): int
	    /*
	     * This will return the day of the week of personal holiday 
	     * or zero when not set
	     */
	    {
	    	$p_holiday=$this->get_personal_holiday_data($date, $no);
//	    	$dayofweek=$p_holiday['dayofweek'];
	    	
	    	if ((!$dayofweek=$p_holiday['dayofweek']) && ($this->get_workdays($date)+$no<7))
	    	//auto adjust from friday, saturday, sunday
	    		$dayofweek=$this->get_workdays($date)+$no+1; 
//	    	else
	    	//else just return sunday
//	    		$dayofweek=7;
	    	
/*	        $p_holiday=$this->select('p_holiday') ?? [];
	        if (empty($p_holiday))
	        	$dayofweek=7;
	        else
	        {
		        ksort($p_holiday);
		        $key_last=$p_holiday[array_key_last($p_holiday)];
		        
		        if (($this->select('p_holiday_start', $key_last) ?? mktime(0, 0, 0, 1, 1, 2021))<=($date ?? time()))
		        {
		            $dayofweek=$this->select('p_holiday_dayofweek', $key_last) ?? 7; //default to Sunday
		        }
		        else
		        {
		            $dayofweek=7; //default on sunday
		            foreach ($p_holiday as $id)
		            {
		                if (($date>=$this->select('p_holiday_start', $id)) &&
		                    ($date<=$this->select('p_holiday_end', $id)))
		                    $dayofweek=$this->select('p_holiday_dayofweek', $id);
		            }
		        }
	        }*/
	        
	        return $dayofweek ?? 0;
	    }
	    
	    function get_perm_personal_holiday($date, $no): int
	    /*
	     * This will return the data of the permanently set personal holiday
	     * it will not change by swapping personal holiday
	     * 
	     * returns 0 when personal holiday is disabled
	     * returns null when its not set
	     */
	    {
	    	$result=$this->t_select($date, 'perm_p_holiday', $no);
	    	
	    	if (is_null($result))
	    		$result=$this->get_personal_holiday($date, $no);
	    		
	    	return $result;
	    }
	    
	    function set_perm_personal_holiday($dayofweek, $no)
	    /*
	     * Setting permanent personal holiday, that can only be changed by calling
	     * set_personal_holiday, but not just any holiday
	     */
	    {
	    	$this->set('perm_p_holiday', $dayofweek, $no);
	    }
	    
	    function set_p_holiday($no, $start, $end, $quarter, $dayofweek, $id=null)
	    /*
	     * This method is used to set personal holiday in standard way
	     */
	    {
	    	if (is_null($id))
	    		$id=$this->count('p_holiday');
	    	
	    	$this->set('p_holiday', $id, $no, $start, 'id');
	    	$this->set('p_holiday', $start, $no, $start, 'start');
	    	$this->set('p_holiday', $end, $no, $start, 'end');
	    	$this->set('p_holiday', $quarter, $no, $start, 'quarter');
	    	$this->set('p_holiday', $dayofweek, $no, $start, 'dayofweek');
	    	$this->set('p_holiday', $this->credentials->username, $no, $start, 'auth');
	    }
	    
	    function update_p_holiday(int $begin_time)
	    /*
	     * This method will be called when p_holiday is updated and changed during update
	     * method
	     * 
	     * called by insert method on employee_input class and reset_personal_holiday
	     */
	    {
	    	$workday=$this->get_workdays();
	    	$ph_list=[];//already taken holiday
	    	
	    	for ($i=0; $i<3; $i++)
	    		if ($i>=7-$workday)
	    		{
	    			/*
	    			 * Mark repeated holiday has ended
	    			 * Do not remove, otherwise will be trouble
	    			 */
	    			$phd=$this->get_personal_holiday_data(\date::today('timestamp'), $i);
//	    			var_dump($i, "Z", $phd);
	    			if (!empty($phd))
	    			{
	    				/*
	    				 * Removal and ending of personal holiday 
	    				 */
//	    				var_dump($i, "Y");
	    				$this->set('p_holiday', $this->end_of_week(\date::today('timestamp'), true), $i, 
	    					$phd['start'], 'end');	    	
	    				$this->set_perm_personal_holiday(0, $i);
	    			}
	    		}
		    	else
		    	{
		    		if (empty($this->get_personal_holiday_data(\date::today('timestamp'), $i)))
		    		/*
		    		 * No holiiday data recorded, automatically assigning new personal holiday data
		    		 */
		    		{
		    			$dayofweek=7;
		    			/*
		    			 * Scanning for automatically assigning new personal holiday
		    			 */
		    			while (($ph_list[$dayofweek]) && ($dayofweek>=1))
		    				$dayofweek--;
		    			
		    			if ($dayofweek==0)
		    				$dayofweek=7;
		    				
		    			$this->set_p_holiday($i, $this->end_of_week($begin_time, true), EOT, $this->year_quarter(), $dayofweek);
		    			$ph_list[$dayofweek]=1;
		    			
		    			$this->set_perm_personal_holiday($dayofweek, $i);
		    		}
		    		else
		    		{
		    			/*
		    			 * Cataloging for already taken personal holiday
		    			 */
		    			$ph_list[$this->get_personal_holiday(\date::today('timestamp', $i))]=1;
		    		}
		    	}	
		    	
//		    var_dump($this->get('p_holiday'));		    	
	    }
	    
	    function p_holiday_update()
	    /*
	     * Updating p_holiday_data in everything during loading of the employee and converting old p_holiday storage
	     * to new p_holiday storage
	     * 
	     * Please remove this on production, and move it to to other place, e.g. during holiday time update 
	     */
	    {	    		    		    	
	    	if (is_null($this->get('p_holiday_format')))
	    	{
	    		$p_holiday=$this->get('p_holiday') ?? [];
	    		
	    		if (!empty($p_holiday))
	    		{
		    		$this->remove('p_holiday');
		    		
		    		for ($i=0; $i<7-$this->get_workdays(); $i++)
		    		{
			    		foreach ($p_holiday as $start => $id)
			    			if ($this->get('p_holiday_dayofweek'))
				    		{
				    			$this->set('p_holiday', $id, $i, $start, 'id');
				    			$this->set('p_holiday', $start, $i, $start, 'start');

				    			$this->set('p_holiday', $this->get('p_holiday_end', $id) ?? EOT, $i, $start, 'end');
			    				$this->set('p_holiday', $this->get('p_holiday_quarter', $id), $i, $start, 'quarter');
			    				
			    				$dow=$i==0 ? ($this->get('p_holiday_dayofweek', $id)) : 7 - $i;
			    				$this->set('p_holiday', $dow, $i, $start, 'dayofweek');
			    				$this->set('p_holiday', $this->get('p_holiday_auth', $id), $i, $start, 'auth');			    							    				
			    			}			    					    		
		    		}		    		
	    		}
	    		else
	    		{	 
	    			for ($i=0; $i<7-$this->get_workdays(); $i++)
	    			{	    				
	    				$this->set_p_holiday($i, 0, EOT, $this->year_quarter(), 7-$i);	    				
	    			}
	    		}

	    		$this->reset_personal_holiday_data();
	    		$this->set('p_holiday_format', 1);
	    	}	    
	    	else
	    	{
	    		/*
	    		 * Don't think thils ever accessed
	    		 */	    		
				$this->update_p_holiday(0);
	    	}	    	
	    }
	    	    
	    function get_facility()
	    {
	        /*			$settings=$this->get_global('settings');
	        
	        $facility_credentials=$settings->select('facility_credentials');
	        $divisions=$this->get_divisions();
	        
	        foreach ($divisions as $division)
	        if (array_key_exists($division, $facility_credentials))
	        {
	        $result=$facility_credentials[$division];
	        break;
	        }*/
	        /*			foreach ($facility_credentials as $f_credential => $credential)
	         {
	         $table=$settings->select($credential, 'c_table');
	         if ($this->credentials->check_other_credentials($table, $this->select('username')))
	         {
	         $result=$f_credential;
	         break;
	         }
	         }*/
	        
	        $divisions=$this->get_divisions();
	        
	        foreach ($divisions as $division)
	        {
	            if ($this->credentials->extensions[$division])
	            {
	                $result=$this->credentials->extensions[$division]->get_master_from_credential($division) ?: $division;
	                break;
	            }
	        }
	        
/*	        if (!$result)
	        // Legacy way
	        {
	            $settings=$this->get_global('settings');
	            
	            $facility_credentials=$settings->select('facility_credentials');
	            
	            foreach ($divisions as $division)
	                if (array_key_exists($division, $facility_credentials))
	                {
	                    $result=$facility_credentials[$division];
	                    break;
	                }
	        }*/
	        
	        return $result;
	    }
	    
	    function paycheck_rounding($new_paycheck)
	    {
	        $settings=$this->get_global('settings');
	        $rounding=$settings->select('paycheck_rounding');
	        
	        $rounded_paycheck=floor($new_paycheck/$rounding)*$rounding;
	        
	        return $rounded_paycheck;
	    }
	    
	    function calculate_paycheck($paycheck, $bill, $date)
	    {
	        $balance=$this->select('balance') ?? 0;
	        
	        $new_paycheck=$paycheck-$bill+$balance;
	        
	        $rounded_paycheck=$this->paycheck_rounding($new_paycheck);
	        $new_balance=$new_paycheck-$rounded_paycheck;
	        
	        $paycheck_dates=$this->select('paycheck_date');
	        $paycheck_index=array_search($date, $paycheck_dates);
	        
	        if ($paycheck_index===false) $paycheck_index=count($paycheck_dates);
	        else if ($paycheck_index==='') $paycheck_index=0;
	        
	        $this->makeArray('paycheck_value');
	        $this->makeArray('paycheck_bill');
	        $this->makeArray('paycheck_received');
	        $this->makeArray('paycheck_date');
	        $this->makeArray('paycheck_officer');
	        
	        if ($paycheck_index==count($paycheck_dates)) $this->addData('balance', $new_balance);
	        
	        $this->addData('paycheck_value', $paycheck, $paycheck_index);
	        $this->addData('paycheck_bill', $bill, $paycheck_index);
	        $this->addData('paycheck_received', $rounded_paycheck, $paycheck_index);
	        $this->addData('paycheck_date', $date, $paycheck_index);
	        $this->addData('paycheck_officer', $this->credentials->username, $paycheck_index);
	        
	        $result=array('old_balance' => $balance, 'paycheck_received' => $rounded_paycheck, 'balance' => $new_balance);
	        
	        return $result;
	    }
	    
	    function rf_balance($target_month)
	    {
	        $paycheck_dates=$this->select('paycheck_date');
	        $paycheck_index=array_search($target_month, $paycheck_dates);
	        $balance=$this->select('starting_balance');
	        
	        if ($paycheck_index!==false)
	        {
	            
	            $paycheck_values=$this->select('paycheck_value');
	            $paycheck_bills=$this->select('paycheck_bill');
	            for ($i=0; $i<=$paycheck_index; $i++)
	            {
	                $paycheck=$paycheck_values[$i];
	                $bill=$paycheck_bills[$i];
	                $new_paycheck=$paycheck-$bill+$balance;
	                
	                $rounded_paycheck=$this->paycheck_rounding($new_paycheck);
	                $balance=$new_paycheck-$rounded_paycheck;
	            }
	        }
	        
	        return $balance;
	    }
	    
	    function select_array($index, $search_array, $target_array)
	    {
	        $array=$this->select($search_array);
	        $search=array_search($index, $array);
	        
	        if (is_integer($search))
	        {
	            $paycheck_values=$this->select($target_array);
	            $result=$paycheck_values[$search];
	        }
	        else
	        {
	            $result='';
	        }
	        
	        return $result;
	    }
	    
	    function rf_salary($target_month)
	    {
	        return $this->select_array($target_month, 'paycheck_date', 'paycheck_value');
	    }
	    
	    function rf_bill($target_month)
	    {
	        return $this->select_array($target_month, 'paycheck_date', 'paycheck_bill');
	    }
	    
	    function rf_received_paycheck($target_month)
	    {
	        return $this->select_array($target_month, 'paycheck_date', 'paycheck_received');
	    }
	    
	    function is_human()
	    {
	        return ($this->get_global('settings')->select('robot', $this->select('username'))!='1');
	    }
	    
	    function is_credential($credential)
	    {
	        $result=$this->credentials->check_other_credentials(
					$this->credentials->definition_data($credential, 'table'), $this->select('username'));
	        
	        return $result;
	    }
	    
	    function update_password(#[\SensitiveParameter] $password)
	    {
	    	$this->credentials->update_password($this->get('username'), $password);	    	
	    }
	    
	    function add_daylist_performance(day_list $day_list)
	    {
	        foreach ($this->day_list as $element)
	        {
	            $this->makeArray('dayoff');
	            $this->makeArray('overwork');
	            $this->makeArray('sickleave');
	            $this->makeArray('skipwork');
	            $this->makeArray('leave');
	            
	            if ($element->approved_day_off($this->select('username')))
	                $this->addData('dayoff', $element->getIndex());
	                
	            if ($element->select('overwork', $this->select('username'))=='true')
	                $this->addData('overwork', $element->getIndex());
	                    
	            if ($element->overtime($this->select('username'))!=0)
	                $this->addData('overtime', $element->select('overtime', $this->select('username')), $element->getIndex());
	                        
				if ($element->undertime($this->select('username'))!=0)
					$this->addData('undertime', $element->select('undertime', $this->select('username')), $element->getIndex());
	                            
				if ($element->sickleave($this->select('username')))
					$this->addData('sickleave', $element->getIndex());
	                                
				if ($element->skipwork($this->select('username')))
					$this->addData('skipwork', $element->getIndex());
	                                    
				if ($element->leave($this->select('username')))
					$this->addData('leave', $element->getIndex());
	        }
	    }
	    
	    function add_daylist(days $day_list)
	    {
	        $this->makeArray('sickleave');
	        $this->makeArray('skipwork');
	        $this->makeArray('leave');
	        
	        foreach ($day_list as $day)
	        {
	            if ((!$this->is_personal_holiday($this->date($day->uniqueid, 'N'), $day->uniqueid)) &&
	                (!$day->public_holiday_status()))
	            {
	                $at_status=$day->attendance_status($this);
	                
					if (array_search(6, $at_status)!==false)
	                    $this->addData('skipwork', 1, $day->uniqueid);
	                    
					if (array_search(2, $at_status)!==false)
						$this->addData('sickleave', 1, $day->uniqueid);

					if (array_search(3, $at_status)!==false)
						$this->addData('leave', 1, $day->uniqueid);
	            }
	        }
//	        var_dump($this->select('username'), count($this->select('skipwork')), count($this->select('leave')), count($this->select('sickleave')));
//	        echo("<br>");
	    }
	    
	    function mark_decommission($credentials, $status)
	    {
	        if (!($commission=$this->select('commission_end'))) $this->makeArray('commission_end');
	        
	        $c_count=count($commission ?? []);
	        
	        $this->set('work_relationship', $status);
	        $this->addData('commission_end', \date::today('timestamp'), $c_count);
	        $this->addData('commission_auth', $credentials, $c_count);
	    }
	    
	    function mark_recommission($credentials)
	    {
	        if (!($commission=$this->select('commission'))) $this->makeArray('commission');
	        
	        $c_count=count($commission ?? []);
	        $this->addData('commission', \date::today('timestamp'), $c_count);
	        
	        $this->addData('commission_auth', $credentials->username, $c_count);
	        
	        $this->set('work_relationship', 0);	       
	        $this->set('recruit_date', \date::today('begin_of_day'));
	    }
	    
	    function is_commissioned($date=null)
	    {
	    	$date=$date ?: \date::today('timestamp');
	    	
	    	if ($this->get('work_relationship'))
	    	{
		        $result=false;
		        $commission=$this->select('commission') ?? [0 => $this->begin_of_day($date, 'timestamp')];
		        
		        foreach ($commission as $no => $c)
		        {
		            $d=$this->select('commission_end', $no) ?? $this->end_of_day('', 'timestamp');
		            
		            if (($c<$date) && ($date<$d))
		                $result=true;
		        }
	    	}
	    	else
	    		$result=true;
	    		
	        return $result;
	    }
	    
	    function is_commissioned_by(day_list $day_list)
	    /*
	     * Search weather this guy is commissioned in all the day list
	     * used to check all employeee data
	     */
	    {
	        $result=false;
	        foreach ($day_list as $day)
	            if ($this->is_commissioned($day->uniqueid))
	            {
	                $result=true;
	                break;
	            }
	        
	        return $result;
	    }
	    
	    function create_map($header, $month)
	    {
	    	$map=[];
	    	foreach ($header as $column)
	    		if ((strtolower($column)!='nip') && (strtolower($column)!='no'))
	    			$map[]=$column;
	    		
	    	$this->addData('bills_map', $map, $month);
	    }
	    
	    function bills_map($month)
	    {
	    	return $this->select('bills_map', $month);
	    }
	    
	    function process_bills($header, $row, $month)
	    {	    		    	
	    	$bills=[];
	    	
	    	$this->create_map($header, $month);
	    	$map=$this->bills_map($month);
	    	
	    	foreach ($map as $internal => $hr)
    			$bills[$internal]=$this->number_unformat($row[$hr]);

	    	$this->addData('bills', $bills, $month);	    	
	    }
	    
	    function get_breakstart($dow=1)
	    {
			if ($dow>=1)
				$breakstart=$this->select('breakstart'.$dow) ?? $this->get_breakstart($dow-1);	    		
			else 
				$breakstart=4;
			
			return $breakstart;
	    }	    
	    
	    function get_startwork(day_data $day): int
	    {
	    	if ($this->get_shift()==='0')
	    	{
	    		$dow=$this->workday_number($day->uniqueid);
	    		$result=$this->hour_of_day(
					$this->get_global('settings')->select('nonshift_workstart_hour', $dow),
	    			$day->uniqueid);
	    		
	    		$result+=$this->get_global('converter')->convert_time('h', 's', $this->select('workstart_mods'));
	    	}
	    	else 
	    	{	
	    		$sequence=$this->get_shift_sequence($day->uniqueid);
	    		$workstart=$this->get_global('settings')->select('shift_0_start')+$sequence*$this->get_global('settings')->select('shift_length');
	    		
	    		$result=$this->hour_of_day($workstart, $day->uniqueid);
	    		
	    		$result+=$this->get_global('converter')->convert_time('h', 's', $this->select('workstart_mods'));	    		
		    }
	    	
	    	return $result;
	    }
	    
	    function get_shiftlength(day_data $day): int
	    /*
	     * Returns how many hours this employee is on the office during that particular day
	     */
	    {
	    	$dow=$this->workday_number($day->uniqueid);
	    	
	    	if ($this->get_shift()==='0')
	    	{
	    		$result=$this->get_global('settings')->select('variable_nonshift_length',$this->get_workdays($day->uniqueid), $dow)+
		    				$day->breaklength($this, 'h');
	    	}
	    	else
	    	{
	    		$shift_length=$this->get_global('settings')->select('variable_shift_length', $this->get_workdays($day->uniqueid), $dow);
	    		if ($this->username=='300') var_dump($shift_length, $day->breaklength($this, 'h'), $dow);
	    		$result=$shift_length+$day->breaklength($this, 'h');
	    	}
	    	
	    	return $result;	    	
	    }
	    	    
	    function get_endwork(day_data $day): int
	    /*
	     * returns timestamp
	     */
	    {	    	
	    	$dow=$this->workday_number($day->uniqueid);
	    	
	    	if ($this->get_shift()==='0')
	    	{
	    		$result=$this->get_startwork($day)+
	    				$this->get_global('converter')->convert_time('h', 's', $this->get_global('settings')->select('variable_nonshift_length', 
	    					$this->get_workdays($day->uniqueid), $dow))+
	    					$day->breaklength($this);	    		
	    	}
	    	else 
	    	{	    		
	    		$shift_length=$this->get_global('settings')->select('variable_shift_length', $this->get_workdays($day->uniqueid), $dow);
	    		
	    		$result=$this->get_startwork($day)+
	    				$this->get_global('converter')->convert_time('h', 's', $shift_length)+$day->breaklength($this);	    				
	    	}

	    	return $result;
	    }
	    
	    function count_workday($timestamp)
	    {
	    	$m=$this->date($timestamp, 'm');
	    	$y=$this->date($timestamp, 'Y');
	    	
	    	$begin=mktime(0, 0, 0, $m+att_date_begin_month, att_date_begin, $y);
	    	$end=mktime(23, 59, 59, $m, att_date_end, $y);
	    	
	    	return ceil($this->ts_date_difference($end, $begin));
	    }
	    
	    function workday_of_month($timestamp)
	    /*
	     * Return the timestamp of beginning and end of workday and number of workday of the month
	     */
	    {
	    	$m=$this->date($timestamp, 'm');
	    	$y=$this->date($timestamp, 'Y');
	    	
	    	$begin=mktime(0, 0, 0, $m+att_date_begin_month, att_date_begin, $y);
	    	$end=mktime(23, 59, 59, $m, att_date_end, $y);
	    	
	    	return ['begin' => $begin, 'end' => $end, 'length' => ceil($this->ts_date_difference($end, $begin))];	    	
	    }
	    
	    function get_breaktime(day_data $day)	    
	    {
	    	$result=[];
	    	
	    	if ($this->has_misc3_privilege($day->uniqueid))
	    	/*
	    	 * When doing friday prayer
	    	 */
	    	{
	    		$result['begin']=$this->time_of_day(['h' => 11, 'm' => 30], $day->uniqueid, 'timestamp');
	    		$result['end']=$result['begin']+$day->breaklength($this);
	    	}
	    	else
	    	{
	    		if ($this->get_shift()=='0')
	    		{
		    		switch ($day->today('N'))
		    		{
		    			case 5:
			    		{
			    		/*
			    		 * only on Friday give everyone 5400 seconds, on fast 3600
			    		 */
		    				$result['begin']=$this->time_of_day(['h' => 11, 'm' => 30], $day->uniqueid, 'timestamp');			    				
		    				$result['end']=$result['begin']+$day->breaklength($this);
			    			break;
			    		}
		    			case 6:		    		
			    		/*
			    		 * on last day of work breaklength 1800 seconds, on fast 0			    		 
			    		 */
			    		{
				    		$result['begin']=
				    			$this->get_startwork($day)+$this->get_global('converter')->convert_time('h', 's', $this->get_breakstart(6));			    		
				    			$result['end']=$result['begin']+$day->breaklength($this);
				    		break;
			    		}
		    			default:
		    			{
		    				$result['begin']=
		    					$this->get_startwork($day)+$this->get_global('converter')->convert_time('h', 's', $this->get_breakstart());
		    				$result['end']=$result['begin']+$day->breaklength($this);
		    			}
		    		}
	    		}
	    		else
	    		{	    			
	    			if ($this->has_misc2_privilege())
    				/*
    				 * on everyday always 1800 (misc2_breaklength)
    				 */	    				
	    			{
	    				$result['begin']=
		    				$this->get_startwork($day)+$this->get_global('converter')->convert_time('h', 's', $this->get_breakstart($day->today('N')));
		    			$result['end']=$result['begin']+$this->get_global('lib_human_resources')->select('misc2_breaklength');
	    			}
	    			else
	    			{
	    				switch ($day->today('N'))
	    				{
	    					/*
	    					 * only on Friday give everyone 5400 seconds, on fast 3600
	    					 */
	    					case 5:
    						{
   								$result['begin']=
   									$this->get_startwork($day)+$this->get_global('converter')->convert_time('h', 's', $this->get_breakstart());
    							$result['end']=$result['begin']+3600;
    							break;
    						}    						
	    					case 6:
	    					/*
	    					 * on Saturday always 3600
	    					 */
	    					{
    							$result['begin']=
    								$this->get_startwork($day)+$this->get_global('converter')->convert_time('h', 's', $this->get_breakstart(6));
    							$result['end']=$result['begin']+3600;
    							break;
    						}
    						/*
    						 * on every other days always 3600
    						 */
	    					default:
    						{
    							$result['begin']=
    								$this->get_startwork($day)+$this->get_global('converter')->convert_time('h', 's', $this->get_breakstart());
    							$result['end']=$result['begin']+3600;
    						}
	    				}	    					    				
	    			}
	    			
	    		}
	    	}
	    	
	    	return $result;
	    }	   
	    
	    
	    function add_fine(fine $fine)
	    /*
	     * This will add fine to the employee account
	     */
	    {
	    	$this->makeArray('fines');
	    	
	    	$this->addData('fines', $fine->select('uniqueid'));
	    }
	    
	    function is_fined($timestamp=null)
	    /*
	     * Used to check if the employee is currently under fine in a particular month
	     */
	    {
	    	if (!$timestamp)
	    		$timestamp=\date::today("timestamp");
	    		
	    	$result=false;
	    	
	    	foreach ($this->select('fines') as $fine_id)
	    	{
	    		$fine=$this->get_global('lib_management')->get_list("fines")->db_select($fine_id);
	    		
	    		if ($result=$fine->active($this->username, $timestamp))
	    			break;
	    	}
	    	
	    	return $result;
	    }
	    
	    function fine_installment($timestamp=null)
	    /*
	     * Used to get installment of fines in a particular month
	     */
	    {
	    	if (!$timestamp)
	    		$timestamp=\date::today("timestamp");
	    		
	    	$result=0;
	    		
	    	foreach ($this->select('fines') as $fine_id)
	    	{
	    		$fine=$this->get_global('lib_management')->get_list("fines")->db_select($fine_id);
	    		$result+=$fine->get_fine($this->username, $timestamp);	    		
	  		}
	  		
	   		return $result;	    
	    }
	    
	    function has_canape()
	    /*
	     * Canape only given to people in night shift
	     * and when the is specified to have canape or undefined canape
	     *
	     * so when true or null
	     */
	    {
	    	$result=false;
	    	
	    	if ($this->get_shift()!=='0')
	    	{
	    		if (($this->select('canape')=='1') ||
	    			(is_null($this->select('canape'))))
	    			$result=true;
	    	}
	    	
	    	return $result;
	    }
	    
	    function get_workdays(?int $time=null) : int
	    /*
	     * return the number of workdays for an employee
	     */
	    {	    	
	    	return $this->t_select($time, 'workdays') ?? 6;
	    }
	    
	    function undertime_status(day_data $day_data)
	    /*
	     * Return the status of undertime of that day
	     */
	    {
	    	$result=[];
	    	if ($day_data->get('undertime_auth_out', $this->get('username')))
	    	{
	    		$result['out']=$day_data->get('undertime_auth_out', $this->get('username'));
	    		
    			if ($day_data->get('undertime_auth_in', $this->get('username')))
    				$result['in']=$day_data->get('undertime_auth_in', $this->get('username'));    			
	    	}
	    			
    		return $result;
	    }
	    
	    function is_undertime_unchecked(day_data $day_data)
	    /*
	     * Used to ensure if undertime is not fully checked for that day
	     * e.g. out only but not in will return bad  
	     */
	    {
	    	$check=$this->undertime_status($day_data);
	    	
	    	return !isset($check['in']);
	    }
	}
	    
	class fine extends record
	/*
	 * A record of fine,
	 *
	 * On employee data add a link to this fine
	 */
	{
		function __construct($credentials, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, cManagementChkSec, $secure_db, $readonly_db, []);
			
			$this->makeArray("fined");
		}
				
		function active($timestamp)
		/*
		 * Used to check if the installment is active or not
		 */
		{						
			return $this->in($this->select('start'), $timestamp, 			
				$this->end_of_month(
					$this->change_month($this->select('term'), $this->select('start'), 'timestamp'), 'timestamp'));
		}
		
		function get_fine($username, $timestamp)
		{			
			return $this->active($timestamp) ? $this->select($username, 'installment') : 0;
		}
		
		function issue_fine($username, $value, $start, $term)
		/*
		 * Save installment fine to a user
		 * A user can have custom installment length
		 */
		{
			$this->addData($username, $value, 'total');
			$this->addData($username, $value/$term, 'installment');
						
			$this->addData('fined', $username);
		}
		
		function terms($timestamp)
		{			
			return (\date::month_difference($timestamp, $this->select('start'))+1)."/".$this->select('term');
		}
		
		function customer()
		{
			$customer=$this->get_global('contacts')->get_list('contact_list')->select($this->select('underlying', 'customer'));
			
			return $customer->getName();
		}
						
		static function installment_term()
		{
			$result=[];
			$result[1]=['value' => 1, 'display' => '1x'];
			$result[3]=['value' => 3, 'display' => '2x'];
			$result[6]=['value' => 6, 'display' => '6x'];
			$result[12]=['value' => 12, 'display' => '12x'];
			$result[18]=['value' => 18, 'display' => '18x'];
			$result[24]=['value' => 24, 'display' => '24x'];
			
			return $result;
		}
	}
?>