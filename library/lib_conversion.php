<?php
	class yarn_conversion extends conversion
	{
		var $s_conversion_table;
		
		function s_conversion_table()
		{
			$conversion_array=array();
			
			$conversion_array['kg']=array();
			$conversion_array['kg']['1s']=1693;

			$conversion_array['1s']=array();			
			$conversion_array['1s']['7s']=7;
			$conversion_array['1s']['8s']=8;
			$conversion_array['1s']['10s']=10;
			$conversion_array['1s']['18s']=18;
			$conversion_array['1s']['20s']=20;
			$conversion_array['1s']['30s']=30;
			$conversion_array['1s']['32s']=32;
			$conversion_array['1s']['40s']=40;
			$conversion_array['1s']['50s']=50;
			$conversion_array['1s']['60s']=60;
			
			return conversion::duplicate_array($conversion_array);			
		}
		
		function __construct()
		{
			parent::__construct('yarn_converter');
			
			$this->s_conversion_table=$this->s_conversion_table();
			$this->to_global('yarn_converter');
		}
		
		function s_metre_to_mass($s_size, $to, $value)
		/*input s_size (metre of) 30s, to kg, value 50000*/
		{			
			$in_kg=$this->conversion_factor($s_size, 'kg', 's_conversion_table')*$value;			

			return $this->convert_mass('kg', $to, $in_kg);
		}
		
		function s_kilogram_to_length($s_size, $to, $value)
		/*input s_size (kilogram of) 30s, to metre, value 2*/
		{
			$in_metre=$this->conversion_factor('kg', $s_size, 's_conversion_table')*$value;
			
			return $this->convert_length('m', $to, $in_metre);
		}
		
		function reeds_to_fabrics_table()
		{
			$conversion_array=array();
			
			$conversion_array['CT_30s']=array();
			$conversion_array['CT_30s'][66]=70;
			$conversion_array['CT_30s'][70]=72;
			$conversion_array['CT_30s'][80]=84;
			$conversion_array['CT_30s'][85]=90;
			
			$conversion_array['CT32s']=array();
			$conversion_array['CT32s'][64]=68;
			$conversion_array['CT32s'][66]=70;
			$conversion_array['CT32s'][70]=72;
			$conversion_array['CT32s'][80]=84;
			$conversion_array['CT32s'][85]=90;
			
			$conversion_array['CT_40s']=array();
			$conversion_array['CT_40s'][85]=90;
			$conversion_array['CT_40s'][90]=94;
			
			$conversion_array['RY_30s']=array();
			$conversion_array['RY_30s'][64]=68;
			$conversion_array['RY_30s'][68]=74;
			$conversion_array['RY_30s'][76]=80;
			$conversion_array['RY_30s'][80]=84;
			
			return $conversion_array;
		}		
		
		function picks_to_fabrics_table()
		{
			$conversion_array=array();
			
			$conversion_array['CT_30s']=array();
			$conversion_array['CT_30s'][54]=56;
			$conversion_array['CT_30s'][58]=60;
			
			$conversion_array['CT_32s']=array();
			$conversion_array['CT_32s'][54]=56;
			$conversion_array['CT_32s'][56]=56;
			$conversion_array['CT_32s'][58]=60;			
			
			$conversion_array['CT_40s']=array();
			$conversion_array['CT_40s'][70]=70;
			$conversion_array['CT_40s'][76]=76;
			
			$conversion_array['RY_30s']=array();
			$conversion_array['RY_30s'][56]=56;
			
			return $conversion_array;
		}
				
		function convert_to_fabrics($weft='ends', $yarn, $value)
		{
			switch ($weft)
			{
				case 'ends': $conversion_array=$this->reeds_to_fabrics_table(); break;
				case 'picks': $conversion_array=$this->picks_to_fabrics_table(); break;
			}
									
			if ($conversion_array[$yarn][$value]) $result=$conversion_array[$yarn][$value];
			else $result=$value;
			
			return $result;
		}
		
		function staple_conversion($input, $pattern)
		/*
		 * Input: string 1 x/32 or number
		 * Output: in mili metre
		 */
		{
			$matches=[];
			$result=0;
			
			if (preg_match('/'.$pattern.'/', $input, $matches))
			{
				if ($matches['literal'])
					$result=$this->convert_length('inch', 'mm', $matches['literal']/32);
				else
					$result=$this->convert_length('inch', 'mm', 1+$matches['num']/$matches['denom']);					
			}
				
			return $result;
		}
		
	}
?>