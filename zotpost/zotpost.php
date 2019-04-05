<?php

use Zotlabs\Extend\Hook;
use Zotlabs\Extend\Route;
use Zotlabs\Lib\Apps;

/**
 * Name: Zot Crosspost Connector (zotpost)
 * Description: Relay public postings to another Zot or Zot6 channel
 * Version: 1.0
 * Maintainer: none
 */
 
/*
 *   Hubzilla to Hubzilla
 */

require_once('include/permissions.php');

function zotpost_load() {

	Hook::register('notifier_normal', 'addon/zotpost/zotpost.php', 'zotpost_post_hook');
	Hook::register('post_local', 'addon/zotpost/zotpost.php', 'zotpost_post_local');
	Hook::register('jot_networks',    'addon/zotpost/zotpost.php', 'zotpost_jot_nets');
	Route::register('addon/zotpost/Mod_zotpost.php','zotpost');
}


function zotpost_unload() {
	Hook::unregister('notifier_normal', 'addon/zotpost/zotpost.php', 'zotpost_post_hook');
	Hook::unregister('post_local', 'addon/zotpost/zotpost.php', 'zotpost_post_local');
	Hook::unregister('jot_networks',    'addon/zotpost/zotpost.php', 'zotpost_jot_nets');
	Route::unregister('addon/zotpost/Mod_zotpost.php','zotpost');

}

function zotpost_jot_nets(&$b) {
    if((! local_channel()) || (! perm_is_allowed(local_channel(),'','view_stream')))
        return;

	if(Apps::addon_app_installed(local_channel(),'zotpost')) {
		$zotpost_defpost = get_pconfig(local_channel(),'zotpost','post_by_default');
		$selected = ((intval($zotpost_defpost) == 1) ? ' checked="checked" ' : '');
		$b .= '<div class="profile-jot-net"><input type="checkbox" name="zotpost_enable"' . $selected . ' value="1" > ' 
			. '<img src="images/zot-300.png" alt="zotpost" style="height: 32px; width: 32px;"> ' . t('Post to Zot') . '</div>';
	}
}


function zotpost_post_local(&$b) {
	if($b['created'] != $b['edited'])
		return;

	if(! perm_is_allowed($b['uid'],'','view_stream'))
		return;

	if((local_channel()) && (local_channel() == $b['uid']) && (! $b['item_private'])) {

		$zotpost_post   = Apps::addon_app_installed(local_channel(),'zotpost');
		$zotpost_enable = (($zotpost_post && x($_REQUEST,'zotpost_enable')) ? intval($_REQUEST['zotpost_enable']) : 0);

		// if API is used, default to the chosen settings
		if($_REQUEST['api_source'] && intval(get_pconfig(local_channel(),'zotpost','post_by_default')))
			$zotpost_enable = 1;

		if(! $zotpost_enable)
			return;

       if(strlen($b['postopts']))
           $b['postopts'] .= ',';
       $b['postopts'] .= 'zotpost';
    }
}


function zotpost_post_hook(&$b) {

	/**
	 * Post to Zot
	 */

	// for now, just top level posts.

	if($b['mid'] != $b['parent_mid'])
		return;

	// for now, no forum or wall to wall posts

	if($b['author_xchan'] !== $b['owner_xchan'])
		return;


	if((! is_item_normal($b)) || $b['item_private'] || ($b['created'] !== $b['edited']))
		return;

	if(! perm_is_allowed($b['uid'],'','view_stream'))
		return;

	if(! strstr($b['postopts'],'zotpost'))
		return;

	logger('zotpost invoked');

	load_pconfig($b['uid'], 'zotpost');

	
	$api      = get_pconfig($b['uid'], 'zotpost', 'server');
	$api      = rtrim($api,'/') . '/api';	

	$password = unobscurify(get_pconfig($b['uid'], 'zotpost', 'password'));
	$channel  = get_pconfig($b['uid'], 'zotpost', 'channel');

	$postdata =  [ 'body' => $b['body'], 'title' => $b['title'], 'source' => (($b['app']) ? : 'ZAP/ZotPost') ];

	if(strlen($b['body'])) {
		$ret = z_post_url($api . '/red/item/update', $postdata, 0, [ 'http_auth' => $channel . ':' . $password ]);
		if($ret['success'])
			logger('zotpost: returns: ' . print_r($ret['body'],true));
		else
			logger('zotpost: z_post_url failed: ' . print_r($ret['debug'],true));
	}
}

