<?php


/**
 * Name: Standard Embeds
 * Description: Allow unfiltered access to embeds from top media providers
 * Version: 1.0
 * Author: Mike Macgirvin
 * Maintainer: none
 */

function std_embeds_load() {
	Zotlabs\Extend\Hook::register('oembed_action','addon/std_embeds/std_embeds.php','std_embeds_action');
}

function std_embeds_unload() {
	Zotlabs\Extend\Hook::unregister_by_file('addon/std_embeds/std_embeds.php');
}

function std_embeds_action(&$arr) {

	if($arr['action'] === 'block')
		return;

	$m = parse_url($arr['url']);


	$realurl = '';

	// Prevent hostname forgeries to get around host restrictions by providing our own URL replacements.
	// So https://youtube.com.badguy.com/watch/111111111 and https://foobar.com/youtube.com/watch/111111111 and
	// https://foobar.com/?fakeurl=https://youtube.com/watch/111111 will not be allowed unfiltered access.

	$s = array(
		'youtube'    => 'https://youtube.com',
		'youtu.be'   => 'https://youtube.com',
		'vimeo'      => 'https://vimeo.com',
		'soundcloud' => 'https://soundcloud.com'
	);

	foreach($s as $k => $v) {
		if(strpos($m['host'],$k) !== false) {
			logger('found: ' . $k);
			$realurl = $v;
			break;
		}
	}

	if($realurl) {
		$arr['url'] = $realurl . (($m['path']) ? '/' . $m['path'] : '') . (($m['query']) ? '?' . $m['query'] : '') . (($m['fragment']) ? '#' . $m['fragment'] : ''); 
		$arr['action'] = 'allow';
		logger('allowed');
	}

}