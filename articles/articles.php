<?php

use Zotlabs\Extend\Route;

/**
 * Name: Articles
 * Description: provide a personal blog which accepts comments but does not federate
 * Version: 1
 */



function articles_load() {
	Route::register('addon/articles/Mod_Articles.php','articles');
	Route::register('addon/articles/Mod_Article_edit.php','article_edit');
	Hook::register('permissions_list','addon/articles/wiki.php','articles_permissions_list');
	Hook::register('permission_limits_get','addon/articles/articles.php','articles_permission_limits_get');
	Hook::register('get_role_perms','addon/articles/articles.php','articles_get_role_perms');

}

function articles_unload() {
	Route::unregister('addon/articles/Mod_Articles.php','articles');
	Route::unregister('addon/articles/Mod_Article_edit.php','article_edit');
	Hook::unregister('permissions_list','addon/articles/wiki.php','articles_permissions_list');
	Hook::unregister('permission_limits_get','addon/articles/articles.php','articles_permission_limits_get');
	Hook::unregister('get_role_perms','addon/articles/articles.php','articles_get_role_perms');

}


function articles_permissions_list(&$x) {

	$x['permissions'] = array_merge($x['permissions'],[
		'view_articles'     => t('Can view my articles'),
		'write_articles'    => t('Can write to my articles')
	]);

}


function articles_permission_limits_get(&$x) {

	// In the absence of existing permissions, copy the view_stream for read access 
	// and the post_wall permission for write access

	if($x['permission'] === 'view_articles') {
		$x['value'] = \Zotlabs\Access\PermissionLimits::Get($x['channel_id'],'view_stream');
		\Zotlabs\Access\PermissionLimits::Set($x['channel_id'],$x['permission'],$x['value']);
	}

	if($x['permission'] === 'write_articles') {
		$x['value'] = \Zotlabs\Access\PermissionLimits::Get($x['channel_id'],'write_storage');
		\Zotlabs\Access\PermissionLimits::Set($x['channel_id'],$x['permission'],$x['value']);
	}

}

function articles_get_role_perms(&$x) {

	switch($x['role']) {
		case 'custom':
		case '':
			break;

		case 'forum_private':
			$x['result']['limits']['view_articles'] = PERMS_SPECIFIC;
			$x['result']['perms_connect'][] = 'view_articles';
			break;

		case 'repository':
			$x['result']['perms_connect'][] = 'write_articles';

		default:
			$x['result']['perms_connect'][] = 'view_articles';
			break;
	}
}
