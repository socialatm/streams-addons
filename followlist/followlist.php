<?php

/**
 *
 * Name: Followlist
 * Description: follow everybody in an ActivityPub followers/following list
 * Version: 1.0
 */

use Zotlabs\Extend\Route;

function followlist_load() {
	Route::register('addon/followlist/Mod_followlist.php','followlist');
}

function followlist_unload() {
	Route::unregister('addon/followlist/Mod_followlist.php','followlist');
}