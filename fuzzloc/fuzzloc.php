<?php
/**
 * Name: Fuzzy Location
 * Description: If you have browser location enabled for your posts, provide a bit of fuzziness to your actual location
 * Version: 1.0
 * Author: Mike Macgirvin
 * Maintainer: none
 */

use Code\Lib\Apps;
use Code\Extend\Hook;
use Code\Extend\Route;


function fuzzloc_load() {

	Hook::register('post_location', 'addon/fuzzloc/fuzzloc.php', 'fuzzloc_post_hook');
	Route::register('addon/fuzzloc/Mod_Fuzzloc.php','fuzzloc');

	logger("loaded fuzzloc");
}


function fuzzloc_unload() {

	/**
	 *
	 * unload unregisters any hooks created with register_hook
	 * during load. It may also delete configuration settings
	 * and any other cleanup.
	 *
	 */

	Hook::unregister_by_file('addon/fuzzloc/fuzzloc.php');
    Hook::unregister('post_prestore', 'addon/fuzzloc/fuzzloc.php', 'fuzzloc_post_hook');

    Route::unregister('addon/fuzzloc/Mod_Fuzzloc.php','fuzzloc');

	logger("removed fuzzloc");
}



function fuzzloc_post_hook(&$item) {

	/**
	 *
	 * An item was posted on the local system.
	 * We are going to look for specific items:
	 *      - A status post by a profile owner
	 *      - The profile owner must have allowed our plugin
	 *
	 */


	if (! local_channel()) {  /* non-zero if this is a logged in user of this system */
		return;
	}

    if (! ($item['latitude'] || $item['longitude'])) {
		return;
	}

	if (! Apps::addon_app_installed(local_channel(),'fuzzloc')) {
		return;
	}

	$maxfuzz = intval(get_pconfig(local_channel(), 'fuzzloc', 'maxfuzz'));
	if (! intval($maxfuzz)) {
		return;
	}

	$minfuzz = intval(get_pconfig(local_channel(), 'fuzzloc', 'minfuzz', 0));

	if ($maxfuzz < $minfuzz) {
		// I'm sorry Dave. I'm afraid I can't do that.
		return;
	}

	logger('fuzzloc invoked',LOGGER_DEBUG);

	$lat = (float) $item['latitude'];
    $lon = (float) $item['longitude'];

	$dir1 = intval(mt_rand(0,1));
	$dir2 = intval(mt_rand(0,1));

	$offset1 = mt_rand($minfuzz,$maxfuzz);
	if ($dir1) {
		$offset1 = 0 - $offset1;
	}

	$offset2 = mt_rand($minfuzz,$maxfuzz);
	if ($dir2) {
		$offset2 = 0 - $offset2;
	}
	
	// $fuzz is in meters. 

	$lat = $lat + fuzzloc_mtod($offset1,$lat);
    $lon = $lon + fuzzloc_mtod($offset2,$lat);

	$item['latitude'] = $lat;
    $item['longitude'] = $lon;

}


function fuzzloc_mtod($meters, $latitude) {
    return $meters / (111.32 * 1000 * cos($latitude * (3.1415 / 180)));
}
