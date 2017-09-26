<?php

/**
 * Name: Zot VI
 *
 */


function zotvi_load() {

	Zotlabs\Extend\Hook::register_array('addon/zotvi/zotvi.php', [
		'module_loaded'              => 'zotvi_load_module',
		'webfinger'                  => 'zotvi_webfinger',
		'channel_mod_init'           => 'zotvi_channel_mod_init',
		'home_mod_init'              => 'zotvi_home_mod_init',
		'zot_revision'               => 'zotvi_zot_revision',
	]);

}


function zotvi_unload() {
	Zotlabs\Extend\Hook::unregister_by_file('addon/zotvi/zotvi.php');
}


function zotvi_load_module(&$b) {
	if($b['module'] === 'zot_probe') {
		require_once('addon/zotvi/Mod_Zot_probe.php');
		$b['controller'] = new \Zotlabs\Module\Zot_probe();
		$b['installed'] = true;
	}
	if($b['module'] === 'zot') {
		require_once('addon/zotvi/Mod_Zot.php');
		$b['controller'] = new \Zotlabs\Module\Zot();
		$b['installed'] = true;
	}
}


function zotvi_zot_revision(&$b) {
//  Do not enable yet.
//	$b['revision'] = '6.0';
}

function zotvi_webfinger(&$b) {
	if(! $b['channel'])
		return;

	$b['result']['links'][] = [ 
		'rel'  => 'http://purl.org/zot/protocol/6.0', 
		'type' => 'application/x-zot+json', 
		'href' => z_root() . '/channel/' . $b['channel']['channel_address']
	];
}

function zotvi_is_zot_request() {

	if($_REQUEST['module_format'] === 'json')
		return true;

	$x = getBestSupportedMimeType([
		'application/x-zot+json'
	]);

	return(($x) ? true : false);

}


function zotvi_channel_mod_init($x) {
	
	if(zotvi_is_zot_request()) {
		$channel = channelx_by_nick(argv(1));
		if(! $channel)
			http_status_exit(404, 'Not found');

		
		$x = zotinfo([ 'address' => $channel['channel_address'] ]);

		$headers = [];
		$headers['Content-Type'] = 'application/x-zot+json' ;

		$ret = json_encode($x);
		$hash = \Zotlabs\Web\HTTPSig::generate_digest($ret,false);
		$headers['Digest'] = 'SHA-256=' . $hash;  
		\Zotlabs\Web\HTTPSig::create_sig('',$headers,$channel['channel_prvkey'],z_root() . '/channel/' . $channel['channel_address'],true);
		echo $ret;
		killme();
	}
}


function zotvi_home_mod_init($x) {
	
	if(zotvi_is_zot_request()) {

		$channel = [ 'channel_prvkey' => get_config('system','prvkey') ];
		
		$x = zot_site_info();

		$headers = [];
		$headers['Content-Type'] = 'application/x-zot+json' ;

		$ret = json_encode($x);
		$hash = \Zotlabs\Web\HTTPSig::generate_digest($ret,false);
		$headers['Digest'] = 'SHA-256=' . $hash;  
		\Zotlabs\Web\HTTPSig::create_sig('',$headers,$channel['channel_prvkey'],z_root(),true);
		echo $ret;
		killme();
	}
}


function zot_getzotkey($url) {

	if(! check_siteallowed($url)) {
		logger('blacklisted: ' . $url);
		return null;
	}

	if($url === z_root()) {
		$j = zot_site_info();
	}
	else {
		$redirects = 0;
		$x = z_fetch_url($url,true,$redirects,
			[ 'headers' => [ 'Accept: application/x-zot+json, application/json;' ] ]
		);

		if($x['success']) {
			$j = json_decode($x['body'],true);
		}
	}

	if($j) {
		return(($j['key']) ? $j['key'] : false);
	}

	return false;

}