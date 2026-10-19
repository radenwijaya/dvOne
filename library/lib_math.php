<?php	
	class math
	{
		use statistics;		
		use matrix;
		
		static function swap(&$a, &$b)
		/*
		 * Swapping number contents
		 */
		{
			$x=$a;
			
			$a=$b;
			$b=$x;
		}
	}
	
	class lib_math extends math
	{
		
	}
	
	final class stats
	/*
	 * Refactoring for a more direct and shorter to write version
	 * 
	 * trait statistic should call to this method
	 * 
	 * Remember: this is a more raw version.  Prior to calling this, it is recommended to call get_dataset from the trait stats
	 * 
	 * to clean and prepare the dataset
	 */
	{
		static function standard_deviation(array $a, $sample = false)
		{
			$n=count($a);
			if ($n === 0)
			{
				trigger_error("The array has zero elements", E_USER_WARNING);
				return false;
			}
			if ($sample && $n === 1)
			{
				trigger_error("The array has only 1 element", E_USER_WARNING);
				return false;
			}
			$mean = array_sum($a) / $n;
			$carry = 0.0;
			foreach ($a as $val)
			{
				$d = ((double) $val) - $mean;
				$carry += $d * $d;
			};
			if ($sample)
			{
				--$n;
			}
			return sqrt($carry / $n);
		}		
		
		static function mean(array $a)
		/*
		 * Average but no removal of outlier
		 */
		{			
			return empty($a) ? 0 : array_sum($a)/count($a);			
		}
		
		static function cv(array $a)
		/*
		 * Returns the coeficient variance in whole number percentage
		 * divide by 100 to make it percentage
		 */
		{
			$result=0;
			
			if (self::mean($a)>0)
				$result=100*self::standard_deviation($a)/self::mean($a);
				
			return $result;
		}
		
		static function outlier(array $a, array $quartile=[], $sorted=false)
		/*
		 * This will return which value is the outliers
		 * 
		 * It asumed data is unsorted unless $sorted is set to true
		 */
		{
			if (!$sorted) asort($a);
			
			if (empty($quartile))
				$quartile=self::quartile($a, true);
			
			$result=[];
			
			if (!empty($quartile))
			{
				foreach ($a as $key => $value)
				{
					$result[$key]=0;
					if ($value<$quartile['q1if'])
						$result[$key]=-1;
					if ($value<$quartile['q1of'])
						$result[$key]=-2;
					if ($quartile['q3if']<$value)
						$result[$key]=1;
					if ($quartile['q3of']<$value)
						$result[$key]=2;
				}
			}
			else
			{
				$median=self::median($a, true);
				foreach ($a as $key => $value)
				{
					$result[$key]=0;
					if ($value<$median*0.75)
						$result[$key]=-1;
					if (1.25*$median<$value)
						$result[$key]=1;
				}
			}
			
			return $result;
		}
		
		static function quartile(array $data, $sorted=false)
		/*
		 * This method will return the q1, q2, IQR, low outlier and high outlier
		 *
		 * It assumed data is unsorted		 
		 */
		{		
			$sd=self::standard_deviation($data);
			
			if (!$sorted) sort($data);
				
			$count=count($data);
			$result=array();
			if ($count>5)
			{
				if ($count % 2 == 0)
				{
					$half=$count/2;
				}
				else
				{
					$half=floor($count/2);
				}
				
				$median=self::median($data, true);
				$lower=self::median(array_slice($data, 0, $half), true);
				$upper=self::median(array_slice($data, -$half, $half), true);
				
				$iqr=$upper-$lower;
				
				$result=array('q1' => $lower, 'q3' => $upper, 'iqr' => $iqr,
					'q1if' => $lower-$iqr*1.5, 'q3if' => $upper+$iqr*1.5,
					'q1of' => $lower-$iqr*3, 'q3of' => $upper+$iqr*3,
					'median' => $median, 'sd' => $sd);
			}
			
			return $result;
		}
		
		static function median(array $median, $sorted=false)
		/*
		 * To use this, data must be preparred before processing
		 * 
		 * It assume data is unsorted, hence needs to be sorted
		 */
		{
			if (!$sorted) asort($median);
			
			$count=count($median);
			$result=null;
			$half=floor($count / 2);
			$keys=array_keys($median);
			
			if ($count>0)
			{
				if ($count==1)
					$result=$median[$keys[0]];
				else
					if ($count % 2 == 0)
					{
						$result=($median[$keys[$half]]+$median[$keys[$half+1]])/2;				
					}
					else
					{						
						$result=$median[$keys[$half+1]];
					}
			}
			
			return $result;
		}		
		
		static function average(array $a, $sorted=false)
		/*
		 * This will remove outlier, before calculating the mean 
		 */
		{			
			$mean=self::remove_outlier($a, $sorted);
			
			return empty($mean) ? 0 : array_sum($mean)/count($mean);
		}
		
		static function remove_zero(array $a)
		/*
		 * This will remove zero and null
		 */
		{
			$result=[];
			
			foreach ($a as $key => $value)
				if ($value)
					$result[$key]=$value;
				
			return $result;
		}
		
		static function remove_outlier(array $a, $sorted=false)
		/*
		 * This will remove the outliers
		 * 
		 * It assume data is unsorted, hence needs to be sorted
		 */
		{
			if (!$sorted) asort($a);
			$quartile=self::quartile($a, true);
			$result=array();
			
			if (!empty($quartile))
			{
				foreach ($a as $key => $value)
				{
					if (($quartile['q1if']<=$value) && ($value<=$quartile['q3if']))
						$result[$key]=$value;
				}
			}
			else
			{
				$result=$a;
			}
			
			return $result;
		}
		
		static function max(array $array)
		/*
		 * Returns the key and value of the largest of an array
		 */
		{
			$max=PHP_INT_MIN;
			$return_key=null;
			foreach ($array as $key => $element)
			{
				if ($element>=$max)
				{
					$return_key=$key;
					$max=$element;
				}
			}
			
			return ($return_key!==null) ? ['key' => $return_key, 'value' => $max] : null;
		}
		
		static function min(array $array)
		/*
		 * Returns the key and value of the smalles of an array
		 */
		{
			$max=PHP_INT_MAX;
			$return_key=null;
			foreach ($array as $key => $element)
			{
				if ($element<=$max)
				{
					$return_key=$key;
					$max=$element;
				}
			}
			
			return ($return_key!==null) ? ['key' => $return_key, 'value' => $max] : null;
		}
		
		static function modus(array $a)
		/*
		 * Finding the modus, and returns the moduls value
		 */
		{
			$values=[];
			foreach ($a as $value)
			{
				if (!isset($values["$value"]))
					$values["$value"]=1;
				else
					$values["$value"]++;
			}
			
			$key=self::max($values)['key'];
			
			return $key;			
		}
	}
	
	trait statistics
	{
		/*
		 * Statsitic function from this is designed to work on a class, therefore will always filter 
		 * the data set before calling the required stats class
		 */
		
		static function tends_scoring($range, $value, $tends)
		/*
		 * Scoring to the tends
		 *
		 * range can be a pair of min, max
		 * tends can be min or max, the closer to tends the better the score
		 */
		{
			$max_range=$range['max']-$range['min'];
			
			if ($max_range!=0)
			{
				if ($tends=='min')
					$position=$range['max']-$value;
				else
					$position=$value-$range['min'];
						
				$result=$position/$max_range;
			}
			
			return $result ?? 0;
		}
		
		static function balanced_scoring($min, $max, $value)
		/*
		 * Balanced scoring will return the closest to centre as better scored
		 */
		{
			$mid=0.5*($min+$max);
			
			if ($max!=$mid)
				$score=1-abs($value-$mid)/($max-$mid);
			else
				$score=0;
					
			return $score;
		}
		
		static function stats_standard_deviation(array $a, $sample = false)
		/*
		 * Alias to stats::standard_deviation
		 */
		{
			$dataset=self::stats_get_dataset($a);
			return stats::standard_deviation($dataset, $sample);
		}
		
		static function stats_get_dataset($a, $mode='value', $function='toString', ...$function_params)
		/*
		 * This will clean the dataset or getting dataset for other stats function from an s_lib object
		 */
		{
			$dataset=array();
			$i=1;
			switch ($mode)
			{
				case 'value':					
					foreach ($a as $value)
					{
						if (($value!='') && (!is_array($value)) && (!is_nan($value)))
						{
							$dataset[$i]=$value;
							$i++;
						}
//						if (is_array($value)) $value=$value+2;;
					}
					break;
				case 'key':
					$dataset=array_keys($a);
					break;
				default:
					foreach ($a as $value)
					{
						if (($value instanceof s_lib) && (method_exists($value, $function)))
						{
							$dataset[]=$value->$function(...$function_params);
						}
					}
					break;
			}
			
			return $dataset;
		}
		
		static function stats_average($a, $mode='value', $function='toString', ...$function_params)
		/*
		 * Removal of outlier before averaging
		 */
		{
			$dataset=self::stats_get_dataset($a, $mode, $function, ...$function_params);
								
			return stats::average($dataset);
		}
		
		static function max(array $array)
		{
			return stats::max($array);
		}
		
		static function min($array)
		{
			return stats::min($array);
		}
		
		
		static function stats_modus($a=[])
		{
			return stats::modus($a);
		}
		
		static function stats_mean($a)
		/*
		 * Alias to stats::mean
		 */
		{
			return stats::mean($a);
		}
		
		static function stats_cv($a)
		/*
		 * Alias to stats::cv
		 */
		{
			return stats::cv($a);
		}
		
		function stats_median($a, $mode='value', $function='toString', ...$function_params)
		{
			$a=$this->stat_remove_outlier($a, $mode, $function, ...$function_params);
			
			return $this->stat_median($a, $mode, $function, ...$function_params);
		}
		
		static function stat_median($a, $mode='value', $function='toString', ...$function_params)
		{
			return stats::median($a);
		}
		
		static function stat_quartile(array $a, $mode='value', $function='toString', ...$function_params)
		/*
		 * This method will return the q1, q2, IQR, low outlier and high outlier 
		 * 
		 * NOTE: Data must be sorted before calling this method
		 */
		{
			$data=self::stats_get_dataset($a, $mode, $function, ...$function_params);
			
			return stats::quartile($data);
		}
		
		static function stat_remove_outlier(array $a, $mode='value', $function='toString', ...$function_params)
		{
			sort($a);
			$quartile=self::stat_quartile($a, $mode, $function, ...$function_params);
			$result=array();
			
			if (!empty($quartile))
			{
				foreach ($a as $value)
				{
					if (($quartile['q1if']<=$value) && ($value<=$quartile['q3if']))
						$result[]=$value;
				}
			}
			else
			{
				$result=self::stats_get_dataset($a, $mode, $function, ...$function_params);
			}
			
			return $result;
		}
				
		static function stat_remove_deviated(array $a, $mode='value', $function='toString', ...$function_params)
		{
			$data=self::stat_remove_outlier($a);
			$quartile=self::stat_quartile($data);
			
			$mean=self::stats_mean($data);
			
			if (!empty($quartile))
			{
				foreach ($data as $value)
				{
					if (($mean-$quartile['sd']<=$value) && ($value<=$mean+$quartile['sd']))
						$result[]=$value;
				}
			}
			else
			{
				$result=self::stats_get_dataset($a, $mode, $function, ...$function_params);
			}
			
			return $result;
		}
		
		static function stat_outlier(array $a, $mode='value', $function='toString', ...$function_params)
		/*
		 * This will return which value is the outliers
		 */
		{
			$datasets=self::stats_get_dataset($a, $mode, $function, ...$function_params);
			
			return stats::outlier($datasets);
		}		
				
		static function stats_QR(array $a, $limit)
		/*
		 * Finding ratio of that is lower than a value
		 */
		{
			$total=count($a);
			$c=0;
			foreach ($a as $v)
				if ($v<$limit)
					$c++;
				
			return ($total!=0) ? $c/$total : 0;
		}
		
		static function stats_outstandard($standard, $fence, array $a)
		/*
		 * Finding the count of outstandard out of the fence  
		 * fence in percentage
		 */
		{			
			$c=0;
			
			foreach ($a as $v)
				if (!s_lib::in($standard-$fence*$standard/100, $v, $standard+$fence*$standard/100))
					$c++;
					
			return $c;
		}
		
		static function stats_trend($values) : ?int 
		/*
		 * This will check trend of a value, whether its uptrend or downtrend
		 */
		{
			$v=array_values($values);
			
			$sd=self::stats_standard_deviation($v);
				
			$x=[];
			for ($i=0; $i<count($v)-1; $i++)
			{
				$x[]=$v[$i+1]-$v[$i];
			} 
								
			$mean=self::stats_mean($x);
			
			$result = null;
			switch (true)
			{
				case ($mean<-$sd): //downtrend
					$result=-1;
					break;
				case (-$sd<=$mean) && ($mean<=$sd): //sideways
					$result=0;
					break;
				case ($mean>$sd):
					$result=1;
					break;
			}
				
			return $result;
		}
		
		static function intervals($array)
		{
			$keys=array_keys($array);
			
			$result=[];
			for ($i=1; $i<count($keys); $i++)
			{
				$result[]=$array[$keys[$i]]-$array[$keys[$i-1]];
			}
			 
			return $result;
		}
		
		static function stats_range($value, $pivot, $deviation): int
		/*
		 * This will in range, if within deviation of pivots, will return 0
		 * otherrwise return -1 or +1 for smaller or bigger respectively 
		 */
		{
			$result=null;
			
			switch (true)
			{
				case ($value<$pivot-$deviation/2):
					$result=-1;
					break;
				case ($pivot+$deviation/2<$value):
					$result=1;
					break;
				default:
					$result=0;
					break;
			}
			
			return $result;
		}
	}	
	
	trait loose_math
	{
		static function round($x, $factor=1)
		/*
		 * Rounding to a factor, e.g. 5, then it will round to the nearest 5
		 * just like how php behave with negative precision
		 */
		{
			$result=round($x/$factor)*$factor;
			
			return $result;
		}
	}
	
	trait matrix
	{
		static function transpose($matrix)
		/*
		 * Transposes array matrix 
		 */
		{
			$result=[];
			foreach ($matrix as $r => $row)
			{
				foreach ($row as $c => $column)
				{
					$result[$c][$r]=$column;
				}
			}
			
			return $result;
		}
	}
?>