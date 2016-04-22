<?php


/**
 * Name: bookmarker
 * Description: Replace #^ with a bookmark icon which doubles as a 'save bookmarks' link. Font awesome is used for Redbasic and derived themes. A neutral dark grey PNG file is used for other themes.
 * Version: 1.1
 * MinVersion: 1.4.2
 * Author: Mike Macgirvin <mike@zothub.com>
 * Maintainer: Mike Macgirvin <mike@macgirvin.com>
 * 
 */

/**
 * TODO: Currently when a bookmark is saved, all bookmark links in the post are saved.
 * It is possible to select a specific bookmark for saving, but requires modification to
 * the JS handler itemBookmark() which only takes an item id currently. 
 */

function bookmarker_install() {
	Zotlabs\Extend\Hook::register('prepare_body', 'addon/bookmarker/bookmarker.php', 'bookmarker_prepare_body');
}


function bookmarker_uninstall() {
	Zotlabs\Extend\Hook::unregister('prepare_body', 'addon/bookmarker/bookmarker.php', 'bookmarker_prepare_body');
}

function bookmarker_prepare_body(&$b) {


	if(get_pconfig(local_channel(),'bookmarker','disable'))
		return;

	if(! strpos($b['html'],'bookmark-identifier'))
		return;

	if(function_exists('redbasic_init') || App::$theme_info['extends'] == 'redbasic')
		$bookmarkicon = '<i class="icon-bookmark"></i>';
	else 
		$bookmarkicon = '<img src="addon/bookmarker/bookmarker.png" width="19px" height="20px" alt="#^" />';

	$id = $b['item']['id'];
	if(local_channel())
		$link = '<a class="fakelink" onclick="itemBookmark(' . $id . '); return false;" title="' . t('Save Bookmarks') . '" href="#">'. $bookmarkicon . '</a> ';
	else
		$link =  $bookmarkicon . '</a> ';

	$b['html'] = str_replace('<span class="bookmark-identifier">#^</span>',$link,$b['html']);

}
