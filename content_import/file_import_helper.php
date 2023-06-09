<?php

use Code\Web\HTTPSig;
use Code\Lib\Channel;
use Code\Lib\Url;
    
require_once('include/cli_startup.php');
require_once('include/attach.php');
require_once('include/import.php');

	cli_startup();

	$attach_id = $argv[1];
	$channel_address = $argv[2];
	$hz_server = urldecode($argv[3]);

	$m = parse_url($hz_server);

	$channel = Channel::from_username($channel_address);
	if(! $channel) {
		logger('filehelper: channel not found');
		killme();
	}

	$headers = [
		'X-API-Token'      => random_string(),
		'X-API-Request'    => $hz_server . '/api/z/1.0/file/export?f=&zap_compat=1&file_id=' . $attach_id,
		'Host'             => $m['host'],
		'(request-target)' => 'get /api/z/1.0/file/export?f=&zap_compat=1&file_id=' . $attach_id,
	];

	$headers = HTTPSig::create_sig($headers,$channel['channel_prvkey'],Channel::url($channel),true,'sha512');		
	$x = Url::get($hz_server . '/api/z/1.0/file/export?f=&zap_compat=1&file_id=' . $attach_id,[ 'headers' => $headers ]);

	if(! $x['success']) {
		logger('no API response',LOGGER_DEBUG);
		return;
	}

	$j = json_decode($x['body'],true);

	sync_files($channel,[$j]);

	killme();

