<?php

require_once('addon/openid/Mod_Openid.php');


	/**
	 *
	 * Name: Openid
	 * Description: Openid (traditional) client and server
	 * Version: 1.0
	 * Author: Mike Macgirvin
	 *
     */


function openid_load() {
	Zotlabs\Extend\Hook::register('module_loaded','addon/openid/openid.php','openid_module_loaded');
}

function openid_unload() {
	Zotlabs\Extend\Hook::unregister_by_file('addon/openid/openid.php');
}


function openid_module_loaded(&$x) {
	if($x['module'] === 'id') {
		require_once('addon/openid/Mod_Id.php');
		$x['controller'] = new \Zotlabs\Module\Id();
		$x['installed'] = true;
	}
}