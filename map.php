<?php
/* 
Plugin Name: Posts on a map
Plugin URI: http://www.kafol.net
Description: Show a Google map with placemarks of geo-tagged posts. Can be used to show places you visited with links to the blog post, a hiking book, etc.
Author: Jean Caffou
Version: 1.1 
Author URI: http://www.kafol.net
*/ 

require_once('class.point.php');
require_once('class.map.php');

define('jc_domain','jc_domain');
define('jc_gps_field','_jc_gps_field');
define('jc_nonce','jc_nonce');

load_plugin_textdomain(jc_domain, false, basename(dirname( __FILE__ )).'/languages');

add_action('admin_init', 'jc_admin_init');
function jc_admin_init(){
	register_setting('jc-settings-group', 'jc_posts_map_id');
	register_setting('jc-settings-group', 'jc_icon_url');
}

function jc_maps_admin() {
	?>
	<div class="wrap">
	<h2>Posts on a map settings</h2>

	<form method="post" action="options.php">
		<?php settings_fields( 'jc-settings-group' ); ?>
		<table class="form-table">
			<tr valign="top">
			<th scope="row"><?php _e( 'Select page to show all posts on a map', jc_domain) ?></th>
			<td><select name="jc_posts_map_id"><?php
				foreach(get_pages() as $page) {
					$option = '<option value="'.$page->ID.'" '.(get_option('jc_posts_map_id') == $page->ID ? 'selected="selected"' : '').'>';
					$option .= $page->post_title;
					$option .= '</option>';
					echo $option;
				}
			?>
			</select></td>
			</tr>
			
			<tr valign="top">
			<th scope="row"><a href="http://mapicons.nicolasmollet.com/" target="_blank"><?php _e( 'Marker icon', jc_domain) ?></a></th>
			<td><input type="text" name="jc_icon_url" value="<?php echo jc_ico(); ?>" /> <img src="<?php echo jc_ico(); ?>" alt="" /></td>
			</tr>
		</table>
		
		<?php submit_button(); ?>

	</form>
	</div>
	<?php
}

function jc_admin_menu() {
	add_options_page("Posts on a map settings", "Posts on a map settings", 'edit_plugins', "jc_posts_map_settings", "jc_maps_admin");
}
add_action('admin_menu', 'jc_admin_menu');

function jc_google_maps() {
	?>
	<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=true"></script>
	<style type="text/css">.jc_map img { max-width: none; }</style>
	<?php
}
add_filter('wp_head', 'jc_google_maps');

function jc_add_map_to_content($content) {
	global $post;
	
	$gps = get_post_meta($post->ID,jc_gps_field,true);
	if(!empty($gps)) {
		$ico = jc_ico();
		$points = array(new point($post->ID,$gps,0,jc_title_link($post->ID),'',$ico));
	}
	
	if($post->ID == get_option('jc_posts_map_id')) {
		$args = array('meta_key' => jc_gps_field, 'posts_per_page' => -1);
		
		$query = new WP_Query($args); 
		$points = array();
		while($query->have_posts()) {
			$query->next_post();
			$id = $query->post->ID;
			$gps = get_post_meta($id,jc_gps_field,true);
			if(!empty($gps)) {
				$points[] = new point($id,$gps,0,jc_title_link($id),__( 'Categories:', jc_domain).' '.get_the_category_list(', ','', $id),jc_ico());
			}
		}
	}
	
	if(isset($points) && count($points) > 0) {
		$many = count($points) > 1;
		
		if($many) {
			$content .= '<h3>'.__( 'Map', jc_domain).'</h3>';
		}
		
		$map = new map($points);
		$map->zoom = 11;
		$map->bounds = $many;
		$content .= '<div class="jc_map" style="width: 100%; height: 350px;">'.$map->output().'</div>';
		
		if($many) {
			$content .= '<div class="jc_list_points"><h3>'.__( 'Points', jc_domain).'</h3><ul>';
			foreach($points as $point) {
				$content .= "<li>{$point->name}</li>";
			}
			$content .= '</ul></div>';
		}
	}
	
	return $content;
}
add_filter('the_content', 'jc_add_map_to_content');

function jc_add_gps_field() {
	add_meta_box( 
		'jc_gps',
		__( 'GPS coordinates', jc_domain),
		'jc_print_gps_field',
		'post','side','high'
	);
}
add_action('add_meta_boxes', 'jc_add_gps_field');

function jc_save_gps_meta($post_id) {
	//if(defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE) return;
	
	if(!wp_verify_nonce($_POST['jc_nonce'], plugin_basename(__FILE__))) return;
	
	if ('page' == $_POST['post_type']) {
		if(!current_user_can('edit_page', $post_id)) return;
	} else {
		if(!current_user_can('edit_post', $post_id)) return;
	}
	
	$gps = point::format(stripslashes($_POST[jc_gps_field]));
	if($gps) {
		update_post_meta($post_id,jc_gps_field,$gps);
	} else {
		delete_post_meta($post_id,jc_gps_field);
	}
}
add_action('save_post', 'jc_save_gps_meta');

function jc_print_gps_field() {
	$id = jc_editor_get_post_id();
	?><input type="text" name="<?php echo jc_gps_field; ?>" value="<?php echo get_post_meta($id,jc_gps_field,true); ?>" style="width:100%" /><?php
	wp_nonce_field(plugin_basename(__FILE__), jc_nonce);
}

function jc_editor_get_post_id() {
	return isset($_GET['post']) ? $_GET['post'] : $_POST['post_ID'];
}

function jc_ico() {
	$ico = get_option('jc_icon_url');
	return (empty($ico)) ? jc_dirname().'point.png' : $ico;
}

function jc_title_link($id) {
	return '<a href="'.get_permalink($id).'">'.get_the_title($id).'</a>';
}

function jc_dirname() {
	return WP_PLUGIN_URL.'/'.dirname(plugin_basename(__FILE__)).'/';
}
?>