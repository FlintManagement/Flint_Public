<?php

// database config
$sqlhost = "localhost";
$sqluser = "newpremises";
$sqlpass = "r3RdqRLuM9HEByt7";
$sqldb = "newpremises";
$sqlprefix = "p_";

// connect to database
mysql_connect("$sqlhost", "$sqluser", "$sqlpass") or die(mysql_error());
mysql_select_db("$sqldb") or die(mysql_error());


// get posted locations
$loc = $_POST['loc'];

if(!$loc){
	echo "Please enter the location to search for";
	die();
}

// geocode data
$geo = geocode($loc);

// init return data
$return = array();

// debug = show full returned data
$return['source'] = $geo['source'];
//$return[] = $geo;

// (last) address into return array
foreach($geo['addresses'] as $addr){
	
	$return[] = $addr;
}


// output json
echo json_encode($return);


/****************************************************************************************************************/


// convert object to array
function object_to_array($obj) {
    if(is_object($obj)) $obj = (array) $obj;
    if(is_array($obj)) {
        $new = array();
        foreach($obj as $key => $val) {
            $new[$key] = object_to_array($val);
        }
    }
    else $new = $obj;
    return $new;
}

// function to pull geocode data from the cache or google, return array of data
function geocode($address){

	global $sqlprefix;
	
	// look up in cache table
	$sql = "SELECT `result` FROM `".$sqlprefix."location_cache` WHERE `string` = '".mysql_real_escape_string($address)."'";
	$query = mysql_query($sql)or die(mysql_error());
	
	// check results
	if(mysql_num_rows($query)>0){
		// pull return from db
		$row = mysql_fetch_assoc($query);
		$data = unserialize($row['result']);
		// set source
		$datasource = "cache";
	}else{
		// build url
		$url = "http://maps.googleapis.com/maps/api/geocode/json?address=".urlencode($address)."&sensor=false&region=uk";
		// get contents
		$data = file_get_contents($url);
		// parse return
		$data = json_decode($data);	
		// convert to array
		$data = object_to_array($data);
				
		// got array, save it to db
		if(is_array($data)){
			// insert into cache table
			$sql = "INSERT INTO `".$sqlprefix."location_cache` (`string`,`date`,`result`) VALUES ('".mysql_real_escape_string($address)."','".time()."','".mysql_real_escape_string(serialize($data))."')";
			$query = mysql_query($sql)or die(mysql_error());
		}
		// set source
		$datasource = "geocoder";
	
	}
	
	/*/
	echo "<pre>";
	print_r($data);
	echo "</pre>";
	//*/
	
	
	// location types to look at
	$oktypes = array(
		"postal_code_prefix",
		"postal_code",
		"sublocality",
		"political",
		"locality"
	);
	
	// go through results
	$addresses = array();
	if(is_array($data['results'])){
		foreach($data['results'] as $loc){
		
			// get country code
			foreach($loc['address_components'] as $k => $a){
				if(in_array("country",$a['types'])){
					$cc = $loc['address_components'][$k]['short_name'];
				}
			}
			
			// check this location is of a valid type
			$intypes = array_intersect($oktypes,$loc['types']);
			
			// check matches requirements
			if(!empty($intypes) && $loc['partial_match']!=1 && $cc=="GB"){
				
				// get width in degrees
				$width = ( $loc['geometry']['viewport']['northeast']['lng'] - $loc['geometry']['viewport']['southwest']['lng'] );
				
				// set map zoom level depending on viewport width
				if($width>=2){
					$zoom = 6;
				}elseif($width>=0.5){
					$zoom = 9;
				}elseif($width>=0.1){
					$zoom = 12;
				}elseif($width>=0.05){
					$zoom = 13;
				}else{
					$zoom = 15;
				}
				
				// set estimated search radius
				$radius = ceil($width * 15);
				
				$addresses[] = array(
					"address"=>$loc['formatted_address'],
					"location_lat"=>$loc['geometry']['location']['lat'],
					"location_lng"=>$loc['geometry']['location']['lng'],
					"zoom"=>$zoom,
					"radius"=>$radius
				);
			}
		
		}
	}
	
	// return details
	return array("source"=>$datasource,"addresses"=>$addresses,"data"=>$data);
	
}



?>