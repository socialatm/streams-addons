<?php

/**
 * Name: Opensearch
 * Description: Opensearch provider interface
 * Version: 1.0
 * Author: Mike Macgirvin
 * Maintainer: none
 */


use Code\Extend\Hook;
use Code\Extend\Route;
use Code\Lib\System;

function opensearch_load() {
	Hook::register('build_pagehead','addon/opensearch/opensearch.php','opensearch_build_pagehead');
	Route::register('addon/opensearch/Mod_opensearch.php', 'opensearch');

}

function opensearch_unload() {
	Hook::unregister('build_pagehead','addon/opensearch/opensearch.php','opensearch_build_pagehead');
	Route::unregister('addon/opensearch/Mod_opensearch.php', 'opensearch');
}

function opensearch_build_pagehead($x) {

	head_add_link([ 
		'rel' => 'search', 
		'href' => z_root() . '/opensearch', 
		'type' => 'application/opensearchdescription+xml',
		'title' => sprintf( t('Search %1$s (%2$s)','opensearch'), 
			System::get_site_name(), 
			t('$Projectname','opensearch'))
	]);
}

