<?php

use Zotlabs\Extend\Route;

/**
 * Name: Hexit
 * Description: Hexadecimal Conversion Tool
 * Version: 1.0
 * Author: Macgirvin
 * Maintainer: none
 *
 */

function hexit_load() {
	Route::register('addon/hexit/Mod_Hexit.php','Hexit');
}

function hexit_unload() {
	Route::unregister_by_file('addon/hexit/Mod_Hexit.php');
}




