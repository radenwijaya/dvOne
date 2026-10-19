<?php
/*
 * collection of algorithm for doing tasks that might require it 
 */
	class lib_algorithms
	{
		static function knapsack10(int $W, $wt=[], $val=[])
		/*
		 * $W - is the weight limit
		 * $val is the value of items, array
		 * $wt is the weight of items, array
		 * 
		 * if the numberr of  $val is not the same as $wt, then will choose the smallest
		 * 
		 * https://www.geeksforgeeks.org/0-1-knapsack-problem-dp-10/
		 * This code is contributed by Sam007.
		 */
		{
			$n=min(count($wt), count($val));
			// Build table K[][] in
			// bottom up manner
			$K = [];
			$p_K=[];
			
			$R = [];
			$p_R=[];
			// Build table K[][] in
			// bottom up manner
			for ($i = 0; $i <= $n; $i++)
			{
				for ($w = 0; $w <= $W; $w++)
				{
					if ($i == 0 || $w == 0)
					{
						$K[$w]=0;
						$R[$w]=[];
					}
					else
						if ($wt[$i - 1] <= $w)
						{
							$a=$val[$i - 1] + $p_K[$w - $wt[$i - 1]];
							$b=$p_K[$w];
							if ($a<$b)
							{
								$K[$w] = $b;
								$R[$w]=$p_R[$w];
							}
							else
							{
								$K[$w] = $a;
								$R[$w]=[...$p_R[$w - $wt[$i - 1]], $i-1];
							}
						}
					else
					{
						$K[$w] = $p_K[$w];
						$R[$w] = $p_R[$w];
					}
				}
				$p_K=$K;
				$p_R=$R;
			}
			
/*			foreach ($K as $value)
			{
				echo("$value ");
			}
			echo("<BR>");
			
			foreach ($R as $value)
			{
				foreach ($value as $x)
				{
					echo($x);
				}
				echo('&nbsp');
			}*/
			
			return ['value' => $K[$W], 'choice' => $R[$W]];
		}
		
		static function combinatoric($count, $elements, &$result, $chain, $depth, &$no)
		/*
		 * Combinatoric used to take an array with count of each available there and output
		 * a sparse matrix containing all possible combinations of elements
		 * 
		 * n numbers of items into r numbers of available slot
		 *
		 * $count - max number of element required
		 * $depth - is element number that is used initialise with 0
		 * $elements - array of available elements
		 * $no - solution number start with 0
		 * $chain - initialise with blank array 
		 *
		 *
		 */
		{
			
			if (($count>0) && ($depth<count($elements)))
			{
				//				var_dump($depth);
				$mc=$elements[$depth]<$count ? $elements[$depth] : $count;
				if  ($depth==count($elements)-1)
				/*
				 * if on edge of the elements, then take it all
				 */
				{
					$result[$no][$depth]=$mc;
					$no++;
				}
				else
				{
					/*
					 * Determine the maximum number that can be taken
					 */
					for ($c=0; $c<=$mc; $c++)
					{
						if ($c>0)
							$result[$no]=[...$chain, $depth => $c];
						lib_algorithms::combinatoric($count-$c, $elements, $result, $result[$no] ?? [], $depth+1, $no);
					}
				}
			}
			else
			{
				$no++;
			}
		}
		
		static function comb($depth, $length, $chain, $max_depth, &$result)
		/*
		 * Start with depth = 0, 
		 * length and max_depth = desired length, could be 10
		 * result = empty array 
		 */
		{
			if ($depth<$max_depth)
			{
				for ($i=0; $i<=$length; $i++)
				{
					if ($i>0)
						$chain[$depth]=$i;
										
					lib_algorithms::comb($depth+1, $length-$i, $chain, $max_depth, $result);
				}
			}
			else
			{
//				for ($i=0; $i<$length; $i++)
				if ($length>0)
					$chain[$depth]=$length;
				
				$result[]=$chain;
			}
		}
	}
?>