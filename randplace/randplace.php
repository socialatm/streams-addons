<?php

/**
 *
 * Name: Random Place
 * Description: Sample plugin, sets a random place when posting.
 * Version: 1.0
 * Author: Mike Macgirvin <mike@macgirvin.com>
 *
 */

use Code\Lib\Apps;

function randplace_load() {
	Code\Extend\Hook::register('post_local', 'addon/randplace/randplace.php', 'randplace_post_hook');
	Code\Extend\Route::register('addon/randplace/Mod_randplace.php', 'randplace');
}

function randplace_unload() {
	Code\Extend\Hook::unregister('post_local', 'addon/randplace/randplace.php', 'randplace_post_hook');
	Code\Extend\Route::unregister('addon/randplace/Mod_randplace.php', 'randplace');
}

function randplace_post_hook(&$item) {

	/**
	 *
	 * An item was posted on the local system.
	 * We are going to look for specific items:
	 *	  - A status post by a profile owner
	 *	  - The profile owner must have allowed our plugin
	 *
	 */

	logger('randplace invoked');

	if (! local_channel()) {
		/* non-zero if this is a logged in user of this system */
		return;
	}

	if (local_channel() !== intval($item['uid'])) {
		/* Does this person own the post? */
		return;
	}

	if (($item['parent']) || (! is_item_normal($item))) {
		/* If the item has a parent, or is not "normal", this is a comment or something else, not a status post. */
		return;
	}

	/* Only proceed if the 'randplace' addon is installed and the current channel has installed the 'randplace' app */

	$active = Apps::addon_app_installed(local_channel(), 'randplace');

	if (! $active) {
		/* We haven't installed or enabled it. Do nothing. */
		return;
	}
		
	/**
	 *
	 * OK, we're allowed to do our stuff.
	 * Here's what we are going to do:
	 * load the list of timezone names, and use that to generate a list of world cities.
	 * Then we'll pick one of those at random and put it in the "location" field for the post.
	 * We'll filter out some entries from the list of timezone names which really aren't physical locations. 
	 */

	$cities = [];
	$zones = timezone_identifiers_list();
	foreach ($zones as $zone) {
		if ((strpos($zone,'/')) && (stristr($zone,'US/') === false) && (stristr($zone,'Etc/') === false)) {
			$cities[] = str_replace('_', ' ',substr($zone,strrpos($zone,'/') + 1));
		}
	}

	if (! count($cities)) {
		return;
	}
		
	// select one at random and store it in $item['location']
	$item['location'] = $cities[array_rand($cities,1)];

	return;
}

