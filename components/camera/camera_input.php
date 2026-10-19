<?php
	/*
	 * Camera activation example, most of the code was taken from 
	 * 
	 * https://www.studytonight.com/post/capture-photo-using-webcam-in-javascript
	 * 
	 * Reminder: Camera activation requires HTTPS, it will not work without HTTPS 
	 * connection
	 */

	trait camera_input
	/*
	 * This trait is used to extend lib_form or its descandents with camera 
	 * input funcitonality
	 * 
	 * If not coupled with with faceapi, it will put PNG as text on the input field
	 */	
	{
		function enable_camera_input($facing=feature_camera::face_default)
		{
			foreach ($this->fields as $field)
				if ((isset($field['camera'])) && ($field['camera']==true))
				{
					$feature=new feature_camera($this->credentials, $field['name'], $this->select($field['name'], 'shutter'),
						$this->select($field['name'], 'reset'), $facing);
					
					$features[]=$feature;
					$this->add_feature('feature_camera', $field['name'], $feature);					
				}
							
			return $features;					
		}
		
		function mark_camera_input($field, $shutter, $result)
		/*
		 * Capture is the name of button used to capture
		 */
		{
			$this->add_specification($field, 'camera', true);
			
			$this->add_button('', $field.'_shutter', $shutter);			
			$this->add_specification($field, 'shutter', $field.'_shutter');
			
			/*
			 * This will add primary action when clicking the shutter button, other action afterwards
			 * will be reserved for piggybacking by face api or other functions
			 */
			$this->register_HTML_event($field.'_shutter', 'onclick', 'takepicture();');
			
			$this->add_button('', $field.'_reset', $result);
			$this->add_specification($field, 'reset', $field.'_reset');
			
			/*
			 * This will add primary action when clicking the reset button
			 */
			$this->register_HTML_event($field.'_reset', 'onclick', 'reset_camera();');			
		}
		
		function camera_body($field)
		{
			$result='';
			
			if ($this->fields[$field]['caption'])
				$result.='<strong>'.$this->fields[$field]['caption'].'</strong>';
				
			$result.='<div class="camera_input">';
			$result.='	<video id="video_'.$field.'">Video stream not available.</video>';
			$result.='	<img id="photo_'.$field.'" alt="Image not available">';
			$result.='	<canvas id="canvas_'.$field.'"></canvas>';
			
			$result.=$this->html_button($this->select($field, 'shutter'));
			$result.=$this->html_button($this->select($field, 'reset'));
			$result.='</div>';
			
			return $result;
		}
		
		function camera_input_placeholder($field)
		{
			$result=$this->html_hidden_codes($field);
			
			return $result;
		}		
		
		function camera_input($field)
		/*
		 * Shows camera input on HTML.  
		 * 
		 * This will output PNG as text as the field's value, and it can be stored in database
		 * or other place.  Be careful, it is big!   		 
		 * 
		 * The image then can be used as display using image injection technology by converting image to text.  
		 */
		{	
			$result='';
			
			if ((isset($this->fields[$field]['camera'])) && ($this->fields[$field]['camera']))
			{
				$result.=$this->camera_input_placeholder($field);
				$result.=$this->camera_body($field);
			}
			
			return $result;					
		}
	}
	
	class feature_camera extends features
	{
		var $field_name;
		var $shutter;
		var $reset;
		
		var $camera_facing;

		const face_back = 'environment';
		const face_front = 'user';
		const face_default = 'default';
		
		function __construct($credentials, $field_name, $shutter, $reset, $facing=self::face_back)
		/*
		 * Field name must be consistent with field name used by
		 */
		{
			$this->field_name=$field_name;
			$this->shutter=$shutter; 
			$this->reset=$reset;
			
			$this->camera_facing=$facing;
			
			parent::__construct($credentials, [], 'camera_'.$field_name, ['camera_css', 'init_camera', 'capture', 'clear', 'camera_open', 'reset_camera']);						
		}
		
		function camera_css()
		/*
		 * Additional CSS, 
		 */
		{
			return '<link type="text/css" href="./components/camera/camera_css.css" rel="stylesheet">';			
		}
		
		function init_camera()
		{
			$camera_facing=
				match ($this->camera_facing)
				{
					self::face_default => 'true',
					self::face_back => '{ facingMode: { exact: "environment" }, }',
					self::face_front => '{ facingMode: { exact: "user" }, }'
				};
			
			$result="<script type='text/javascript'>";
			$result.='function init_camera()';
			$result.='{';
			$result.=' 	photo = document.getElementById("photo_'.$this->field_name.'");';
			$result.='	photo.style.display="none";';
			$result.=' 	canvas = document.getElementById("canvas_'.$this->field_name.'");';
			$result.='	canvas.style.display="none";';
			$result.=' 	video = document.getElementById("video_'.$this->field_name.'");';

			$result.='	shutter = document.getElementById("'.$this->shutter.'");';
			$result.='	reset = document.getElementById("'.$this->reset.'");';
			
			$result.=' 	navigator.mediaDevices.getUserMedia({video: '.$camera_facing.', audio: false})';
			$result.=' 		.then(function(stream) {video.srcObject = stream; video.play(); })';
			$result.=' 		.catch(function(err) { console.log("An error occurred: " + err); });';
			
			$result.=' 	video.addEventListener("canplay", 
							function(ev) 
							{
								if (!streaming_'.$this->field_name.') 
								{
									height = video.videoHeight;
									width = video.videoWidth;
										
									video.setAttribute("width", width);
									video.setAttribute("height", height);
									canvas.setAttribute("width", width);
									canvas.setAttribute("height", height);

									streaming_'.$this->field_name.' = true;
									shutter.style.display="block";
									reset.style.display="none";
								}
							}, false);';
			
/*			$result.='shutter.addEventListener("click", 
						function(ev)
						{
							takepicture();
							ev.preventDefault();
						}, false);';*/

/*			$result.='reset.addEventListener("click",
						function(ev)
						{
							reset_camera();
							ev.preventDefault();
						}, false);';*/
			
			$result.='}';
			$result.='</script>';
			
			return $result;
		}
		
		function reset_camera()
		/*
		 * Resetting camera
		 */
		{
			$result="<script type='text/javascript'>";
			$result.='function reset_camera()';
			$result.='{';
			$result.=' 	photo_data = document.getElementById("'.$this->field_name.'");';
			$result.=' 	photo = document.getElementById("photo_'.$this->field_name.'");';
			$result.=' 	video = document.getElementById("video_'.$this->field_name.'");';
			
			$result.='	shutter = document.getElementById("'.$this->shutter.'");';
			$result.='	reset = document.getElementById("'.$this->reset.'");';
			
			$result.='	shutter.style.display="block";';
			$result.='	reset.style.display="none";';
			
			$result.='	video.style.display="block";';
			$result.='	photo.style.display="none";';
			$result.='	photo_data.value="";';
			
			$result.='	clearphoto();';
			$result.='}';
			$result.='</script>';
			
			return $result;			
		}
		
		function capture()
		/*
		 * Capturing the video stream to a canvas
		 * 
		 * Needs to add data to strings aftertake picture to the text field input
		 */
		{			
			$result="<script type='text/javascript'>";
			$result.='function takepicture()';
			$result.='{';			
			$result.='	var context = canvas.getContext("2d");';
			
			$result.=' 	photo_data = document.getElementById("'.$this->field_name.'");';			
			$result.=' 	photo = document.getElementById("photo_'.$this->field_name.'");';
			$result.=' 	canvas = document.getElementById("canvas_'.$this->field_name.'");';
			$result.=' 	video = document.getElementById("video_'.$this->field_name.'");';
			
			$result.='	shutter = document.getElementById("'.$this->shutter.'");';
			$result.='	reset = document.getElementById("'.$this->reset.'");';
			
			$result.='	video.style.display="none";';
			$result.='	photo.style.display="block";';
			$result.='	shutter.style.display="none";';
			$result.='	reset.style.display="block";';
			
			$result.='	if (video.videoWidth && video.videoHeight)';
			$result.='	{';
			$result.='		canvas.width = video.videoWidth;';
			$result.='		canvas.height = video.videoHeight;';
			$result.='		context.drawImage(video, 0, 0, video.videoWidth, video.videoHeight);';
			$result.='		var data = canvas.toDataURL("image/png");';
			$result.='		photo.setAttribute("src", data);';					
			$result.='		photo_data.value=data;';			
//			$result.='		describe_picture(photo);';
			$result.='	}';
			$result.='	else';
			$result.='	{';
			$result.='		clearphoto();';
			$result.='	}';
			$result.='}';
			$result.='</script>';
			
			return $result;
		}
		
		function clear()
		{
			$result="<script type='text/javascript'>";
			$result.='function clearphoto()';
			$result.='{';
			$result.='	var context = canvas.getContext("2d");';
			
			$result.=' 	photo = document.getElementById("photo_'.$this->field_name.'");';
			$result.=' 	canvas = document.getElementById("canvas_'.$this->field_name.'");';
			
			$result.='	context.fillStyle = "#AAA";';
			$result.='	context.fillRect(0, 0, canvas.width, canvas.height);';
				
			$result.='	var data = canvas.toDataURL("image/png");';
			$result.='	photo.setAttribute("src", data);';
			$result.='}';
			$result.='</script>';
			
			return $result;			
		}
		
		function camera_open()
		{
			$result="<script type='text/javascript'>";
			$result.="	streaming_".$this->field_name."=false;";
			
			//$result.='	document.getElementById("photo_'.$this->field_name.'").display.style="none"';
			//			$result.='	document.getElementById("canvas_'.$this->field_name.'").display.style="none";';
			$result.="	window.addEventListener('load', init_camera, false);";
			$result.='</script>';
			
			return $result;			
		}
	}
?>