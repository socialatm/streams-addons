<?php

use Code\Extend\Route;

/**
 * Name: Hexit
 * Description: Hexadecimal Conversion Tool
 * Version: 1.0
 * Author: Macgirvin
 * Maintainer: none
 *
 */

function hexit_load() {
	Route::register('addon/hexit/Mod_Hexit.php','hexit');
}

function hexit_unload() {
	Route::unregister('addon/hexit/Mod_Hexit.php','hexit');
}




