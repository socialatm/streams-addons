<?php

require_once('include/cli_startup.php');
require_once('include/attach.php');

cli_startup();

$a = get_app();


$photo_id = $argv[1];
$channel_address = $argv[2];
$fr_server = urldecode($argv[3]);

$cookies = 'store/[data]/redphoto_cookie_' . $channel_address;
$photo_tmp = 'store/[data]/redphoto_data' . $channel_address;

	$c = q("select * from channel left join xchan on channel_hash = xchan_hash where channel_address = '%s' limit 1",
		dbesc($channel_address)
	);
	if(! $c) {
		logger('redphotohelper: channel not found');
		killme();
	}
	$channel = $c[0];	


	    $ch = curl_init($fr_server . '/api/red/photo?f=&photo_id=' . $photo_id);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($ch, CURLOPT_COOKIEFILE, $cookies);
        curl_setopt ($ch, CURLOPT_COOKIEJAR, $cookies);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'RedMatrix');

        $output = curl_exec($ch);
        curl_close($ch);

		$j = json_decode($output,true);

		if(! $j['data']) {
			logger('redphotohelper: no data');
			killme();
		}

		file_put_contents($photo_tmp,base64_decode($j['data']));

		$args = array();


		$args['src'] = $photo_tmp; 
		
		$args['filename'] = $j['filename'];
		if(! $args['filename'])
			$args['filename'] = t('photo');
		$args['hash'] = $j['hash'];
		$args['scale'] = $j['scale'];
		$args['album'] = $j['album'];
		$args['visible'] = 0;
		$args['created'] = $j['created'];
		$args['edited'] = $j['edited'];
		$args['title'] = $j['title'];
		$args['description'] = $j['description'];

		$args['allow_cid'] = $j['allow_cid'];
		$args['allow_gid'] = $j['allow_gid'];
		$args['deny_cid']  = $j['deny_cid'];
		$args['deny_gid']  = $j['deny_gid'];


		if($j['photo_flags'] & 1)
			$args['photo_usage'] = PHOTO_PROFILE;
		if($j['profile'])
			$args['photo_usage'] = PHOTO_PROFILE;

		if(array_key_exists('photo_usage',$args))
			$args['photo_usage'] = $j['photo_usage'];

		$args['type'] = $j['type']; 

		unset($j['data']);

//		logger('redphotohelper: ' . print_r($j,true));

		$r = q("select id from photo where resource_id = '%s' and uid = %d limit 1",
			dbesc($args['resource_id']),
			intval($channel['channel_id'])
		);
		if($r) {
			killme();
		}


		$ret = attach_store($channel,$channel['channel_hash'],'import',$args);
//		logger('photo_import: ' . print_r($ret,true));

		killme();

