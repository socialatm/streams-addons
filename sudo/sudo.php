<?php

/**
 * Name: Sudo
 * Description: Allow site administrator to access/administer protected channels and content
 * Author: Mike Macgirvin
 * Version: 1.0
 */

use Code\Extend\Hook;
use Code\Extend\Route;


function sudo_load() {
	Hook::register('admin_channels','addon/sudo/sudo.php','sudo_admin_channels');
	Route::register('addon/sudo/Mod_Sudo.php','sudo');
}


function sudo_unload() {
	Hook::unregister('admin_channels','addon/sudo/sudo.php','sudo_admin_channels');
	Route::unregister('addon/sudo/Mod_Sudo.php','sudo');
}



function sudo_admin_channels(&$channels) {
	if ($channels) {
		for ($x = 0; $x < count($channels); $x ++) {
			$channels[$x]['channel_link'] = z_root() . '/sudo/' . $channels[$x]['channel_address'];
		}
	}
}
