<?php

use Code\Web\HTTPSig;
use Code\Lib\Channel;
use Code\Lib\Url;

require_once('include/cli_startup.php');
require_once('include/attach.php');
require_once('include/import.php');

	cli_startup();

	$page = $argv[1];
	$since = $argv[2];
	$until = $argv[3];
	$channel_address = $argv[4];
	$hz_server = urldecode($argv[5]);

	$m = parse_url($hz_server);

	$channel = Channel::from_username($channel_address);
	if(! $channel) {
		logger('itemhelper: channel not found');
		killme();
	}

	$headers = [ 
		'X-API-Token'      => random_string(),
		'X-API-Request'    => $hz_server . '/api/z/1.0/item/export_page?f=&zap_compat=1&since=' . urlencode($since) . '&until=' . urlencode($until) . '&page=' . $page ,
		'Host'             => $m['host'],
		'(request-target)' => 'get /api/z/1.0/item/export_page?f=&zap_compat=1&since=' . urlencode($since) . '&until=' . urlencode($until) . '&page=' . $page ,
	];

	$headers = HTTPSig::create_sig($headers,$channel['channel_prvkey'], Channel::url($channel),true,'sha512');

	$x = Url::get($hz_server . '/api/z/1.0/item/export_page?f=&zap_compat=1&since=' . urlencode($since) . '&until=' . urlencode($until) . '&page=' . $page, [ 'headers' => $headers ]);

	if(! $x['success']) {
		logger('no API response',LOGGER_DEBUG);
		killme();
	}

	$j = json_decode($x['body'],true);

    if(! (isset($j['item']) && is_array($j['item']) && count($j['item']))) {
		killme();
    }

	import_items($channel,$j['item'],false,((array_key_exists('relocate',$j)) ? $j['relocate'] : null));

	killme();

