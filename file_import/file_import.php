<?php

/**
 * Name: Cloud File Importer
 * Description: import file storage from another project site
 * Version: 1.0
 * Author: Mike Macgirvin
 * Maintainer: Mike Macgirvin <mike@macgirvin.com>
 */

use Zotlabs\Extend\Route;

function file_import_install() {
	Route::register('addon/file_import/Mod_file_import.php','file_import');	
}

function file_import_uninstall() {
	Route::unregister('addon/file_import/Mod_file_import.php','file_import');	
}
