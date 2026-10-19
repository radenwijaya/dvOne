<?php
	class Strings
	{
		const holiday_names = [
			'cny' => ['id_ID' => 'Imlek', 'en_ID' => 'Spring Festival'],
			
			'vesak' => ['id_ID' => 'Waisak', 'en_ID' => 'Vesak'],
			'nyepi' => ['id_ID' => 'Nyepi', 'en_ID' => 'Nyepi'],
			
			'good_friday' => ['id_ID' => 'Jumat Agung', 'en_ID' => 'Good Friday'],
			'easter' => ['id_ID' => 'Minggu Paskah', 'en_ID' => 'Easter'],
			'ascension' => ['id_ID' => 'Kenaikan Yesus', 'en_ID' => 'Ascension Day'],
			'christmas' => ['id_ID' => 'Natal', 'en_ID' => 'Christmas Day'],
			
			'isra' => ['id_ID' => 'Isra Miraj', 'en_ID' => "Isra' and Mi'raj"],
			'hny' => ['id_ID' => 'Tahun Baru Hijri', 'en_ID' => 'Hijri Newyear'],
			'eid_fitr' => ['id_ID' => 'Idul Fitri', 'en_ID' => 'Eid al Fitr'],
			'eid_adha' => ['id_ID' => 'Idul Adha', 'en_ID' => 'Eid al Adha'],
			'mawlid' => ['id_ID' => 'Maulud Nabi', 'en_ID' => 'Mawlid'],
			
			'newyear' => ['id_ID' => 'Tahun Baru', 'en_ID' => 'New Year'],
			'labour' => ['id_ID' => 'Hari Buruh', 'en_ID' => 'Labour Day'],
			'pancasila' => ['id_ID' => 'Hari Pancasila', 'en_ID' => 'Pancasila Day'],
			'national' => ['id_ID' => 'Hari Kemerdekaan', 'en_ID' => 'National Day'],
		];
		
		const lib_form_errors = [
			406 => ['id_ID' => "406 Input tidak bisa diterima (#type)", 
				    'en_ID' => "406 Input is not acceptable (#type)"]			
		];
		
		const invokeable_interface_errors = [
			400 => ['id_ID' => 'Akses tidak sah.',
					'en_ID' => 'Unauthorised access.'],			
			401 => ['id_ID' => 'Login Gagal.',
					'en_ID' => 'Login Failed.'],
			500 => ['id_ID' => 'Kegagalan system!', 
					'en_ID' => 'System failure!']
		];
	}	
?>