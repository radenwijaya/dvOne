<?php
//	include_once('lib_baseclass.php');
//	include_once('lib_base_list.php');
	
	class currency_conversion extends conversion
	/*
	 * Currency rate as per day
	 */
	{
		var $exchange_table;
		function __construct()
		{
			$this->exchange_table=array('IDR' => array(), 'USD' => array(), 'CNY' => array());
			
			$this->exchange_table['USD']['IDR']=13500;
			$this->exchange_table['CNY']['IDR']=2200;
			
			$this->exchange_table['IDR']['USD']=1/$this->exchange_table['USD']['IDR'];
			$this->exchange_table['IDR']['CNY']=1/$this->exchange_table['CNY']['IDR'];
		}
		
		function exchange($from, $to)
		{
			return $this->conversion_factor($from, $to, 'exchange_table');
		}
		
		function add_conversion_unit($from, $to, $conversion_factor, $exchange_table='exchange_table')
		{
			parent::add_conversion_unit($from, $to, $conversion_factor, $exchange_table);
		}
	}
	
	class currency extends entity
	{
		var $data;
		var $sorted;
		
		function __construct($locale, $code, $symbol, $name)
		{
			parent::__construct();			
			$this->addData('locale', $locale, $locale);
			$this->addData('code', $code);
			$this->addData('symbol', $symbol);
			$this->addData('name', $name);
			
			$this->id_field='code';
			$this->name_field='name';
			
			$this->addData($code, 1, \date::today());//self exchange
		}
		
		function set_rate($code, $value)
		//obsolete
		{
			$this->addData($code, $value, \date::today());
		}
		
		function set_historic_rate($date, $code, $value)
		//obsolete
		{
			$this->addData($code, $value, $date);
			
			$this->ordered=false;
		}
		
		function rate($code, $date='')
		//obsolete
		{
			if (!$date)	$date=\date::today();
			
			$latest_rate=0;
			if (!empty($this->data[$code]))
			{
				if (!$this->ordered)
				{
					$this->ordered=true;
					krsort($this->data[$code]);
				}
			
				$latest_rate=$this->select($code, $date);
				
				if (!$latest_rate)
				{					
					$rates=$this->select($code);
					$latest_rate=$this->get_closest_value($date, $rates, 'date', 'date_string_difference');
					
					//var_dump($rates);
					//$latest_rate=reset($rates);					
				}
			}
			
			return $latest_rate;
		}
	}
	
	class exchange extends s_lib
	/*
	 * Exchange has multiple currency converter with different date / time as 
	 * currency value changes everyday
	 */
	{
		var $currencies;
		var $primary_currency;
		var $locale_list;
		
		var $currency_conversion;
		var $currency_conversion_sorted;
		
		function __construct($primary_currency='IDR')
		{						
			$this->currencies=new advanced_list();
			$this->locale_list=array();
			$this->primary_currency=$primary_currency;
			$this->currency_conversion=array();
			$this->currency_conversion_sorted=false;
			
			$this->add_currency('id_ID', 'IDR', 'Rp', 'Indonesia Rupiah');
			$this->add_currency('en_US', 'USD', '$', 'US Dollar');			
			$this->add_currency('zh_CN', 'CNY', 'Y', 'Renminbi');
			
			//$this->set_rate('USD', 13500, 'IDR');
			//$this->set_rate('CNY', 2000, 'IDR');			
		}
		
		static function locale_to_code($locale)
		{	
			if (!$locale) $locale=default_locale;
			$result='';
			
			$locale_array=explode('_', $locale);
			
			switch ($locale_array[0])
			{
				case 'id': $result='IDR'; break;
				case 'en': $result='USD'; break;
				case 'zh': $result='CNY'; break;
			}
			
			return $result;
		}
		
		function add_currency($locale, $code, $symbol, $name)
		{
			$currency=new currency($locale, $code, $symbol, $name);
			$this->currencies->add($currency);			
			
			$this->locale_list[$locale]=$code;
		}
		
		function select_currency($locale)
		{
			return $this->locale_list[$locale];
		}
		
		function historic_rate($date, $currencyA, $currencyB='')
		{			
			if (!$currencyA)
				$currencyA=$this->primary_currency;
			else				
				if ($this->locale_list[$currencyA])
					$currencyA=$this->locale_list[$currencyA];
				
			if (!$currencyB)
				$currencyB=$this->primary_currency;
			else
				if ($this->locale_list[$currencyB])
					$currencyB=$this->locale_list[$currencyB];
										
//			$currency=$this->currencies->select($currencyA);
//			$rate=$currency->rate($currencyB, $date);
			
			if (!$this->currency_conversion_sorted)
			{
				krsort($this->currency_conversion);
				$this->currency_conversion_sorted=true;
			}
						
			$latest_converter=$this->get_closest_value($date, $this->currency_conversion, 'date', 'date_string_difference');
						
			$rate=$latest_converter->exchange($currencyA, $currencyB);
/*			if (!$rate)
			{
				switch ($currencyA)
				{
					case 'USD': if ($currencyB=='IDR') return $rate=13500; break;
					case 'CNY': if ($currencyB=='IDR') return $rate=2200; break;
				}
			}*/
			
			return $rate;							
		}
					
		function rate($currencyA, $currencyB='')
		{
			return $this->historic_rate(\date::today(), $currencyA, $currencyB);
		}
		
		function set_rate($currencyA, $value, $currencyB='')
		{
			$this->set_historic_rate(\date::today(), $currencyA, $value, $currencyB);
			/*
			if (!$currencyA)
				$currencyA=$this->primary_currency;
			else				
				if ($this->locale_list[$currencyA])
					$currencyA=$this->locale_list[$currencyA];
				
			if (!$currencyB)
				$currencyB=$this->primary_currency;
			else
				if ($this->locale_list[$currencyB])
					$currencyB=$this->locale_list[$currencyB];
							
//			$currency=$this->currencies->select($currencyA);
//			$currency->set_rate($currencyB, $value);
			
//			$currency=$this->currencies->select($currencyB);
//			$currency->set_rate($currencyA, 1/$value);			
			
			$date=$this->today();
			if (!$this->currency_conversion[$date])
				$this->currency_conversion[$date]=new currency_conversion();
			
			$this->currency_conversion[$date]->add_conversion_unit($currencyA, $currencyB, $value);
			$this->currency_conversion_sorted=false;*/
		}
		
		function set_historic_rate($date, $currencyA, $value, $currencyB='')
		{
			if (!$currencyA)
				$currencyA=$this->primary_currency;
			else
				if (isset($this->locale_list[$currencyA]))
					$currencyA=$this->locale_list[$currencyA];
				
			if (!$currencyB)
				$currencyB=$this->primary_currency;
			else
				if (isset($this->locale_list[$currencyB]))
					$currencyB=$this->locale_list[$currencyB];
				
//			$currency=$this->currencies->select($currencyA);
//			$currency->set_historic_rate($date, $currencyB, $value);
					
//			$currency=$this->currencies->select($currencyB);
//			$currency->set_historic_rate($date, $currencyA, 1/$value);
			
			if (!isset($this->currency_conversion[$date]))
				$this->currency_conversion[$date]=new currency_conversion();
			
			$this->currency_conversion[$date]->add_conversion_unit($currencyA, $currencyB, $value);
			$this->currency_conversion_sorted=false;
		}
				
		function supported_currencies()
		{
			foreach ($this->currencies as $currency)
			{
				$locales=$currency->select('locale');
				$locale=array_pop($locales);
				$result[]=array('value' => $locale, 'display' => $currency->select('code'));
			}
			return $result;
		}
		
		function change($value, $currencyA, $currencyB)
		{
			return $this->rate($currencyA, $currencyB)*$value;
		}
	}
	
	class payment_methods
	{
		static function get_method($exempt=array())
		//exempt is the exemption that need not be available, 
		//write as 'key'
		{
			$methods=
				array(
					array('value' => 'transfer', 'display' => 'Transfer'),
			 		array('value' => 'cash', 'display' => 'Cash'),
					array('value' => 'cheque', 'display' => 'Cek / BG'),
					array('value' => 'edc', 'display' => 'Kartu Kredit'),
					array('value' => 'marketplace', 'display' => 'Marketplace'),
					array('value' => 'warranty', 'display' => 'Garansi'),
					array('value' => 'discount', 'display' => 'Diskon'),
					array('value' => 'cancel', 'display' => 'Batal')
				);

			$exempt_key=array_flip($exempt);

			foreach ($methods as $method)
			{
				if (is_null($exempt_key[$method['value']]))
				{
					$result[]=$method;
				}
			}
			
			return $result;
		}
		
		static function asArray($exempt)
		{
			$methods=self::get_method($exempt);
			$result=array();
			foreach ($methods as $method)
			{
				$result[$method['value']]=$method['display'];
			}
			
			return $result;
		}
	}
	
	class taxes extends s_lib
	{
		var $taxrate;
		
		var $actual_taxrate;
		
		function __construct()
		{
			$this->taxrate=
				array('tax_01' => array('name' => 'Bea Masuk', 'value' => 0.075, 'deductible' => false),
					  'tax_02' => array('name' => 'PPN', 'value' => 0.10, 'deductible' => true),
					  'tax_03' => array('name' => 'PPH', 'value' => 0.10, 'deductible' => true));
			
			$this->taxrate['tax_01']['import']['effective_taxrate']=new entity();
			$this->taxrate['tax_02']['import']['effective_taxrate']=new entity();
			$this->taxrate['tax_03']['import']['effective_taxrate']=new entity();

			$this->taxrate['tax_01']['local']['effective_taxrate']=new entity();
			$this->taxrate['tax_02']['local']['effective_taxrate']=new entity();
			$this->taxrate['tax_03']['local']['effective_taxrate']=new entity();			
		}
		
		function add_item_taxrate($tax, $currency, $item, $date, $rate)
		{
			$source='import';
			if ($currency==default_locale) $source='local';
			
			$this->taxrate[$tax][$source]['effective_taxrate']->addData($item, $rate, $date);
			$this->taxrate[$tax][$source]['effective_taxrate']->sort($item);
		}
		
		function effective_tax_rate($tax, $currency, $item, $date='')
		{
			if (!$date) $date=\date::today();
			$source='import';
			if ($currency==default_locale) $source='local';
			
			$effective_rate=0;
			
			if ($this->tax_dialect_property($currency, $tax, 'rate'))
			{
				$effective_rate=$this->taxrate[$tax][$source]['effective_taxrate']->select($item, $date);
				if (!$effective_rate)
				{
					$rates=$this->taxrate[$tax][$source]['effective_taxrate']->select($item);
								
					if (is_array($rates)) $effective_rate=end($rates);
					else $effective_rate=$this->tax($tax);
				}
			}
			
			return $effective_rate;
		}
		
		function get_tax()
		{
			return $this->taxrate;
		}
		
		function asArray()
		{
			$taxe=self::get_tax();
			$result=array();
			foreach ($taxes as $tax)
			{
				$result[$tax['name']]=$tax['value'];
			}
			return $result;
		}
		
		function tax($type, $value='value')
		{
			return $this->get_tax()[$type][$value];
		}
		
		function aftertax_to_price($aftertax, $tax)
		{
			$result=$aftertax/(1+$this->tax($tax, 'value'));
			return $result;
		}
		
		function price_to_taxprice($price, $tax)
		{
			$result=$price*(1+$this->tax($tax, 'value'));
			return $result;
		}
		
		function aftertax_to_tax($aftertax, $tax)
		{
			$result=$aftertax*($this->tax($tax, 'value'))/(1+$this->tax($tax, 'value'));
			return $result;
		}
		
		function beforetax_to_tax($price, $tax)
		{
			$result=$price*$this->tax($tax, 'value');
			return $result;
		}
		
		function tax_rate($type)
		{
			$result=number_format($this->tax($type, 'value')*100, 2).'%';
			return $result;
		}
		
		function update_rate($name, $value)
		{
			$this->taxrate[$name]['value']=$value;
		}
		
		function non_deductible()
		{
			$result=array();
			foreach ($this->taxrate as $tax => $properties)
				if (!$properties['deductible'])
					$result[]=$tax;
				
			return $result;
		}
				
		function tax_dialect($currency)
		{
			$result='';
			
			$source='import';
			if ($currency==default_locale) $source='local';
			
			switch ($source)
			{
				case 'import':
					$result=array('tax_01' => array('additive' => array('tax_02' => true, 'tax_03' => true), 'rate' => 'tax_rate_01'),
								  'tax_02' => array('rate' =>'tax_rate_02'),
								  'tax_03' => array('rate' =>'tax_rate_03'));
					break;
				case 'local':
					$result=array('tax_01' => array('rate' => ''),
								  'tax_02' => array('rate' => 'tax_rate_02'),
								  'tax_03' => array('rate' => ''));
					break;
			}
			
			return $result;
		}
		
		function tax_dialect_property($source, $tax, $property)
		{			
			$tax_dialect=$this->tax_dialect($source);
			
			return $tax_dialect[$tax][$property];
		}
		
		function calculate_complete_tax($base_value, $item, $currency, $taxes)
		{
			$result='';
			//$tax_rate=array(1 => $this->select('tax_01_rate'), 2 => $this->select('tax_02_rate'), 3 => $this->select('tax_03_rate'));
			
			$tax_value=array();
			$additive_tax=array();
			$tax_number=0;
			foreach ($taxes as $tax => $condition)
			{
				$tax_number++;
				
				$tax_base=$base_value;
					
				foreach ($additive_tax as $addon_tax => $addon_value)
				{
					if ($taxes[$addon_tax]['additive'][$tax])
						$tax_base+=$addon_value;
				}
					
				$current_tax=$tax_base*$this->effective_tax_rate($tax, $currency, $item);
					
					//echo("$tax base $base_value tax base $tax_base current $current_tax taxrate ".$this->select($condition['rate'])."<br>");
				if ($condition['additive'])
				{
					$additive_tax[$tax]=$current_tax;
				}
					
				$tax_value[$tax]=$current_tax;				
			}
			
			return $tax_value;
		}
	}
	
	class terbilang
	{
		static function group($unit)
		{
			$result='';
			switch ($unit)
			{
				case 1: $result='ribu'; break;
				case 2: $result='juta'; break;
				case 3: $result='milyar'; break;
				case 4: $result='triliun'; break;
			}
			return $result;
		}
		
		static function digits($value)
		{
			$result='';
			switch ($value)
			{
				case 0: $result='nol'; break;
				case 1: $result='satu'; break;
				case 2: $result='dua'; break;
				case 3: $result='tiga'; break;
				case 4: $result='empat'; break;
				case 5: $result='lima'; break;
				case 6: $result='enam'; break;
				case 7: $result='tujuh'; break;
				case 8: $result='delapan'; break;
				case 9: $result='sembilan'; break;
				
			}
			return $result;
		}
		
		static function teens($value)
		{
			$result=array();
			
			if (($value>0) && ($value<10))
				$result[]=terbilang::digits($value);
				elseif ($value==10)
				$result[]='sepuluh';
				elseif ($value==11) $result[]='sebelas';
				elseif (($value>=12) && ($value<=19))
				{
					$result[]=terbilang::digits($value % 10);
					$result[]='belas';
				}
				elseif ($value>=20)
				{
					$result[]=terbilang::digits(floor($value / 10));
					$result[]='puluh';
					
					if ($value % 10>0)
						$result[]=terbilang::digits($value % 10);
				}
				
				return $result;
		}
		
		static function hundreads($value)
		{
			$result=array();
			
			if ($value>99)
			{
				if (($value>=100) && ($value<200))
					$result[]='seratus';
					else
					{
						$result[]=terbilang::digits(floor($value/100));
						$result[]='ratus';
					}
			}
			
			if ($value % 100 !=0)
				$result=array_merge($result, terbilang::teens($value % 100));
				
				return $result;
		}
		
		static function spell($value, $unit=0)
		{
			$result=array();
			if (($unit==0) && ($value==0))
				$result[]=terbilang::digits(0);
				
				if ($value>0)
				{
					if (($unit==1) && ($value % 1000==1))
						$result[]='seribu';
						else
						{
							if ($value % 1000>0)
							{
								$result=array_merge($result, terbilang::hundreads($value % 1000));
								$result[]=terbilang::group($unit);
							}
						}
						
						$result=array_merge(terbilang::spell(floor($value / 1000), $unit+1), $result);
				}
				
				return $result;
		}
		
		static function spell_currency($value)
		{
			$value=round($value, 2);
			$array=explode('.', $value);
			
			$result=terbilang::spell($array[0]);
			$result[]='rupiah';
			
			if ($array[1]==0)
			{
				//$result[]='saja';
			}
			else
			{
				if ($array[1]<10)
					$array[1]=$array[1]*10;
					
					$result=array_merge($result, terbilang::spell($array[1]));
					$result[]='sen';
			}
			
			return ucwords(implode(' ', $result));
		}
	}	
	
	class payment_gateways
	{
		static function gateway_constants()
		{
			$result=array();
			if (paypal_sandbox)
			{
				$result['paypal']=
				array('oauth_server' => "https://api.sandbox.paypal.com/v1/oauth2/token",
					  'payment_server' => 'https://api.sandbox.paypal.com/v1/payments/payment',
					  'authorise_server' => 'https://api.sandbox.paypal.com/v1/payments/payment/<payment_id>/execute/',
					  'payment_creator' => 'https://www.ptgoldensun.com/dv1/paypal_create_payment.php',
					  'payment_authorise' => 'https://www.ptgoldensun.com/dv1/paypal_authorise_payment.php',
					  'payment_display' => 'https://www.ptgoldensun.com/dv1/self_pay_invoice.php',
					  'client_id' => 'AeyIVjH5UGuYGNjqhSFPAAE5XLsvYf5wul4Um21VYqqYqci8XIPD-t0pREeT_Jg-8S1hZwufSthtVjU1',
					  'client_secret' => 'EHZc1fQYDgyG7K81_kE5YSBLKvrZ_qeII7pR6ojt_arW9kIHOlIbQkYp3otG4jHz9eRmHVe_5SB1z4LN');
			}
			
			return $result;
		}
		
		static function supported_gateways()
		{
			return array_keys(self::gateway_constants());
		}
		
		static function select_payment_creator($gateway='paypal')
		{			
			return self::gateway_constants()[$gateway]['payment_creator'];
		}
		
		static function select_authorise_server($payment_id, $gateway='paypal')
		{
			$server=self::gateway_constants()[$gateway]['authorise_server'];
			return str_replace('<payment_id>', $payment_id, $server);
		}
		
		static function select($field, $gateway='paypal')
		{
			return self::gateway_constants()[$gateway][$field];			
		}
		
		static function render_script_location($gateway='paypal')
		{
			$result='';
			switch ($gateway)
			{
				case 'paypal':
					$result='<script src="https://www.paypalobjects.com/api/checkout.js"></script>';
					break;
			}
			
			return $result;						
		}
		
		static function render_mainscript($payment_url, $authorisation_url, $gateway='paypal')
		{
			$result='';
			switch ($gateway)
			{
				case 'paypal':
					$result=
					'<script>
					paypal.Button.render({
						
						env: \'sandbox\', // sandbox | production
						
						commit: true,
										
						payment: function() {
							var CREATE_URL = \''.$payment_url.'\';					
							return paypal.request.post(CREATE_URL)
							.then(function(res) {
								return res.paymentID;
							});
						},
										
						onAuthorize: function(data, actions) {										
							var EXECUTE_URL = \''.$authorisation_url.'\';
							
							var data = {
								paymentID: data.paymentID,
								payerID: data.payerID
							};
												
							return paypal.request.post(EXECUTE_URL, data)
							.then(function (res) {
								window.alert(\'Payment Complete!\');
							});
						}
						
					}, \'#paypal-button-container\');
					</script>';
					break;
			}
			
			return $result;
		}
		
		static function render_button($gateway='paypal')
		{
			$result='';
			switch ($gateway)
			{
				case 'paypal': 
					$result='<div id="paypal-button-container"></div>';
					break;
			}
			
			return $result;
		}
	}
	
	class finance
	{
		static function money_format(string $locale, float $number, $curr=''): string
		/* 
		 * Set curr to blank for automatic
		 */
		{									
			$fmt = new NumberFormatter($locale, NumberFormatter::CURRENCY);
			
			if ($curr=='')
				$curr=$fmt->getSymbol(NumberFormatter::CURRENCY_CODE);
				
			return $fmt->formatCurrency($fmt, $curr);
		}
		
		static function money_to_string(string $locale, string $value, &$curr): float
		{
			$fmt = new NumberFormatter($locale, NumberFormatter::CURRENCY);
			
			return $fmt->parseCurrency($value, $curr);
		}
	}
			
	trait monetary
	{
		var $exchange;
		var $class_list;
		var $taxes;
		var $freight;
		
		function associate_global_variable()
		{
			global $exchange, $class_list;
			$this->exchange=$exchange;
			$this->class_list=$class_list;
			$this->taxes=new taxes();
			$this->freight=new freight();
		}		
	}
	//$taxes=new taxes();
?>