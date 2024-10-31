<?php
/**
* Purpose: A Google Maps/Earth KML and KMZ export tool
* Description: Gathers and organizes data and exports it as XML with a file extension KML or KMZ, a standard for
*			Google Earth and Google maps. KMZ is the same format as KML, the difference is that KMZ is compressed 
*			and can include images within the compressed file
*
* @version Date: 20. marec 2009
* @author Žan Kafol
* @access public
*/

class point {
	/*
	* Global variables
	* Latitude, Longitude, Name. Description, Icon
	*/ 
	public $lat = 0;
	public $lon = 0;
	public $name = "";
	public $link = "";
	public $description = "";
	public $extra = "{}";
	public $icon = "";
	public $id = 0;
	public $alt = null;
	public $timestamp = null;
	public $geocode = false;
	public $address = '';
	public $onclick = '';
	public $width = 16;
	public $height = 16;
	public $zIndex = 0;
	public $accuracy = 0;
	public $speed = 0;
	public $category = array();
	
	/*
	* Construct function
	* @param string $id
	* @param string $coor
	* @param string $altitude
	* @param string $name
	* @param string $description
	* @param string $icon
	*/
	function point($id=null,$coor=null,$alt=null,$name='',$description='',$icon='',$extra='{}') {
		if($coor != null) {
			list($lat,$lon) = explode(',',$coor);
			$this->setLat($lat);
			$this->setLon($lon);
		}
		$this->name = $name;
		$this->description = $description;
		$this->icon = $icon;
		$this->id = ($id==null) ? time().rand() : $id;
		$this->alt = $alt;
		$this->extra = $extra;
		$this->timestamp = time();
	}
	
	function setAddr($a) {
		$this->address = $a;
		$this->geocode = true;
	}
	
	function setLat($c) {
		$this->lat = $this->degToDec($c);
	}
	
	function setLon($c) {
		$this->lon = $this->degToDec($c);
	}
	
	function asDegMin() {
		$coord = '';
		$intlat = intval(abs($this->lat));
		$intlon = intval(abs($this->lon));
		
		$coord .= $this->lat > 0 ? 'N' : 'S';
		$coord .= " $intlat"."° ";
		$coord .= number_format(round((abs($this->lat) - $intlat) * 60, 3),3);
		
		$coord .= ' ';
		$coord .= $this->lon > 0 ? 'E' : 'W';
		$coord .= " $intlon"."° ";
		$coord .= number_format(round((abs($this->lon) - $intlon) * 60, 3),3);
		
		return $coord;
	}
	
	/*
	* Function degToDec
	* Converts a (Degrees,Minutes,Seconds) coordinate to a decimal number
	* @param string $coor
	*/
	function degToDec($coordinate) {
		$coor = trim(preg_replace('[^\d\.°\']','',$coordinate));
		$coor = html_entity_decode($coor,ENT_QUOTES);
		$coor = urldecode($coor);
		
		$split1 = explode('°',$coor);
		if(is_array($split1) && count($split1) > 1) {
			$degree = intval($split1[0]);
			$split2 = explode("'",$split1[1]);
			
			if(is_array($split2) && count($split2) > 1) {
				$minute = intval($split2[0]) / 60;
				$second = floatval($split2[1]) / 3600;
				
				$dec = floatval($degree + $minute + $second);
			} else {
				$dec = floatval($degree) + floatval($split1[1]) / 60;
			}
		} else {
			$dec = floatval($coor);
		}
		
		if($dec == 0.0) {
			//trigger_error("Zero coordinate: $coor ({$this->name})");
		}
		
		return $dec;
	}
	
	function format($input) {
		/*
		 * N 45° 46.000 E 014° 12.000
		 * S 45° 46' 1" W 014° 12' 1"
		 * +46° 22' 38.11", -96° 9' 55.90"
		 * N 45.766667 E 014.200000
		 * -45.766667,014.200000
		 */
		
		$input = trim($input);
		
		$patterns = array(
			'/([NS+-]?\s*\d+°\s*\d+[\.\']\s*\d+\.?\d*"?)\'?[\s,]+([+-WE]?\s*\d+°\s*\d+[\.\']\s*\d+\.?\d*"?)\'?/is',
			'/([+-NS]?\s*\d+\.?\d*)[\s,]+([+-WE]?\s*\d+\.?\d*)/is'
		);
		
		foreach($patterns as $pattern) {
			if(preg_match($pattern,$input,$matches) && count($matches) == 3) {
				foreach($matches as &$match) {
					$coord = self::degToDec(preg_replace('/[\sNSWE+-]/i','',$match));
					$match = preg_match('/[SW-]/i',$match) ? -$coord : $coord;
				}
				
				return self::degToDec($matches[1]).','.self::degToDec($matches[2]);
			}
		}

		return false;
	}
	
	function getID() {
		return $this->id;
	}
	
	function dist($p) { return $this->distance($p); }
	
	function distance($point) {
		return $this->haversine($this->lat,$this->lon,$point->lat,$point->lon);
	}
	
	function haversine($lat1,$lng1,$lat2,$lng2) {
		$dLat = deg2rad($lat2 - $lat1);
		$dLon = deg2rad($lng2 - $lng1);
		$a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
		return 2 * atan2(sqrt($a), sqrt(1 - $a)) * 6371; // 6371 = average earth radius
	}
	
	function geocode($latlng = null) {
		if(is_null($latlng)) {
			$latlng = "{$this->lat},{$this->lon}";
		}
		
		$json = file_get_contents("https://maps.googleapis.com/maps/api/geocode/json?latlng=$latlng&sensor=true");
		
		return json_decode($json);
	}
	
	function geocode_addr($latlng = null) {
		$geocode = $this->geocode($latlng);
		
		return ($geocode && isset($geocode->results) && isset($geocode->results[0])) ? $geocode->results[0]->formatted_address : '';
	}
}

?>