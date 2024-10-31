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

class map {
	/*
	* Global variables
	*/ 
	public $points = array();
	public $map_height = '100%';
	public $map_width = '100%';
	public $view = null;
	public $zoom = 14;
	public $title = 'Google Maps';
	public $type = 0;
	public $extra = '';
	public $lang = 'sl';
	
	public $currentLocation = false;
	public $search = false;
	
	public $jquery = false;
	public $cluster = false;
	public $bounds = false;
	
	private $id;
	
	/*
	* Constructor function
	*/
	function map($points=null) {
		if(is_array($points)) {
			foreach($points as $point) {
				if(floatval($point->lat) != 0 && floatval($point->lon) != 0) {
					$this->points[] = $point;
				}
			}
		}
		if(count($this->points) > 0) {
			$this->setView($this->points[0],18);
		}
		
		$this->id = uniqid();
	}
	
	function setView($view,$alt=8,$heading=null) {
		$this->view = $view;
		$this->zoom = $alt;
	}
	
	function setTitle($t) {
		$this->title = $t;
	}
	
	function setType($t) {
		$this->type = $t;
	}
	
	/*
	* Dummy functions
	*/
	function setName($name) {
		$this->setTitle($name);
	}
	function cleanForJS($s) {
		$s = str_replace("\n",'',$s);
		$s = str_replace("\r",'',$s);
		$s = str_replace("\t",'',$s);
		$s = str_replace('</',"<\/",$s);
		return $s;
	}
	function extra($e) {
		$this->extra .= $e;
	}
	
	function striptags($s,$o=null) {
		return strip_tags($s,$o);
	}
	
	/*
	* Set map Key
	*/
	function key($key) {
		$this->key = $key;
	}
	function setKey($key) {
		$this->key($key);
	}

	/*
	 * Output function
	 * &language='.$this->lang.'
	*/
	function output() {
		$html = '';
		
		$html .= '
			<script type="text/javascript">
				var gmap_'.$this->id.' = null;
				var gmap_open_info_'.$this->id.' = null;
				var gmap_markers_'.$this->id.' = [];
				var gmap_infowindows_'.$this->id.' = [];
				var gmap_bounds_'.$this->id.' = [];
				
				function kafolnet_gmaps_init_'.$this->id.'() {
					var gmap_default_center_'.$this->id.' = new google.maps.LatLng('.(!is_null($this->view) ? $this->view->lat.','.$this->view->lon : '46.119944,14.815333' ).');
					
					gmap_'.$this->id.' = new google.maps.Map(document.getElementById("gmap_canvas_'.$this->id.'"), {
						zoom: '.$this->zoom.',
						center: gmap_default_center_'.$this->id.',
						mapTypeId: google.maps.MapTypeId.ROADMAP
					});
					';
					
					if($this->cluster) {
						$html .= 'var fluster_'.$this->id.' = new Fluster2(gmap_'.$this->id.');';
					}
					
					foreach($this->points as $point) {
						if(!is_null($point->lat) && !is_null($point->lon)) {
							$html .= "
								gmap_bounds_".$this->id.".push(new google.maps.LatLng({$point->lat},{$point->lon}));
								gmap_markers_".$this->id."['{$point->id}'] = {};
								gmap_markers_".$this->id."['{$point->id}'].e = {$point->extra};
								gmap_markers_".$this->id."['{$point->id}'].g = new google.maps.Marker({
									".(!empty($point->icon) ? "icon: '{$point->icon}'," : '')."
									position: new google.maps.LatLng({$point->lat},{$point->lon}), 
									".($this->cluster ? "" : "map: gmap_".$this->id.",")."
									".(($point->zIndex) ? "zIndex: {$point->zIndex}," : "")."
									title: '{$this->striptags($point->name)}'
								});
								gmap_infowindows_".$this->id."['{$point->id}'] = new google.maps.InfoWindow({
									content: '{$point->name}{$this->cleanForJS($point->description)}'
								});
								google.maps.event.addListener(gmap_markers_".$this->id."['{$point->id}'].g, 'click', function() {
									if(gmap_open_info_".$this->id.") {
										gmap_open_info_".$this->id.".close();
									}
									gmap_infowindows_".$this->id."['{$point->id}'].open(gmap_".$this->id.",gmap_markers_".$this->id."['{$point->id}'].g);
									gmap_open_info_".$this->id." = gmap_infowindows_".$this->id."['{$point->id}'];
								});
								google.maps.event.addListener(gmap_infowindows_".$this->id."['{$point->id}'], 'domready', function() {
									{$point->onclick}
								});
								".($this->cluster ? "fluster_".$this->id.".addMarker(gmap_markers['{$point->id}'].g);" : "")."
							";
							
							if($point->accuracy > 0) {
								$html .= "
									var circle_".$this->id." = new google.maps.Circle({
										map: gmap_".$this->id.",
										radius: {$point->accuracy}
									});
									circle_".$this->id.".bindTo('center', gmap_markers_".$this->id."['{$point->id}'].g, 'position');
								";
							}
						}
					}
					
					$html .= $this->extra;
					
					if($this->bounds) {
						$html .="
							var gbound_".$this->id." = new google.maps.LatLngBounds();
							for(var i in gmap_bounds_".$this->id.") gbound_".$this->id.".extend(gmap_bounds_".$this->id."[i]);
							gmap_".$this->id.".fitBounds(gbound_".$this->id.");
						";
					}
					
					if($this->currentLocation) {
						$html .= '
							if(navigator.geolocation) {
								navigator.geolocation.getCurrentPosition(function(position) {
									set_gmap_user_location_'.$this->id.'(new google.maps.LatLng(position.coords.latitude,position.coords.longitude));
								});
							} else if (google.gears) {
								var ggeoloc_'.$this->id.' = google.gears.factory.create("beta.geolocation");
								ggeoloc_'.$this->id.'.getCurrentPosition(function(position) {
									set_gmap_user_location_'.$this->id.'(new google.maps.LatLng(position.latitude,position.longitude));
								});
							}
							
							function set_gmap_user_location_'.$this->id.'(gmap_user_location) {
								var gmap_user_location_point_'.$this->id.' = new google.maps.Marker({
									position: gmap_user_location,
									map: gmap_'.$this->id.',
									title: "Current location",
									icon: "http://google-maps-icons.googlecode.com/files/tickmark2.png",
									animation: google.maps.Animation.DROP
								});
								gmap_'.$this->id.'.setZoom(17);
								gmap_'.$this->id.'.setCenter(gmap_user_location);
							}
						';
					}
					
					if($this->cluster) {
						$html .= 'fluster_'.$this->id.'.initialize();';
					}
					
					$html .= '
				}
				';
				
				if($this->search) {
					$html .= '
						function gmap_do_search_'.$this->id.'() {
								gmap_addr_'.$this->id.' = document.getElementById("gmap_search_'.$this->id.'").value;
								if(gmap_addr_'.$this->id.'.match(/^\s*$/)) return;
								if(gmap_markers_'.$this->id.') {
									for(i in gmap_markers_'.$this->id.') {
										if(gmap_markers_'.$this->id.'[i].g.getTitle().toLowerCase().indexOf(gmap_addr_'.$this->id.'.toLowerCase()) != -1) {
											gmap_'.$this->id.'.setCenter(gmap_markers_'.$this->id.'[i].g.getPosition());
											gmap_'.$this->id.'.setZoom(17);
											google.maps.event.trigger(gmap_markers_'.$this->id.'[i].g, "click");
											return true;
										}
									}
								}
								geocoder_'.$this->id.' = new google.maps.Geocoder();
								geocoder_'.$this->id.'.geocode( { "address": gmap_addr_'.$this->id.'}, function(results, status) {
									if (status == google.maps.GeocoderStatus.OK) {
										gmap_'.$this->id.'.setCenter(results[0].geometry.location);
										gmap_'.$this->id.'.setZoom(13);
									} else {
										alert("Ni zadetkov.");
									}
								});
							}
					';
				}
				
				$html .= $this->html_init().' ;
			</script>
			<div id="gmap_canvas_'.$this->id.'" style="height: '.$this->map_height.'; width: '.$this->map_width.';"></div>
		';
		return $html;
	}
	
	function add_polyline($pts,$color="#0000FF") {
		$path_name = sha1(serialize($pts));
		$this->extra("var points_$path_name = [];\n");
		foreach($pts as $point) {
			if(!is_null($point->lat) && !is_null($point->lon)) {
				$this->extra("
					points_$path_name.push(new google.maps.LatLng({$point->lat},{$point->lon}));
				");
			}
		}
		$this->extra("
			var pth_$path_name = new google.maps.Polyline({
				path: points_$path_name,
				strokeColor: \"$color\",
				strokeOpacity: 1,
				strokeWeight: 2
			});
			pth_$path_name.setMap(gmap_".$this->id.");
			gmap_bounds_".$this->id." = gmap_bounds_".$this->id.".concat(points_$path_name);
		");
	}
	
	function html_init() {
		return '
			if(typeof(addLoadEvent) != "function") {
				function addLoadEvent(func) {
				  var oldonload = window.onload;
				  if (typeof window.onload != "function") {
					window.onload = func;
				  } else {
					window.onload = function() {
					  if (oldonload) {
						oldonload();
					  }
					  func();
					}
				  }
				}
			}
			'.($this->jquery ? 
			'$(document).ready(function() { kafolnet_gmaps_init_'.$this->id.'(); });' : 
			'addLoadEvent(kafolnet_gmaps_init_'.$this->id.');');
	}
}

?>