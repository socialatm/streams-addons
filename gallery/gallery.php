<?php

/**
 * Name: Gallery
 * Description: Image Gallery
 * Version: 0.1
 * MinVersion: 3.4
 * Author: Mario
 * Maintainer: Mario
 */

require_once('addon/gallery/Mod_Gallery.php');

function gallery_module() {}

function gallery_load() {
	Zotlabs\Extend\Hook::register('load_pdl', 'addon/gallery/gallery.php', 'gallery_load_pdl');
}

function gallery_unload() {
	Zotlabs\Extend\Hook::unregister('load_pdl', 'addon/gallery/gallery.php', 'gallery_load_pdl');
}

function gallery_load_pdl(&$b) {
	if ($b['module'] === 'gallery') {
		$b['layout'] = '
			[region=aside]
			[widget=vcard][/widget]
			[/region]
			[region=right_aside]
			[widget=notifications][/widget]
			[widget=newmember][/widget]
			[/region]
		';
	}
}


