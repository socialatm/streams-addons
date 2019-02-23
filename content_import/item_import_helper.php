<?php

use Zotlabs\Web\HTTPSig;

require_once('include/cli_startup.php');
require_once('include/attach.php');
require_once('include/import.php');

cli_startup();

$page = $argv[1];
$start = $argv[2];
$finish = $argv[3];
$channel_address = $argv[4];
$hz_server = urldecode($argv[5]);


	$channel = channelx_by_nick($channel_address);
	if(! $channel) {
		logger('reditemhelper: channel not found');
		killme();
	}

	$headers = [ 
		'X-API-Token'      => random_string(),
		'X-API-Request'    => $hz_server . '/api/z/1.0/item_export_page?f=&since=' . urlencode($since) . '&until=' . urlencode($until) . '&page=' . $page ,
		'Host'             => \App::get_hostname(),
		'(request-target)' => '/api/z/1.0/item_export_page?f=&since=' . urlencode($since) . '&until=' . urlencode($until) . '&page=' . $page ,
	];

	$headers = HTTPSig::create_sig($headers,$channel['channel_prvkey'], channel_url($channel),true,'sha512');

	$x = z_fetch_url($hz_server . '/api/z/1.0/item_export_page?f=&since=' . urlencode($since) . '&until=' . urlencode($until . '&page=' . $page),false,$redirects,[ 'headers' => $headers ]);

	if(! $x['success']) {
		logger('no API response');
		killme();
	}

	$j = json_decode($x['body'],true);

	if(! ($j['item'] || count($j['item'])))
		killme();

	import_items($channel,$j['item'],false,((array_key_exists('relocate',$j)) ? $j['relocate'] : null));

	killme();

