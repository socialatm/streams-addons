<?php


/**
 * Name: GNU-Social Protocol
 * Description: GNU-Social Protocol (Experimental, Not-finished, Unsupported)
 * Version: 1.0
 * Author: Mike Macgirvin
 * Maintainer: none
 * Requires: pubsubhubbub
 */


require_once('include/crypto.php');
require_once('include/items.php');
require_once('include/bb2diaspora.php');
require_once('include/contact_selectors.php');
require_once('include/queue_fn.php');


function gnusoc_load() {
	register_hook('module_loaded', 'addon/gnusoc/gnusoc.php', 'gnusoc_load_module');
	register_hook('webfinger', 'addon/gnusoc/gnusoc.php', 'gnusoc_webfinger');
	register_hook('personal_xrd', 'addon/gnusoc/gnusoc.php', 'gnusoc_personal_xrd');
	register_hook('follow_allow', 'addon/gnusoc/gnusoc.php', 'gnusoc_follow_allow');
	register_hook('feature_settings_post', 'addon/gnusoc/gnusoc.php', 'gnusoc_feature_settings_post');
	register_hook('feature_settings', 'addon/gnusoc/gnusoc.php', 'gnusoc_feature_settings');
	register_hook('follow', 'addon/gnusoc/gnusoc.php', 'gnusoc_follow_local');
	register_hook('permissions_create', 'addon/gnusoc/gnusoc.php', 'gnusoc_permissions_create');
	register_hook('queue_deliver', 'addon/gnusoc/gnusoc.php', 'gnusoc_queue_deliver');

//	register_hook('notifier_hub', 'addon/gnusoc/gnusoc.php', 'gnusoc_process_outbound');
//	register_hook('permissions_update', 'addon/gnusoc/gnusoc.php', 'gnusoc_permissions_update');

}

function gnusoc_unload() {
	unregister_hook('module_loaded', 'addon/gnusoc/gnusoc.php', 'gnusoc_load_module');
	unregister_hook('webfinger', 'addon/gnusoc/gnusoc.php', 'gnusoc_webfinger');
	unregister_hook('personal_xrd', 'addon/gnusoc/gnusoc.php', 'gnusoc_personal_xrd');
	unregister_hook('follow_allow', 'addon/gnusoc/gnusoc.php', 'gnusoc_follow_allow');
	unregister_hook('feature_settings_post', 'addon/gnusoc/gnusoc.php', 'gnusoc_feature_settings_post');
	unregister_hook('feature_settings', 'addon/gnusoc/gnusoc.php', 'gnusoc_feature_settings');
	unregister_hook('follow', 'addon/gnusoc/gnusoc.php', 'gnusoc_follow_local');
	unregister_hook('permissions_create', 'addon/gnusoc/gnusoc.php', 'gnusoc_permissions_create');
	unregister_hook('queue_deliver', 'addon/gnusoc/gnusoc.php', 'gnusoc_queue_deliver');

}

// @fixme - subscribe to hub(s) on follow


function gnusoc_load_module(&$a, &$b) {
	if($b['module'] === 'salmon') {
		require_once('addon/gnusoc/salmon.php');
		$b['installed'] = true;
	}
}



function gnusoc_webfinger(&$a,&$b) {
	$b['result']['links'][] = array('rel' => 'salmon', 'href' => z_root() . '/salmon/' . $b['channel']['channel_address']);
}

function gnusoc_personal_xrd(&$a,&$b) {
	$b['xml'] = str_replace('</XRD>',
		'<Link rel="salmon" href="' . z_root() . '/salmon/' . $b['user']['channel_address'] . '" />' . "\r\n" . '</XRD>', $b['xml']);

}


function gnusoc_follow_allow(&$a, &$b) {

	if($b['xchan']['xchan_network'] !== 'gnusoc')
		return;

	$allowed = get_pconfig($b['channel_id'],'system','gnusoc_allowed');
	if($allowed === false)
		$allowed = 1;
	$b['allowed'] = $allowed;
	$b['singleton'] = 1;  // this network does not support channel clones
}


function gnusoc_follow_local(&$a,&$b) {

	require_once('addon/pubsubhubbub/pubsubhubbub.php');

	if($b['abook']['abook_xchan'] && $b['abook']['xchan_network'] === 'gnusoc') {
		$hubs = get_xconfig($b['abook']['abook_xchan'],'system','push_hubs');
		if($hubs) {
			foreach($hubs as $hub) {
				pubsubhubbub_subscribe($hub,$b['channel'],$b['abook'],$hubmode = 'subscribe');
			}
		}
	}
}


function gnusoc_feature_settings_post(&$a,&$b) {

	if($_POST['gnusoc-submit']) {
		set_pconfig(local_channel(),'system','gnusoc_allowed',intval($_POST['gnusoc_allowed']));
		info( t('GNU-Social Protocol Settings updated.') . EOL);
	}
}


function gnusoc_feature_settings(&$a,&$s) {
	$gnusoc_allowed = get_pconfig(local_channel(),'system','gnusoc_allowed');
	if($gnusoc_allowed === false)
		$gnus_allowed = get_config('gnusoc','allowed');	

	$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
		'$field'	=> array('gnusoc_allowed', t('Enable the (experimental) GNU-Social protocol for this channel'), $gnusoc_allowed, '', $yes_no),
	));

	$s .= replace_macros(get_markup_template('generic_addon_settings.tpl'), array(
		'$addon' 	=> array('gnusoc', '<img src="addon/gnusoc/gnusoc-32.png" style="width:auto; height:1em; margin:-3px 5px 0px 0px;">' . t('GNU-Social Protocol Settings'), '', t('Submit')),
		'$content'	=> $sc
	));

	return;

}

function get_salmon_key($uri,$keyhash) {
	$ret = array();

	logger('Fetching salmon key for ' . $uri, LOGGER_DEBUG, LOG_INFO);

	$x = webfinger_rfc7033($uri,true);

	logger('webfinger returns: ' . print_r($x,true), LOGGER_DATA, LOG_DEBUG);

	if($x && array_key_exists('links',$x) && $x['links']) {
		foreach($x['links'] as $link) {
			if(array_key_exists('rel',$link) && $link['rel'] === 'magic-public-key') {
				$ret[] = $link['href'];
			}
		}
	}

	else {
		$arr = old_webfinger($uri);

		logger('old webfinger returns: ' . print_r($arr,true), LOGGER_DATA, LOG_DEBUG);

		if(is_array($arr)) {
			foreach($arr as $a) {
				if($a['@attributes']['rel'] === 'magic-public-key') {
					$ret[] = $a['@attributes']['href'];
				}
			}
		}
		else {
			return '';
		}
	}

	// We have found at least one key URL
	// If it's inline, parse it - otherwise get the key

	if(count($ret)) {
		for($x = 0; $x < count($ret); $x ++) {
			if(substr($ret[$x],0,5) === 'data:') {
				$ret[$x] = convert_salmon_key($ret[$x]);
			}
		}
	}


	logger('Key located: ' . print_r($ret,true), LOGGER_DEBUG, LOG_INFO);

	if(count($ret) == 1) {

		// We only found one one key so we don't care if the hash matches.
		// If it's the wrong key we'll find out soon enough because
		// message verification will fail. This also covers some older
		// software which don't supply a keyhash. As long as they only
		// have one key we'll be right.

		return $ret[0];
	}
	else {
		foreach($ret as $a) {
			$hash = base64url_encode(hash('sha256',$a));
			if($hash == $keyhash)
				return $a;
		}
	}

	return '';
}



function slapper($owner,$url,$slap) {

	// does contact have a salmon endpoint?

	if(! strlen($url))
		return;

	if(! $owner['channel_prvkey']) {
		logger(sprintf("channel '%s' (%d) does not have a salmon private key. Send failed.",
		$owner['channel_address'],$owner['channel_id']));
		return;
	}

	logger('slapper called for ' .$url . '. Data: ' . $slap, LOGGER_DATA, LOG_DEBUG);

	// create a magic envelope


	$data      = base64url_encode($slap, false); // do not strip padding 
	$data_type = 'application/atom+xml';
	$encoding  = 'base64url';
	$algorithm = 'RSA-SHA256';
	$keyhash   = base64url_encode(hash('sha256',salmon_key($owner['channel_pubkey'])),true);

	// precomputed base64url encoding of data_type, encoding, algorithm concatenated with periods

	$precomputed = '.YXBwbGljYXRpb24vYXRvbSt4bWw=.YmFzZTY0dXJs.UlNBLVNIQTI1Ng==';

	$signature  = base64url_encode(rsa_sign($data . $precomputed,$owner['channel_prvkey']));

	$salmon_tpl = get_markup_template('magicsig.tpl','addon/gnusoc/');

	$salmon = replace_macros($salmon_tpl,array(
		'$data'      => $data,
		'$encoding'  => $encoding,
		'$algorithm' => $algorithm,
		'$keyhash'   => $keyhash,
		'$signature' => $signature
	));


	$hash = random_string();

	queue_insert(array(
   		'hash'       => $hash,
        'account_id' => $owner['channel_account_id'],
   		'channel_id' => $owner['channel_id'],
        'driver'     => 'slap',
   		'posturl'    => $url,
   		'notify'     => '',
   		'msg'        => $salmon,
	));

	return $hash;

}


function gnusoc_queue_deliver(&$a,&$b) {
   $outq = $b['outq'];
    if($outq['outq_driver'] !== 'slap')
        return;

    $b['handled'] = true;

	$headers = array(
		'Content-type: application/magic-envelope+xml',
		'Content-length: ' . strlen($outq['outq_msg']));

    $counter = 0;
	$result = z_post_url($outq['outq_posturl'], $outq['outq_msg'], $counter, array('headers' => $headers, 'novalidate' => true));
    if($result['success'] && $result['return_code'] < 300) {
        logger('slap_deliver: queue post success to ' . $outq['outq_posturl'], LOGGER_DEBUG);
        if($b['base']) {
            q("update site set site_update = '%s', site_dead = 0 where site_url = '%s' ",
                dbesc(datetime_convert()),
                dbesc($b['base'])
            );
        }
        q("update dreport set dreport_result = '%s', dreport_time = '%s' where dreport_queue = '%s' limit 1",
            dbesc('accepted for delivery'),
            dbesc(datetime_convert()),
            dbesc($outq['outq_hash'])
        );

        remove_queue_item($outq['outq_hash']);
    }
    else {
        logger('slap_deliver: queue post returned ' . $result['return_code']
            . ' from ' . $outq['outq_posturl'],LOGGER_DEBUG);
            update_queue_item($outq['outq_posturl']);
    }
    return;

}



function gnusoc_remote_follow($channel,$xchan) {


	$slap = replace_macros(get_markup_template('follow_slap.tpl','addon/gnusoc/'),array(
		'$name' => xmlify($channel['channel_name']),
		'$profile_page' => xmlify($channel['channel_url']),
		'$thumb' => xmlify($channel['xchan_photo_l']),
		'$item_id' => xmlify(random_string()),
		'$title' => xmlify(t('Follow')),
		'$published' => datetime_convert('UTC','UTC','now',ATOM_TIME),
		'$type' => 'html',
		'$content' => xmlify(sprintf( t('%1$s is now following %2$s'),$channel['channel_name'],$xchan['xchan_name'])),
		'$remote_profile' => xmlify($xchan['xchan_url']),
		'$remote_photo' => xmlify($xchan['xchan_photo_l']),
		'$remote_thumb' => xmlify($xchan['xchan_photo_m']),
		'$remote_nick' => xmlify(substr($xchan['xchan_addr'],0,strpos($xchan['xchan_addr'],'@'))),
		'$remote_name' => xmlify($xchan['xchan_name']),
		'$verb' => xmlify(ACTIVITY_FOLLOW),
		'$ostat_follow' => ''
	));


	logger('follow xml: ' . $slap, LOGGER_DATA);

	$deliver = '';

	$y = q("select * from hubloc where hubloc_hash = '%s'",
		dbesc($xchan['xchan_hash'])
	);


	if($y) {
		$deliver = slapper($channel,$y[0]['hubloc_callback'],$slap);
	}

	return $deliver;
}

function gnusoc_permissions_create(&$a,&$b) {
    if($b['recipient']['xchan_network'] === 'gnusoc') {
        $b['deliveries'] = gnusoc_remote_follow($b['sender'],$b['recipient']);
        if($b['deliveries'])
            $b['success'] = 1;
    }
}