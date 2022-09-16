<?php
/**
 * Name: OpenStreetMap
 * Description: Use OpenStreetMap for displaying locations. 
 * Version: 1.3
 * Author: Klaus Weidenbach
 * Maintainer: none
 */

use Code\Extend\Hook;
use Code\Lib\Head;
use Code\Lib\Url;
use Code\Render\Theme;
    
function openstreetmap_load() {
	Hook::register('render_location', 'addon/openstreetmap/openstreetmap.php', 'openstreetmap_location');
	Hook::register('generate_map', 'addon/openstreetmap/openstreetmap.php', 'openstreetmap_generate_map');
	Hook::register('generate_named_map', 'addon/openstreetmap/openstreetmap.php', 'openstreetmap_generate_named_map');
	Hook::register('page_header', 'addon/openstreetmap/openstreetmap.php', 'openstreetmap_alterheader');

	logger("installed openstreetmap");
}

function openstreetmap_unload() {
	Hook::unregister_by_file('addon/openstreetmap/openstreetmap.php');

	logger("removed openstreetmap");
}

function openstreetmap_alterheader($navHtml) {
	Head::add_js('/addon/openstreetmap/openstreetmap.js');
}

/**
 * @brief Add link to a map for an item's set location/coordinates.
 *
 * If an item has coordinates add link to a tile map server, e.g. openstreetmap.org.
 * If an item has a location open it with the help of OSM's Nominatim reverse geocode search.
 * 
 * @param array& $item
 */
function openstreetmap_location(&$item) {

    $itemlocation = $item['location'];
    $lat = $item['lat'];
    $lon = $item['lon'];

    if (! ($lat || $lon)) {
        if ($item['coord']) {
            $tmp = explode(' ', trim($item['coord']));
            if (count($tmp) > 1) {
                $lat = $tmp[0];
                $lon = $tmp[1];
            }
        }
    }
    if (!$itemlocation && !($lat || $lon)) {
        return;
    }

	/*
	 * Get the configuration variables from the config.
	 * @todo Separate the tile map server from the text-string to map tile server 
	 * since they apparently use different URL conventions.
	 * We use OSM's current convention of "#map=zoom/lat/lon" and optional
	 * ?mlat=lat&mlon=lon for markers.
	 */

	$tmsserver = get_config('openstreetmap', 'tmsserver', 'http://www.openstreetmap.org');

	$nomserver = get_config('openstreetmap', 'nomserver','http://nominatim.openstreetmap.org/search.php');

	$zoom = intval(get_config('openstreetmap', 'zoom', 16));
	if ($zoom < 1 || $zoom > 18) {
		$zoom = 16;
	}

	$marker = get_config('openstreetmap', 'marker', 1);
    
	$coord = $lat || $lon;

	$rendered_loc = (($itemlocation && !$coord) ? '<a target="map" title="' . $itemlocation . '" href="'.$nomserver . '?q=' . urlencode($itemlocation) . '">' . $itemlocation . '</a>' : $itemlocation);

	if ($coord) {
        $lat = urlencode(round(floatval($lat), 5));
    	$lon = urlencode(round(floatval($lon), 5));

        $rendered_coord = '<a target="map" class="OSMMapLink" title="' . $lat . ' ' . $lon . '" href="' . $tmsserver ;
        if (intval($marker)) {
            $rendered_coord .= '?mlat=' . $lat . '&mlon=' . $lon;
        }
        $rendered_coord .= '#map=' . intval($zoom) . '/' . $lat . '/' . $lon .'">' . t('Map') . '</a>';
	}

	if ($rendered_coord) {
		if ($rendered_loc) {
			$rendered_loc .= '&nbsp;<span class="smalltext">(' . $rendered_coord . ')</span>';
		}
		else {
			$rendered_loc = '<span class="smalltext">' . $rendered_coord . '</span>';
		}
	}
	$item['html'] = $rendered_loc;
}


function openstreetmap_generate_named_map(&$b) {

	$nomserver = get_config('openstreetmap', 'nomserver','http://nominatim.openstreetmap.org/search.php');

	$args = '?q=' . urlencode($b['location']) . '&format=json';

	$x = Url::get($nomserver . $args);

	if ($x['success']) {
		$j = json_decode($x['body'],true);
		if ($j && is_array($j) && $j[0]['lat'] && $j[0]['lon']) {
			$arr = array('lat' => $j[0]['lat'],'lon' => $j[0]['lon'],'location' => $b['location'], 'html' => '');
			openstreetmap_generate_map($arr);
			$b['html'] = $arr['html'];
		}
	}
}

function openstreetmap_generate_map(&$b) {

	$tmsserver = get_config('openstreetmap', 'tmsserver', 'http://www.openstreetmap.org');

	if (strpos(z_root(),'https:') !== false) {
		$tmsserver = str_replace('http:','https:',$tmsserver);
	}

	$zoom = intval($b['zoom']);

	if (! $zoom) {
		$zoom = intval(get_config('openstreetmap', 'zoom'));
	}
	
	if ($zoom < 1 || $zoom > 18) {
		$zoom = 16;
	}

	$marker = get_config('openstreetmap', 'marker', 1);

	$lat = (float) $b['lat']; 
	$lon = (float) $b['lon']; 

	logger('lat: ' . $lat, LOGGER_DATA);
	logger('lon: ' . $lon, LOGGER_DATA);
	logger('zoom: ' . $zoom, LOGGER_DATA);

	$bbox = osm_latlon_to_bbox($lat, $lon, $zoom);

	if (is_numeric($lat) && is_numeric($lon)) {
		$b['html'] = '<iframe style="width:100%; height:480px; border:1px solid #ccc" src="' . $tmsserver . '/export/embed.html?bbox=' . $bbox[1] . '%2C' . $bbox[2] . '%2C' . $bbox[3] . '%2C' . $bbox[0] ;

		$b['html'] .=  '&amp;layer=mapnik&amp;marker=' . $lat . '%2C' . $lon . '" style="border: 1px solid black"></iframe><br/><small><a href="' . $tmsserver . '/?mlat=' . $lat . '&mlon=' . $lon . '#map=' . $zoom . '/' . $lat . '/' . $lon . '">' . (($b['location']) ? escape_tags($b['location']) : t('View Larger')) . '</a></small>';

		logger('generate_map: ' . $b['html'], LOGGER_DATA);
	}

}

function osm_gettile($lat,$lon,$zoom) {

	$xtile = floor((($lon + 180) / 360) * pow(2, $zoom));
	$ytile = floor((1 - log(tan(deg2rad($lat)) + 1 / cos(deg2rad($lat))) / pi()) /2 * pow(2, $zoom));
	return [ $xtile, $ytile ];
}

function osm_getlatlon($xtile,$ytile,$zoom) {
	$n = pow(2, $zoom);
	$lon_deg = $xtile / $n * 360.0 - 180.0;
	$lat_deg = rad2deg(atan(sinh(pi() * (1 - 2 * $ytile / $n))));
	return [ $lat_deg, $lon_deg ];
}


function osm_latlon_to_bbox($lat, $lon, $zoom) {

	$width = 640;
	$height = 480;	# note: must modify this to match your embed map width/height in pixels
	$tile_size = 256;

	$t = osm_gettile($lat,$lon,$zoom);
	$xtile = $t[0];
	$ytile = $t[1];


	$xtile_s = ($xtile * $tile_size - $width/2) / $tile_size;
	$ytile_s = ($ytile * $tile_size - $height/2) / $tile_size;
	$xtile_e = ($xtile * $tile_size + $width/2) / $tile_size;
	$ytile_e = ($ytile * $tile_size + $height/2) / $tile_size;

	$s = osm_getlatlon($xtile_s,$ytile_s, $zoom);
	$lat_s = $s[0];
	$lon_s = $s[1];
	
	$e = osm_getlatlon($xtile_e,$ytile_e,$zoom);
	$lat_e = $e[0];
	$lon_e = $e[1];
	return [ $lat_s, $lon_s, $lat_e, $lon_e ];

}


function openstreetmap_plugin_admin(&$o) {
	$t = Theme::get_template("admin.tpl", "addon/openstreetmap/");
	$tmsserver = get_config('openstreetmap', 'tmsserver', 'http://www.openstreetmap.org');
	$nomserver = get_config('openstreetmap', 'nomserver', 'http://nominatim.openstreetmap.org/search.php');
	$zoom = intval(get_config('openstreetmap', 'zoom', 16));
	if ($zoom < 1 || $zoom > 18) {
		$zoom = 16;
	}
	$marker = get_config('openstreetmap', 'marker', 1);

	$o = replace_macros($t, [
			'$submit' => t('Submit'),
			'$tmsserver' => [ 'tmsserver', t('Tile Server URL'), $tmsserver, t('A list of <a href="http://wiki.openstreetmap.org/wiki/TMS" target="_blank">public tile servers</a>')],
			'$nomserver' => [ 'nomserver', t('Nominatim (reverse geocoding) Server URL'), $nomserver, t('A list of <a href="http://wiki.openstreetmap.org/wiki/Nominatim" target="_blank">Nominatim servers</a>')],
			'$zoom' => [ 'zoom', t('Default zoom'), $zoom, t('The default zoom level. (1:world, 18:highest, also depends on tile server)')],
			'$marker' => [ 'marker', t('Include marker on map'), $marker, t('Include a marker on the map.')],
	]);
}

function openstreetmap_plugin_admin_post(&$a) {
	$urltms = ((x($_POST, 'tmsserver')) ? notags(trim($_POST['tmsserver'])) : 'http://www.openstreetmap.org');
	$urlnom = ((x($_POST, 'nomserver')) ? notags(trim($_POST['nomserver'])) : 'http://nominatim.openstreetmap.org/search.php');
	$zoom = ((x($_POST, 'zoom')) ? intval(trim($_POST['zoom'])) : '16');
	$marker = ((x($_POST, 'marker')) ? intval(trim($_POST['marker'])) : '0');
	set_config('openstreetmap', 'tmsserver', $urltms);
	set_config('openstreetmap', 'nomserver', $urlnom);
	set_config('openstreetmap', 'zoom', $zoom);
	set_config('openstreetmap', 'marker', $marker);
	info( t('Settings updated.') . EOL);
}
