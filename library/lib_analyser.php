<?php
	include_once("lib_baseclass.php");

	class linear_analysis extends s_lib
	{
		var $data;
		var $data_fog;
		var $matrix_function;
		var $matrix;
		
		function __construct($matrix_function)
		{
			$this->matrix=array();
			$this->data=array();
			$this->data_fog=array();
			
			$this->matrix_function=$matrix_function;
		}
		
		function add(float $x, float $y)
		{ 
			$this->data_fog["$x"][]=$y;
		}
		
		function condense_data()
		{
			$this->data=array();
			ksort($this->data_fog);
			
			foreach ($this->data_fog as $x => $fog)
			{
				sort($fog);
				$clean_fog=$this->stat_remove_outlier($fog);
				
				$y=$this->stats_average($clean_fog);
				
				$this->data[]=array('x' => $x, 'y' => $y);
			}
			
			return $this->data;
		}
		
		function to_matrix()
		{
			$m_function=$this->matrix_function;
			
			$this->condense_data();
			$this->matrix=array();
			
			$count=count($this->data);
			
			for ($i=0; $i<$count; $i++)
			{
				for ($j=$i+1; $j<$count; $j++)
				{
					$m_result=$this->$m_function
					($this->data[$i], $this->data[$j]);
					
					if ($m_result) $this->matrix[]=$m_result;
				}
			}
			
			return $this->matrix;
		}
		
		function find_eq($point_1, $point_2)
		{
			$result='';
			
			$m=($point_2['y']-$point_1['y'])/($point_2['x']-$point_1['x']);
			
			$c=$point_1['y'];
			
			if (((!is_infinite($m)) && (!is_nan($m))) &&
				((!is_infinite($c)) && (!is_nan($c))))
				$result=array('m' => $m, 'c' => $c);
				
			return $result;
		}
		
		function find_logeq($point_1, $point_2)
		{
			$result='';
			
			if (is_infinite($point_1['x']))	$point_1['x']=PHP_INT_MAX;
			if (is_infinite($point_2['x']))	$point_2['x']=PHP_INT_MAX;
			if (is_infinite($point_1['y']))	$point_1['y']=PHP_INT_MAX;
			if (is_infinite($point_2['y']))	$point_2['y']=PHP_INT_MAX;
			
			if (($point_1['y']-$point_2['y']!=0) && ($point_2['x']**$point_1['y']!=0))
			{
				$b=(($point_1['x']**$point_2['y'])/($point_2['x']**$point_1['y']))**(1/($point_1['y']-$point_2['y']));
				
				if ($point_1['y']!=0)
				{
					$a=exp(log($b*$point_1['x'])/$point_1['y']);
					
					if (((!is_infinite($a)) && (!is_nan($a))) &&
						((!is_infinite($b)) && (!is_nan($b))))
						$result=array('a' => $a, 'b' => $b);
				}
			}
			
			return $result;
		}
		
		function find_y($matrix, $x)
		{
			$result=0;
			switch ($this->matrix_function)
			{
				case 'find_logeq':
					$result=log($matrix['b']*$x, $matrix['a']);
					break;
				case 'find_eq':
					$result=$matrix['m']*$x+$matrix['c'];
					break;
				default:
					$result= (float) 'NaN';
					break;
			}
			
			return $result;
		}
		
		function average_eq()
		/*
		 * This method is completely wrong logic
		 */
		{
			$m=array();
			$c=array();
			$count=0;
			foreach ($this->matrix as $value)
			{
				$m[]=$value['m'];
				$c[]=$value['c'];
			}
			
			sort($m);
			sort($c);
			
			$m=$this->stat_remove_outlier($m);
			$c=$this->stat_remove_outlier($c);
			
			$result=(!is_nan($count)) ? array('m' => $this->stats_average($m), 'c' => $this->stats_average($c)) : '';
			
			return $result;
		}
		
		function estimate_y($x)
		{
			/*
			 * find all value for all
			 */
			$y_array=array();
			foreach ($this->matrix as $m)
			{
				if (($m['a'] > 0) && ($m['b'] > 0))
					$y_array[]=$this->find_y($m, $x);
			}
			sort($y_array);
			$y_array_clean=$this->stat_remove_outlier($y_array);
			
			$result=$this->stats_average($y_array_clean);
			
			return $result;
		}
	}

?>