<?php
	/*
	 * FaceAPI Javascript example:
	 * Documentation:
	 * https://justadudewhohacks.github.io/face-api.js/docs/index.html
	 * 
	 * Case Study:  
	 * https://medium.com/theleanprogrammer/face-api-js-a-way-to-build-face-recognition-system-in-browser-c1f4ac922657 
	 */

	class faceapi extends features
	/*
	 * This class is used to include faceapi
	 */
	{
		var $name;
		
		function __construct($credentials, $name)
		{
			$this->name=$name;
			parent::__construct($credentials, [], 'faceapi', 'use_faceapi');
			
			$this->add_dependency(new jquery($credentials, 'jquery'));
		}
		
		function use_faceapi()
		{
			return '<script src="./components/faceapi/js/face-api.min.js"></script>';
		}
		
	}
	
	trait faceapi_input
	{
		/*
		 * This trait is used to extend lib_form or its descandents with facial recognition
		 * input funcitonality
		 * 
		 * to use this, you must use camera_input components, and then pull the image holder
		 * here
		 */
		
		var $face_matches;
		
		function enable_faceapi_reader()
		/*
		 * Used when reading facial description to text and then add all the javascript features to the interface
		 */
		{
			$face_api=new faceapi($this->credentials, 'faceapi');
			$features=[$face_api];
			$this->add_feature('faceapi', 'faceapi', $face_api);
			
			foreach ($this->fields as $field)
				if ((isset($field['faceapi'])) && ($field['faceapi']==true))
				{
					$feature=new feature_faceapi_descriptor($this->credentials, $field['name'], 
						$this->select($field['name'], 'describe'), $this->select($field['name'], 'reset'),
						$this->submit_name);
					
					$features[]=$feature;
					$this->add_feature('feature_faceapi_descriptor', $field['name'], $feature);					
				}
					
			return $features;					
		}
		
		function enable_faceapi_matcher($show_submit=false)
		/*
		 * Page feature, must be used when reading and matching facial description and add all the javascript features to the interface
		 * 
		 * $show_submit is false then it will hide when needed (cannot be to bypassed), otherwise, it can by bypassed
		 */
		{
			$face_api=new faceapi($this->credentials, 'faceapi');
			$features=[$face_api];
			$this->add_feature('faceapi', 'faceapi', $face_api);
			
			foreach ($this->fields as $field)
				if ((isset($field['faceapi'])) && ($field['faceapi']==true))
				{
					$feature=new feature_faceapi_matcher($this->credentials, $field['name'],
						$this->select($field['name'], 'describe'), 
						$this->select($field['name'], 'reset'),
						$this->select($field['name'], 'confirmation'),
						$this->submit_name,
						$this->select($field['name'], 'accuracy_threshold'));
					
					$feature->set('show_submit', $show_submit);
					
					$feature->add_face_matches($this->face_matches);
					
					$features[]=$feature;
					$this->add_feature('feature_faceapi_matcher', $field['name'], $feature);
				}
			
			return $features;
		}
		
		function add_face_matches(smart_list $list)
		/*
		 * extracting face matches from employee_list this will be added to features when enable_faceapi_matcher
		 */
		{
			$this->face_matches=[];
			
			foreach ($list as $id => $person)
			{
				$this->face_matches[$id]['descriptions']=$person->get_facial_data();
				$this->face_matches[$id]['name']=$person->getName();
			}
		}
		
		function mark_faceapi_input($field, $describe_button=null)
		/*
		 * You can use any image including camera input (preferably) so when its processed 
		 * it will process the image
		 * 
		 * description button can be piggybacked to any button, if not defined then 
		 * will be using this case shutter button from camera input 
		 */
		{
			$this->add_specification($field, 'faceapi', true);
			
			if (($this->select($field, 'camera')) && !($describe_button)) 
			// if field is a camera and describe button is null then redefine describe button
			{
				$describe_button=$this->select($field, 'shutter');				
			}
			
			$this->add_specification($field, 'describe', $describe_button);
			
			/*
			 * Make describe button will add event on describe button using add event listener
			 */
		}
		
		function mark_faceapi_matcher_input($field, $confirmation='confirmation', $describe_button=null, $threshold=0.6)
		/*
		 * Identical mark_faceapi_input, but adding confirmation fields for the welcome text!
		 * fields type should be dropdown		 
		 */
		{
			$this->mark_faceapi_input($field, $describe_button);
						
			$this->add_specification($field, 'confirmation', $field.'_'.$confirmation);
			$this->add_specification($field, 'accuracy_threshold', $threshold);
			
			$this->add_hidden_input('_facial_update_'.$field, '');
			$this->add_div('', $field.'_'.$confirmation, '', 1);
		}		
		
		function faceapi_input($field)
		/*
		 * Identical to camera_input, 
		 */
		{
			$result='';
			
			if ((isset($this->fields[$field]['faceapi'])) && ($this->fields[$field]['faceapi']))
			{
				$result.=$this->faceapi_html_input_placeholder($field);
				$result.=$this->camera_body($field);
			}
			
			return $result;
		}
		
		function faceapi_matcher_input($field)
		/*
		 * Identical to faceapi_input, but with additional div,
		 */
		{
			$result='';
			
			if ((isset($this->fields[$field]['faceapi'])) && ($this->fields[$field]['faceapi']))
			{
				$result.=$this->faceapi_html_input_placeholder($field);
				$result.=$this->camera_body($field);
				
				/*
				 * Forces this update!
				 */
				$result.=$this->html_hidden_codes('_facial_update_'.$field);
				
				$result.=$this->html_div($this->select($field, 'confirmation'));
			}
						
			return $result;
		}		
		
		function faceapi_html_input_placeholder($field)
		{
			$result=$this->html_hidden_codes($field);
			
			return $result;
		}		
	}
	
	trait faceapi_scandata
	{
		/*
		 * Add to human record, e.g. guest or employee to enable it used as faceapi_'s human
		 */
		function add_face_scan($face_description)
		/*
		 * Revolving and ever replacing facial scan that retains only the 5 latest
		 * scans to adjust with the age of the scanee
		 *
		 */
		{
			if ($this->select('face_scans'))
			{
				$keys=array_keys($this->select('face_scans'));
				$min=min($keys);
				$max=max($keys);
			}
			else
			{
				$min=0;
				$max=0;
			}
			
			$this->addData('face_scans', $face_description, $max+1);
			if ($max-$min>=6)
				$this->remove('face_scans', $min);
		}
		
		function master_face_scan($face_description)
		/*
		 * Master scans is used to scan for present face detection by
		 * HRD in office
		 *
		 * Face descriptions are scanned by faceapi
		 */
		{
			$this->addData('face_description', $face_description);
			$this->addData('face_date', \date::today('timestamp'));
			
			$this->add_face_scan($face_description);
		}
		
		function has_facial_data()
		{
			return ($this->select('face_description')!=null);
		}
		
		function get_facial_data()
		/*
		 * Some facial data will overlap during the first few scans
		 * because face scans are duplicated during the first facial scan,
		 * and this is intended
		 */
		{
			$face_scans=$this->select('face_scans') ?? [];
			
			$result=[$this->select('face_description'), ...$face_scans];
			
			return $result;
		}		
	}
	
	class faceapi_feature extends features
	/*
	 * This is the common class for loading libarary needed by faceapi_matcher and faceapi_descriptor  
	 * for enableing face api in a page
	 */
	{
		var $field_name;
		var $describe_button;
		var $reset_button;
		var $submit_name;
		
		function init_faceapi()
		/*
		 * Write on top
		 */
		{			
			$default_submit='none';
			if ($this->get('show_submit')==true)
				$default_submit='block';			
			
			$result="<script type='text/javascript'>";
			$result.="	async function init_faceapi()";
			$result.="	{";
			$result.="		const MODEL_URL = './components/faceapi/models';";
			$result.="		faceapi.loadSsdMobilenetv1Model(MODEL_URL);";
			$result.="		faceapi.loadFaceLandmarkModel(MODEL_URL);";
			$result.="		faceapi.loadFaceRecognitionModel(MODEL_URL);";
			$result.="		faceapi.loadFaceExpressionModel(MODEL_URL);";
			$result.='		submit = document.getElementById("'.$this->submit_name.'");';
			$result.="		submit.style.display='$default_submit';";
			$result.="	}";
			$result.="	window.addEventListener('load', init_faceapi, false);";
			$result.='</script>';
			
			return $result;
		}
	}
	
	class feature_faceapi_descriptor extends faceapi_feature
	/*
	 * This feeature is used to capture facial description on a photograph and returns it to a html form,
	 * preferably text 
	 * 
	 * You can use one feature for each camera input in a page.
	 */
	{
		function __construct($credentials, $field_name, $describe_button, $reset_button, $submit_name)
		/*
		 * Field name must be consistent with field name used by
		 */
		{
			$this->field_name=$field_name;
			$this->describe_button=$describe_button;
			$this->reset_button=$reset_button;
			$this->submit_name=$submit_name;
			
			parent::__construct($credentials, [], 'faceapi_'.$field_name, ['init_faceapi', 'register_camera_controls', 'describe_face']);
		}
		
		function register_camera_controls()
		/*
		 * This method will register event listener for shutte click as piggybacking to the default click function
		 * thus executed after that function
		 */
		{
			$result="<script type='text/javascript'>";
			$result.="function register_camera_controls()";
			
			$result.='{';
			$result.='	shutter = document.getElementById("'.$this->describe_button.'");';			
			$result.='	shutter.addEventListener("click",
					 	function(ev)
			 			{';	
			$result.=' 		photo = document.getElementById("photo_'.$this->field_name.'");';
			$result.='		field = document.getElementById("'.$this->field_name.'");';
			$result.='		describe_face(photo, field);';
			$result.='	}, false);';
			
			$result.='	reset = document.getElementById("'.$this->reset_button.'");';
			$result.='	reset.addEventListener("click",
					 	function(ev)
			 			{';
			$result.='		field = document.getElementById("'.$this->field_name.'");';
			$result.='		field.value="";';
			$result.='		submit = document.getElementById("'.$this->submit_name.'");';
			$result.="		submit.style.display='none';";			
			$result.='	}, false);';			
			$result.='}';
					
			$result.="	window.addEventListener('load', register_camera_controls, false);";
			$result.='</script>';
			
			return $result;			
		}
		
		function describe_face()
		/*
		 * This will rewrite the input holder with description
		 */
		{
			$result="<script type='text/javascript'>";
			$result.='async function describe_face(img_face, field)';
			$result.='{';
			$result.='	faceDescription=await faceapi.detectSingleFace(img_face).withFaceLandmarks().withFaceDescriptor();';
					
			$result.='	if (faceDescription)';
			$result.='	{';
			$result.='		alert("scan success!");';
			$result.='		field.value=faceDescription.descriptor;';
			$result.='		submit = document.getElementById("'.$this->submit_name.'");';
			$result.="		submit.style.display='block';";			
			$result.='	}';
			$result.='	else';
			$result.='	{';
			$result.='		alert("no face detected");';
			$result.='		reset_camera();';
			$result.='		field.value="";';
			
			$result.='		submit = document.getElementById("'.$this->submit_name.'");';
			$result.="		submit.style.display='none';";
			$result.='	};';
					
//			$result.='	alert([faceDescription.descriptor]);';
			$result.='}';		
			$result.='</script>';
			
			return $result;
		}
	}
			
	class feature_faceapi_matcher extends faceapi_feature
	/*
	 * This class is used in matching face captured on a webcam to face descriptions
	 */
	{
		var $face_list;
		var $confirmation_field;
		
		function __construct($credentials, $field_name, $describe_button, $reset_button, 
			$confirmation_field, $submit_name, $accuracy_threshold)
		/*
		 * Field name must be consistent with field name used by
		 * accuracy_threshold, lower the more accurate
		 */
		{
			$this->field_name=$field_name;
			$this->describe_button=$describe_button;
			$this->reset_button=$reset_button;
			$this->submit_name=$submit_name;
			$this->confirmation_field=$confirmation_field;
			$this->accuracy_threshold=$accuracy_threshold;
			
			parent::__construct($credentials, [], 'faceapi_'.$field_name, ['init_faceapi', 'load_face_descriptors', 
				'scan_face', 'register_camera_controls']);
		}
		
		function scan_face()
		/*
		 * This will rewrite the input holder with description
		 */
		{
			$result="<script type='text/javascript'>";
			$result.='async function scan_face(img_face, update_field, lf_descriptors)';
			$result.='{';
			$result.='	let faceDescription = await faceapi.detectSingleFace(img_face).withFaceLandmarks().withFaceDescriptor();';
			$result.='	let value="";';
			
			$result.='	if (faceDescription)';
			$result.='	{';
			$result.='		faceDescription = faceapi.resizeResults(faceDescription, img_face);';		
			$result.='		const threshold = '.$this->accuracy_threshold.';';
			$result.='		const faceMatcher = new faceapi.FaceMatcher(lf_descriptors, threshold);';

			$result.='		const result = faceMatcher.findBestMatch(faceDescription.descriptor);';
			$result.='		value=result.label;';
			
			$result.='		update_field.value=faceDescription.descriptor;';
//			$result.='		alert(value);';
//			$result.='	const results = faceDescription.map(fd => (fd.descriptor));';
/*			$result.='	results.forEach((bestMatch, i) => ';
			$result.='		{alert(bestMatch.toString());})';*/
			$result.='	}';
			$result.='	return value;';
			$result.='}';
			$result.='</script>';
			
			return $result;			
		}
		
		function add_face_matches($list)
		{
			$this->face_list=$list;
		}
		
		function load_face_descriptors()
		/*
		 * Loading face descriptions to the javascript
		 */
		{
			$ids=[]; 
			$names=[];
			$descs=[];
			$count=count($this->face_list);
			
			$i=0;
			foreach ($this->face_list as $id => ['descriptions' => $descriptions, 'name' => $name])
			{
				$ids[$i]="'".$id."'";
				$names[$i]="'".htmlspecialchars($name)."'";
				
				foreach ($descriptions as $description)
					$descs[$i][]='new Float32Array(['.$description.'])';
				$i++;
			}
			
			$result="<script type='text/javascript'>";
			$result.='	const '.$this->field_name.'_names = ['.implode(',', $names).'];';
			$result.='	const '.$this->field_name.'_usernames = ['.implode(',', $ids).'];';
			$result.="	function load_face_descriptors()";
			$result.='	{';
			$result.='		result=[];';
			$result.='		let f32a=[];';
			for ($i=0; $i<$count; $i++)
			{
				$result.='		f32a=['.implode(',', $descs[$i]).'];';
				$result.='		result['.$i.']=new faceapi.LabeledFaceDescriptors('.$ids[$i].', f32a);';
			}			
			$result.='		return result;';
			$result.='	}';
			$result.='	const labeledFaceDescriptors = load_face_descriptors();';			
			$result.='</script>';
			
			return $result;
		
		}
		
		function register_camera_controls()
		/*
		 * This method will register event listener for shutte click as piggybacking to the default click function
		 * thus executed after that function
		 */
		{
			$default_submit='none';
			if ($this->get('show_submit')==true)
				$default_submit='block';
				
			$result="<script type='text/javascript'>";
			$result.="function register_camera_controls()";
			
			$result.='{';
			$result.='	shutter = document.getElementById("'.$this->describe_button.'");';
			$result.='	shutter.addEventListener("click",
					 	async function(ev)
			 			{';
			$result.=' 		photo = document.getElementById("photo_'.$this->field_name.'");';
			$result.='		field = document.getElementById("'.$this->field_name.'");';
			$result.='		update_field = document.getElementById("_facial_update_'.$this->field_name.'");';
			$result.='		value = await scan_face(photo, update_field, labeledFaceDescriptors);';
						
			$result.='		if ((value=="unknown") || (value==""))';
			$result.='		{';
			$result.='			alert("tidak dikenali");';
			$result.='			reset_camera();';
			$result.='		}';
			$result.='		else';
			$result.='		{';
			$result.='			let id='.$this->field_name.'_usernames.indexOf(value);';
			$result.='			field.value=value;';
			$result.='			let name ='.$this->field_name.'_names[id];';
			
			$result.='			confirmation = document.getElementById("'.$this->confirmation_field.'");';
			$result.='			confirmation.innerHTML="<h3>Hello "+name+"</h3>";';
			
			$result.='			submit = document.getElementById("'.$this->submit_name.'");';
			$result.="			submit.style.display='block';";			
			$result.='		}';
			
			$result.='	}, false);';
			
			$result.='	reset = document.getElementById("'.$this->reset_button.'");';
			$result.='	reset.addEventListener("click",
					 	function(ev)
			 			{';
			$result.='		field = document.getElementById("'.$this->field_name.'");';
			$result.='		field.value="";';
			$result.='		submit = document.getElementById("'.$this->submit_name.'");';
			$result.="		submit.style.display='$default_submit';";
			$result.='		confirmation = document.getElementById("'.$this->confirmation_field.'");';
			$result.='		confirmation.innerHTML="";';
			$result.='	}, false);';
			$result.='}';
			
			$result.="	window.addEventListener('load', register_camera_controls, false);";
			$result.='</script>';
			
			return $result;
		}		
	}
	
	class faceapi_checkin_form extends inputform_control
	/*
	 * Extends from this to use facial recognition login
	 * 
	 * stepZero is already made 
	 */
	{
		use camera_input;
		use faceapi_input;
		
		var $emploee_list;
		
		function prepare_facial_input(array $conditions)
		/*
		 * Fill conditions with blank array
		 */
		{
			$this->employee_list=$this->get_global('employee_list');
			
			$conditions[]=[f => 'has_facial_data', p => []];
			$this->employee_list->db_fill($conditions, [], false);
			
			$employees=$this->employee_list->asDropdownArray([value => 'index', display => 'vanity_name'], display, []);
			
			$this->add_dropdown('Foto Selfie', 'employee', $employees, '', [required => true]);
			$this->mark_camera_input('employee', 'Foto', 'Reset');
			$this->mark_faceapi_matcher_input('employee', 'confirmation', null, 0.15);
						
			$this->add_face_matches($this->employee_list);
			
			$this->add_password_input('Password', 'password', 4, 32);
		}
		
		function input($input, ...$fields) 
		{
			/*
			 * This field is used to capture password
			 */			
			parent::input($input);
									
			if ($this->input_step>=1)
			{
				$password=$this->select('password');
				 
				if (!$this->credentials->is_valid_login($this->secure_db, $this->select('employee'), $password))
				{ 
					$this->error='Foto dan password tidak cocok.';					
					$this->revert_input_step(0);
				}
			}
													
			return $this->input_step;
		}
		
		function stepZero()
		{
			$result=$this->form_open();
			
			$result.=$this->faceapi_matcher_input('employee').'<br>';
			
			$result.=$this->html_password('password');
			
			$result.=$this->html_submit();
			$result.=$this->html_credential();
			
			$result.=$this->form_close();
			
			return $result;			
		}
		
		function get_page_features()
		/*(
		 * Override on descendant class for other feature is used! 
		 */
		{
			return [...$this->enable_camera_input(),
				...$this->enable_faceapi_matcher()];			
		}
		
		function form_title($form_title='')
		{
			return parent::form_title($form_title ?: 'FaceAPI Checkin'); 
		}
	}
	
	class faceapi_checkin_interface extends junction
	/*
	 * The default interface that is used for class that extends faceapi_checkin_form 
	 */
	{
		function form_title($form_title='')
		{
			return parent::form_title($this->form->form_title());
		}
		
		function __construct($credentials, $input, $secure_db, $readonly_db)
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
			
			$form=$input['_form'];
			$f=new $form($credentials, 'facial_login', null, 'post', null, $secure_db, $readonly_db);
//			var_dump($this->form instanceof faceapi_checkin_form);
			
			$this->form=$f;
				
			$this->form->set_junction($this);
							
			if ($this->form->input($input)==$this->form->final_input_step())
				$this->form->insert();
			
			$this->add_page_features(...$this->form->get_page_features());				
		}
	}
	
	class faceapi_checkin_page extends secure_page
	{
		function __construct($credentials, $input, $secure_db, $readonly_db, $settings)
		{
			parent::__construct($credentials, $input, $secure_db, $readonly_db);
			
			$interface='faceapi_checkin_interface';
			if (($input['_form']) && (is_subclass_of($input['_form'], 'faceapi_checkin_form', true)))				
			{
				$this->interface=$this->prepare_junction($interface, $credentials, $input, $secure_db, $readonly_db);
				if ($this->interface)
				{
					$this->pageContents=$this->interface->toHTML();
					$this->H1Title=$this->interface->form_title('');
							
					$this->pageTitle=$this->H1Title;
					$this->pageDescription=$this->interface->description();
				}
			}
		}
	}
?>