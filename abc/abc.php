<?php
/**
 * Name: ABC Music
 * Description: Render ABC music notation as musical scores
 * Version: 1.0Z 
 * Author: Olivier Migeot <https://abcentric.net/profile/olivierm>
 * Maintainer: Mike Macgirvin <https://z.macgirvin.com/channel/mike>
 */

use Zotlabs\Extend\Hook;

function abc_load() {
	Hook::register('page_end', 'addon/abc/abc.php', 'abc_page');
	Hook::register('bbcode', 'addon/abc/abc.php', 'abc_bbcode');
}


function abc_unload() {
	Hook::unregister_by_file('addon/abc/abc.php');
}


function abc_page($x) {
	head_add_js('/addon/abc/abc.js');
}

function abc_bbcode(&$a) {

	if (in_array('activitypub',$a['options']) || in_array('export',$a['options'])) {
		return;
	}

	$rand = '_'.substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10); 

	$a['text'] = preg_replace("/\[abc\](.*?)\[\/abc\]/ism",'<div class="abc-wrapper"><code id="abcmusic'.$rand.'">' . "$1" . '</code><div id="notation'.$rand.'"></div></div><script>loadAbc(\''.$rand.'\');</script>',$a['text']);

}
