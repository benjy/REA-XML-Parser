<?php
class REA_XML {

	/* Default Fields we return. You can specify any
	 * fields int the REA XML standard
	 */
	private $fields = array (
		'priceView',
		'description',
		'features' => array(
			'bedrooms',
			'bathrooms',
			'garages',
			'carports',
			'airConditioning',
			'pool',
			'alarmSystem',
			'otherFeatures',
		),
		'address' => array(
			'streetNumber',
			'street',
			'suburb',
			'state',
			'postcode',
		),	
		'images',
		'status',
	);

	/* default files exluded when parsing a directory */
	private $default_excluded_files = array(".", "..");

	/* Keeps track of excluded files */
	private $excluded_files;

	function REA_XML($debug=false, $fields=array()) {

		/* Use requested fields if set */
		if(!empty($fields)) {
			$this->fields = $fields;
		}
		$this->debug = $debug; /* Set debug flag */
		
	}

	function parse_xml($xml_string) {

		$properties = array();
		$properties_array = array();
		$xml = false;

		try {
			/* Create XML document. */
			@$xml = new SimpleXMLElement($xml_string);	
		}
		catch(Exception $e) {
			$this->feedback($e->getMessage());
		}

		/* Loaded the file */
		if($xml !== false) {

			/* Get property type */
			$property_root = $xml->xpath("/propertyList/*");
			if(isset($property_root[0])) {
				$property_type = $property_root[0]->getName();	
			}
			
			/* Some XML files don't even have a type and caused errors */
			if(!empty($property_type)) {
				/* Select the property type. */
				$properties = $xml->xpath("/propertyList/$property_type");
			}
		}


		/* We have properties */
		if(is_array($properties) && count($properties) > 0) {
			foreach($properties as $property) {
				$prop = array();//reset property

				/* For every property we select all
				 * the requested fields 
				 */
				foreach($this->fields as $key => $field) {
					if(is_array($field)) {
						foreach($field as $sub_field) {
							$prop[$key][$sub_field] = $property->{$key}->{$sub_field};
						}
					}
					else { /* Different handling for Images */
						if($field == "images") {
							foreach($property->images as $img) {
								$attr = $img->img->attributes();
								$prop[$field][] = $attr->url;
							}
						}
						else {
							$prop[$field] = $property->{$field};
						}
						
					}
				}

				if(in_array("status", $this->fields)) {
					$attr = $property->attributes();

					//save status
					$prop['status'] = $attr->status;
				}

				/* Save the property */
				$properties_array[$property_type][] = $prop;
			}
		}	

		return $properties_array;	
	}

	function parse_directory($dir, $excluded_files=array(), $property_type=false) {
		$properties = array();
		if(file_exists($dir)) {
			if($handle = opendir($dir)) {

				/* Merged default excluded files with user specified files */
				$this->excluded_files = array_merge($excluded_files, $this->default_excluded_files);

				/* Loop through all the files. */
				while(false !== ($xml_file = readdir($handle))) {

					/* Ensure it's not exlcuded. */
					if(!in_array($xml_file, $this->excluded_files)) {
						
						/* Get the full path */
						$file_full_path = $dir  . "/" . $xml_file;

						/* retrieve the properties from
						 * this file.
						 */
						$prop = $this->parse_file($file_full_path);

						if(is_array($prop) && count($prop) > 0) {

							$array_key = array_keys($prop);
							$property_type = $array_key[0];

							if(!isset($properties[$property_type])) {
								//initialise
								$properties[$property_type] = array();
							}

							/* We need the array prop because it includes the property type */
							$properties[$property_type] = array_merge($prop[$property_type], $properties[$property_type]);
						}

						/*
						if($loaded_xml) {
							xml_processed($xml_file, $data_dir);
						}
						else {
							xml_load_failed($xml_file, $data_dir);
						}
							*/							
						
					}
				}
				closedir($handle);
			}
			else {
				feedback("Could not open directory");
			}	
		}
		else {
			feedback("Directory does not exist");
		}

		return $properties;
	}

	/* Parse a REA XML File. */
	function parse_file($xml_file) {
		
		if(file_exists($xml_file)) {
			/* Parse the XML file */
			return $this->parse_xml(file_get_contents($xml_file));
		}
		else {
			throw new Exception("File could not be found");
		}
		
	}


	/* Property Types:
	 *
	 * residential, land, rental,
	 * commercialLand, business. 
	 */
	function get_property_type($xml_string) {
		$property_type = false;
		$xml = false;
		try {
			/* Create XML document. */
			@$xml = new SimpleXMLElement($xml_string);	
		}
		catch(Exception $e) {
			$this->feedback($e->getMessage());
		}

		if($xml) {
			/* Select the property type. */
			$properties = $xml->xpath("/propertyList/*");

			/* Ensure we have properties and Get Type */
			if(isset($properties[0])) {
				$property_type = $properties[0]->getName();
			}			
		}	
		return $property_type;	
	}


	/* Reset excluded files */
	function reset_excluded_files() {
		$this->excluded_files = $this->default_excluded_files;
	}

	/* Display Feedback */
	function feedback($string) {
		if($this->debug) {
			print $string . "<br/>";
		}
	}
	
}
