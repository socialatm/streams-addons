<?php

use Zotlabs\Extend\Hook;
use Zotlabs\Extend\Route;
use Zotlabs\Extend\Widget;

/**
 * Name: wiki
 * Description: Provides a personal wiki for channels
 * Version: 1.0
 *
 */


function wiki_load() {

	Hook::register('permissions_list','addon/wiki/wiki.php','wiki_permissions_list');
	Hook::register('permission_limits_get','addon/wiki/wiki.php','wiki_permission_limits_get');
	Hook::register('get_role_perms','addon/wiki/wiki.php','wiki_get_role_perms');
	Route::register('addon/wiki/Mod_Wiki.php','wiki');
	Widget::register('addon/wiki/Wiki_list.php','wiki_list');
	Widget::register('addon/wiki/Wiki_pages.php','wiki_pages');
	Widget::register('addon/wiki/Wiki_page_history.php','wiki_page_history');

}

function wiki_unload() {

	Hook::unregister('permissions_list','addon/wiki/wiki.php','wiki_permissions_list');
	Hook::unregister('permission_limits_get','addon/wiki/wiki.php','wiki_permission_limits_get');
	Hook::unregister('get_role_perms','addon/wiki/wiki.php','wiki_get_role_perms');
	Route::unregister('addon/wiki/Mod_Wiki.php','wiki');
	Widget::unregister('addon/wiki/Wiki_list.php','wiki_list');
	Widget::unregister('addon/wiki/Wiki_pages.php','wiki_pages');
	Widget::unregister('addon/wiki/Wiki_page_history.php','wiki_page_history');

}



function wiki_permissions_list(&$x) {

	$x['permissions'] = array_merge($x['permissions'],[
		'view_wiki'     => t('Can view my wiki pages'),
		'write_wiki'    => t('Can write to my wiki pages')
	]);

}


function wiki_permission_limits_get(&$x) {

	// In the absence of existing permissions, copy the view_stream for read access 
	// and the post_wall permission for write access

	if($x['permission'] === 'view_wiki') {
		$x['value'] = \Zotlabs\Access\PermissionLimits::Get($x['channel_id'],'view_stream');
		\Zotlabs\Access\PermissionLimits::Set($x['channel_id'],$x['permission'],$x['value']);
	}

	if($x['permission'] === 'write_wiki') {
		$x['value'] = \Zotlabs\Access\PermissionLimits::Get($x['channel_id'],'post_wall');
		\Zotlabs\Access\PermissionLimits::Set($x['channel_id'],$x['permission'],$x['value']);
	}

}

function wiki_get_role_perms(&$x) {

	switch($x['role']) {
		case 'custom':
		case '':
			break;

		case 'forum_private':
			$x['result']['limits']['view_wiki'] = PERMS_SPECIFIC;
			$x['result']['perms_connect'][] = 'view_wiki';
			break;

		case 'repository':
			$x['result']['perms_connect'][] = 'write_wiki';

		default:
			$x['result']['perms_connect'][] = 'view_wiki';
			break;
	}
}