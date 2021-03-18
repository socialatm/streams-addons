<?php

/**
 * Name: Nodeinfo Statistics
 * Description: Generates some statistics for the-federation.info
 * Version: 3.0
 * Author: Mike Macgirvin
 * Author: Michael Vogel <https://pirati.ca/profile/heluecht>
 * Maintainer: none
 */

use Zotlabs\Extend\Hook;
use Zotlabs\Extend\Route;


function statistics_load() {
	Hook::register('cron_weekly', 'addon/statistics/statistics.php', 'statistics_cron_weekly');
	Hook::register('well_known', 'addon/statistics/statistics.php', 'statistics_well_known');
	Route::register('addon/statistics/Mod_nodeinfo.php', 'nodeinfo');
}


function statistics_unload() {
	Hook::unregister('cron_weekly', 'addon/statistics/statistics.php', 'statistics_cron_weekly');
	Hook::unregister('well_known', 'addon/statistics/statistics.php', 'statistics_well_known');
	Route::unregister('addon/statistics/Mod_nodeinfo.php', 'nodeinfo');
}


function statistics_well_known(&$a) {
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


function statistics_cron_weekly($a) {

	// Now trying to register
	$url = "https://the-federation.info/register/" . App::get_hostname();

	$ret = z_fetch_url($url);
//	logger('statistics_cron: registering answer: '. print_r($ret,true), LOGGER_DEBUG);

}

