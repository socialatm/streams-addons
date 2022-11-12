<?php

/**
 * Name: Dreamhost
 * Description: Improved operation on Dreamhost shared hosting
 * Version: 1.0
 * Maintainer: none;
 */



function dreamhost_load() {
	Code\Extend\Hook::register('page_not_found', 'addon/dreamhost/dreamhost.php','dreamhost_page_not_found');
	Code\Extend\Hook::register('startup', 'addon/dreamhost/dreamhost.php','dreamhost_init');
}

function dreamhost_unload() {
	Code\Extend\Hook::unregister('page_not_found', 'addon/dreamhost/dreamhost.php','dreamhost_page_not_found');
	Code\Extend\Hook::unregister('startup', 'addon/dreamhost/dreamhost.php','dreamhost_init');
}

function dreamhost_page_not_found($x) {

	if((array_key_exists('QUERY_STRING',$_SERVER))
		&& ($_SERVER['QUERY_STRING'] === 'q=internal_error.html')) { 
			logger('Original URI =' . $_SERVER['REQUEST_URI'],LOGGER_DEBUG);
			goaway(z_root() . $_SERVER['REQUEST_URI']);
	}

}

function dreamhost_init($x) {
	@ini_set('pcre_jit','0');
}