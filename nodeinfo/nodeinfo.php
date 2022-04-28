<?php

/**
 * Name: Nodeinfo
 * Description: Provide site discovery
 * Version: 3.0
 * Author: Mike Macgirvin
 * Author: Michael Vogel <https://pirati.ca/profile/heluecht>
 * Maintainer: none
 */

use Code\Extend\Hook;
use Code\Extend\Route;


function nodeinfo_load() {
	Hook::register('cron_weekly', 'addon/nodeinfo/nodeinfo.php', 'nodeinfo_cron_weekly');
	Hook::register('well_known', 'addon/nodeinfo/nodeinfo.php', 'nodeinfo_well_known');
	Route::register('addon/nodeinfo/Mod_nodeinfo.php', 'nodeinfo');
}


function nodeinfo_unload() {
	Hook::unregister('cron_weekly', 'addon/nodeinfo/nodeinfo.php', 'nodeinfo_cron_weekly');
	Hook::unregister('well_known', 'addon/nodeinfo/nodeinfo.php', 'nodeinfo_well_known');
	Route::unregister('addon/nodeinfo/Mod_nodeinfo.php', 'nodeinfo');
}


function nodeinfo_well_known(&$a) {
	if(argc() > 1 && argv(1) === 'nodeinfo') {
		$arr = [ 'links' => [
			[
				'rel' => 'http://nodeinfo.diaspora.software/ns/schema/2.0',
				'href' => z_root() . '/nodeinfo/2.0'
			],

		]];

		header('Content-type: application/json');
		echo json_encode($arr);
		killme();
	}
}


function nodeinfo_cron_weekly($a) {

	// Now trying to register
	$url = "https://the-federation.info/register/" . App::get_hostname();

	$ret = z_fetch_url($url);
//	logger('nodeinfo_cron: registering answer: '. print_r($ret,true), LOGGER_DEBUG);

}

