<?php

use Zotlabs\Web\HTTPSig;
use Zotlabs\Lib\Queue;
use Zotlabs\Lib\LDSignatures;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\Activity;

/**
 * Name: ActivityPub
 * Description: ActivityPub Protocol gateway
 * 
 */

require_once('addon/activitypub/as.php');

function activitypub_load() {
	Zotlabs\Extend\Hook::register_array('addon/activitypub/activitypub.php', [
		'module_loaded'              => 'activitypub_load_module',
		'webfinger'                  => 'activitypub_webfinger',
		'profile_mod_init'           => 'activitypub_profile_mod_init',
		'follow_mod_init'            => 'activitypub_follow_mod_init',
		'channel_mod_init'           => 'activitypub_channel_mod_init',
		'item_mod_init'              => 'activitypub_item_mod_init',
		'thing_mod_init'             => 'activitypub_thing_mod_init',
		'locs_mod_init'              => 'activitypub_locs_mod_init',
		'follow_allow'               => 'activitypub_follow_allow',
		'discover_channel_webfinger' => 'activitypub_discover_channel_webfinger',
		'permissions_create'         => 'activitypub_permissions_create',
		'permissions_accept'         => 'activitypub_permissions_accept',
		'connection_remove'          => 'activitypub_connection_remove',
		'notifier_hub'               => 'activitypub_notifier_process',
		'feature_settings_post'      => 'activitypub_feature_settings_post',
		'feature_settings'           => 'activitypub_feature_settings',
		'channel_links'              => 'activitypub_channel_links',
		'queue_deliver'              => 'activitypub_queue_deliver',
		'import_author'              => 'activitypub_import_author',
		'channel_protocols'          => 'activitypub_channel_protocols',
		'federated_transports'       => 'activitypub_federated_transports',
		'create_identity'            => 'activitypub_create_identity'
	]);


	Zotlabs\Extend\Route::register('addon/activitypub/Mod_Inbox.php','inbox');
	Zotlabs\Extend\Route::register('addon/activitypub/Mod_Outbox.php','outbox');
	Zotlabs\Extend\Route::register('addon/activitypub/Mod_Activity.php','activity');
	Zotlabs\Extend\Route::register('addon/activitypub/Mod_Nullbox.php','nullbox');
	Zotlabs\Extend\Route::register('addon/activitypub/Mod_Apschema.php','apschema');
	Zotlabs\Extend\Route::register('addon/activitypub/Mod_Followers.php','followers');
	Zotlabs\Extend\Route::register('addon/activitypub/Mod_Following.php','following');

}

function activitypub_unload() {
	Zotlabs\Extend\Hook::unregister_by_file('addon/activitypub/activitypub.php');
	Zotlabs\Extend\Route::unregister('addon/activitypub/Mod_Inbox.php','inbox');
	Zotlabs\Extend\Route::unregister('addon/activitypub/Mod_Outbox.php','outbox');
	Zotlabs\Extend\Route::unregister('addon/activitypub/Mod_Activity.php','activity');
	Zotlabs\Extend\Route::unregister('addon/activitypub/Mod_Nullbox.php','nullbox');
	Zotlabs\Extend\Route::unregister('addon/activitypub/Mod_Apschema.php','apschema');
	Zotlabs\Extend\Route::unregister('addon/activitypub/Mod_Followers.php','followers');
	Zotlabs\Extend\Route::unregister('addon/activitypub/Mod_Following.php','following');


}


function activitypub_channel_protocols(&$b) {

		$b['protocols'][] = 'activitypub';

}

function activitypub_federated_transports(&$x) {
	$x[] = 'ActivityPub';
}


function activitypub_follow_allow(&$b) {

	if($b['xchan']['xchan_network'] !== 'activitypub')
		return;

	$b['allowed']   = 1;
	$b['singleton'] = 1;  // this network does not support channel clones

}

function activitypub_channel_links(&$b) {
	$c = channelx_by_nick($b['channel_address']);
	if($c) {
		$b['channel_links'][] = [
			'rel' => 'alternate',
			'type' => 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"',
			'url' => z_root() . '/channel/' . $c['channel_address']
		];
		$b['channel_links'][] = [
			'rel' => 'alternate',
			'type' => 'application/activity+json',
			'url' => z_root() . '/channel/' . $c['channel_address']
		];
	}
}

function activitypub_webfinger(&$b) {
	if(! $b['channel'])
		return;

	$b['result']['properties']['http://purl.org/zot/federation'] .= ',activitypub';

	$b['result']['links'][] = [ 
		'rel'  => 'self', 
		'type' => 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"',
		'href' => z_root() . '/channel/' . $b['channel']['channel_address']
	];
	$b['result']['links'][] = [ 
		'rel'  => 'self', 
		'type' => 'application/activity+json',
		'href' => z_root() . '/channel/' . $b['channel']['channel_address']
	];
}

function activitypub_discover_channel_webfinger(&$b) {

	$url      = $b['address'];
	$x        = $b['webfinger'];
	$protocol = $b['protocol'];

	logger('probing: activitypub');

	if($protocol && strtolower($protocol) !== 'activitypub')
		return;

	if(! is_array($x))
		return;

	$address = EMPTY_STR;

	if(array_key_exists('subject',$x) && strpos($x['subject'],'acct:') === 0)
		$address = str_replace('acct:','',$x['subject']);
	if(array_key_exists('aliases',$x) && count($x['aliases'])) {
		foreach($x['aliases'] as $a) {
			if(strpos($a,'acct:') === 0) {
				$address = str_replace('acct:','',$a);
				break;
			}
		}
	}	

    if(strpos($url,'@') && $x && array_key_exists('links',$x) && $x['links']) {
        foreach($x['links'] as $link) {
            if(array_key_exists('rel',$link) && array_key_exists('type',$link)) {
                if($link['rel'] === 'self' && ($link['type'] === 'application/activity+json' || strpos($link['type'],'ld+json') !== false)) {
					$url = $link['href'];
                }
            }
        }
    }
	
	if(($url) && (strpos($url,'http') === 0)) {
		$x = ActivityStreams::fetch($url);
		if(! $x) {
			return;
		}
	}
	else {
		return;
	}

	$AS = new ActivityStreams($x);

	if(! $AS->is_valid()) {
		return;
	}

	// Now find the actor and see if there is something we can follow	

	$person_obj = null;
	if($AS->type === 'Person') {
		$person_obj = $AS->data;
	}
	elseif($AS->obj && $AS->obj['type'] === 'Person') {
		$person_obj = $AS->obj;
	}
	else {
		return;
	}

	Activity::actor_store($url,$person_obj);

	if($address) {
		q("update xchan set xchan_addr = '%s' where xchan_hash = '%s' and xchan_network = 'activitypub'",
			dbesc($address),
			dbesc($url)
		);
		q("update hubloc set hubloc_addr = '%s' where hubloc_hash = '%s' and hubloc_network = 'activitypub'",
			dbesc($address),
			dbesc($url)
		);
	}

	$b['xchan']   = $url;
	$b['success'] = true;

}

function activitypub_import_author(&$b) {

	if(! $b['author']['url'])
		return;

	$url = $b['author']['url'];

	// let somebody upgrade from an 'unknown' connection which has no xchan_addr
	$r = q("select xchan_hash, xchan_url, xchan_name, xchan_photo_s from xchan where xchan_url = '%s' limit 1",
		dbesc($url)
	);
	if(! $r) {
		$r = q("select xchan_hash, xchan_url, xchan_name, xchan_photo_s from xchan where xchan_hash = '%s' limit 1",
			dbesc($url)
		);
	}
	if($r) {
		logger('in_cache: ' . $r[0]['xchan_name'], LOGGER_DATA);
		$b['result'] = $r[0]['xchan_hash'];
		return;
	}

	$x = discover_by_webbie($url);

	if($x) {
		$r = q("select xchan_hash, xchan_url, xchan_name, xchan_photo_s from xchan where xchan_url = '%s' limit 1",
			dbesc($url)
		);
		if(! $r) {
			$r = q("select xchan_hash, xchan_url, xchan_name, xchan_photo_s from xchan where xchan_hash = '%s' limit 1",
				dbesc($url)
			);
		}
		if($r) {
			$b['result'] = $r[0]['xchan_hash'];
			return;
		}
	}

	return;

}




function activitypub_is_as_request() {

	if(strpos($_SERVER['HTTP_ACCEPT'],'activity') !== false) {
		logger('Accept: ' . $_SERVER['HTTP_ACCEPT'], LOGGER_DATA);
	}

	if($_REQUEST['module_format'] === 'json')
		return true;

	$x = getBestSupportedMimeType([
		'application/ld+json;profile="https://www.w3.org/ns/activitystreams"',
		'application/activity+json',
		'application/ld+json;profile="http://www.w3.org/ns/activitystreams"'
	]);

	return(($x) ? true : false);

}


function activitypub_magic_env_allowed() {

	$x = getBestSupportedMimeType([
		'application/magic-envelope+json'
	]);

	return(($x) ? true : false);
}

function activitypub_salmon_sign($data,$channel) {

  	$data      = base64url_encode($data, false); // do not strip padding
    $data_type = 'application/activity+json';
    $encoding  = 'base64url';
    $algorithm = 'RSA-SHA256';
    $keyhash   = base64url_encode(z_root() . '/channel/' . $channel['channel_address']);

    $data = str_replace(array(" ","\t","\r","\n"),array("","","",""),$data);

    // precomputed base64url encoding of data_type, encoding, algorithm concatenated with periods

    $precomputed = '.' . base64url_encode($data_type,false) . '.YmFzZTY0dXJs.UlNBLVNIQTI1Ng==';

    $signature  = base64url_encode(rsa_sign($data . $precomputed,$channel['channel_prvkey']));

    return ([
        'data'       => $data,
		'data_type'  => $data_type,
        'encoding'   => $encoding,
        'alg'        => $algorithm,
		'sigs'       => [
			'value'  => $signature,
			'key_id' => $keyhash
		]
	]);

}

function activitypub_channel_mod_init($x) {
	
	if(ActivityStreams::is_as_request()) {
		$chan = channelx_by_nick(argv(1));
		if(! $chan)
			http_status_exit(404, 'Not found');

		$y = Activity::encode_person($chan,true,true);
		if(! $y)
			http_status_exit(404, 'Not found');

		$x = array_merge(['@context' => [
			ACTIVITYSTREAMS_JSONLD_REV,
			'https://w3id.org/security/v1',
			z_root() . ZOT_APSCHEMA_REV
			]], $y);

		$headers = [];
		$headers['Content-Type'] = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"' ;

		$x['signature'] = LDSignatures::dopplesign($x,$chan);
		$ret = json_encode($x, JSON_UNESCAPED_SLASHES);
		$headers['Digest'] = HTTPSig::generate_digest_header($ret);
		$h = HTTPSig::create_sig($headers,$chan['channel_prvkey'],channel_url($chan));
		HTTPSig::set_headers($h);

		echo $ret;
		logger('channel: ' . $ret, LOGGER_DATA);
		killme();
	}
}

function activitypub_notifier_process(&$arr) {

	if($arr['hub']['hubloc_network'] !== 'activitypub')
		return;

	logger('upstream: ' . intval($arr['upstream']));

	logger('notifier_array: ' . print_r($arr,true), LOGGER_ALL, LOG_INFO);


	$signed_msg = null;

	if(array_key_exists('target_item',$arr) && is_array($arr['target_item'])) {

		if(intval($arr['target_item']['item_obscured'])) {
			logger('Cannot send raw data as an activitypub activity.');
			return;
		}

		if(strpos($arr['target_item']['postopts'],'nopub') !== false) {
			return;
		}

		$signed_msg = get_iconfig($arr['target_item'],'activitypub','rawmsg');

		// If we have an activity already stored with an LD-signature
		// which we are sending downstream, use that signed activity as is.
		// The channel will then sign the HTTP transaction. 

		// It is unclear if Mastodon supports the federation delivery model. Initial tests were
		// inconclusive and the behaviour varied. 

		if(($arr['channel']['channel_hash'] != $arr['target_item']['author_xchan']) && (! $signed_msg)) {
			return;
		}
		
	}

	$target_item = $arr['target_item'];

	if(! $target_item['mid'])
		return;

	$prv_recips = $arr['env_recips'];


	if($signed_msg) {
		$jmsg = $signed_msg;
	}
	else {
		$ti = asencode_activity($target_item);
		if(! $ti)
			return;

		$msg = array_merge(['@context' => [
			ACTIVITYSTREAMS_JSONLD_REV,
			'https://w3id.org/security/v1',
			z_root() . ZOT_APSCHEMA_REV
		]], $ti);
	
		$msg['signature'] = \Zotlabs\Lib\LDSignatures::dopplesign($msg,$arr['channel']);

		$jmsg = json_encode($msg, JSON_UNESCAPED_SLASHES);
	}

	if($prv_recips) {
		$hashes = array();

		// re-explode the recipients, but only for this hub/pod

		foreach($prv_recips as $recip)
			$hashes[] = "'" . $recip['hash'] . "'";

		$r = q("select * from xchan left join hubloc on xchan_hash = hubloc_hash where hubloc_url = '%s'
			and xchan_hash in (" . implode(',', $hashes) . ") and xchan_network = 'activitypub' ",
			dbesc($arr['hub']['hubloc_url'])
		);

		if(! $r) {
			logger('activitypub_process_outbound: no recipients');
			return;
		}

		foreach($r as $contact) {

			// is $contact connected with this channel - and if the channel is cloned, also on this hub?
			$single = deliverable_singleton($arr['channel']['channel_id'],$contact);

			if(! $arr['normal_mode'])
				continue;

			if($single) {
				$qi = activitypub_queue_message($jmsg,$arr['channel'],$contact,$target_item['mid']);
				if($qi)
					$arr['queued'][] = $qi;
			}
			continue;
		}

	}
	else {

		// public message

		// See if we can deliver all of them at once

		$x = get_xconfig($arr['hub']['hubloc_hash'],'activitypub','collections');
		if($x && $x['sharedInbox']) {
			logger('using publicInbox delivery for ' . $arr['hub']['hubloc_url'], LOGGER_DEBUG);
			$contact['hubloc_callback'] = $x['sharedInbox'];
			$qi = activitypub_queue_message($jmsg,$arr['channel'],$contact,$target_item['mid']);
			if($qi) {
				$arr['queued'][] = $qi;
			}
		}
		else {

			$r = q("select * from xchan left join hubloc on xchan_hash = hubloc_hash where hubloc_url = '%s' and xchan_network = 'activitypub' ",
				dbesc($arr['hub']['hubloc_url'])
			);

			if(! $r) {
				logger('activitypub_process_outbound: no recipients');
				return;
			}

		
			foreach($r as $contact) {

				$single = deliverable_singleton($arr['channel']['channel_id'],$contact);

				if($single) {
					$qi = activitypub_queue_message($jmsg,$arr['channel'],$contact,$target_item['mid']);
					if($qi)
						$arr['queued'][] = $qi;
				}
			}	
		}
	}
	
	return;

}


function activitypub_queue_message($msg,$sender,$recip,$message_id = '') {


	$dest_url = $recip['hubloc_callback'];

    logger('URL: ' . $dest_url, LOGGER_DEBUG);
	logger('DATA: ' . jindent($msg), LOGGER_DATA);

    if(intval(get_config('system','activitypub_test')) || intval(get_pconfig($sender['channel_id'],'system','activitypub_test'))) {
        logger('test mode - delivery disabled');
        return false;
    }

    $hash = random_string();

    logger('queue: ' . $hash . ' ' . $dest_url, LOGGER_DEBUG);
	Queue::insert(array(
        'hash'       => $hash,
        'account_id' => $sender['channel_account_id'],
        'channel_id' => $sender['channel_id'],
        'driver'     => 'activitypub',
        'posturl'    => $dest_url,
        'notify'     => '',
        'msg'        => $msg
    ));

    if($message_id && (! get_config('system','disable_dreport'))) {
        q("insert into dreport ( dreport_mid, dreport_site, dreport_recip, dreport_result, dreport_time, dreport_xchan, dreport_queue ) values ( '%s','%s','%s','%s','%s','%s','%s' ) ",
            dbesc($message_id),
            dbesc($dest_url),
            dbesc($dest_url),
            dbesc('queued'),
            dbesc(datetime_convert()),
            dbesc($sender['channel_hash']),
            dbesc($hash)
        );
    }

    return $hash;

}


function activitypub_connection_remove(&$x) {

	$recip = q("select * from abook left join xchan on abook_xchan = xchan_hash where abook_id = %d",
		intval($x['abook_id'])
	);

	if((! $recip) || $recip[0]['xchan_network'] !== 'activitypub')
		return; 

	$channel = channelx_by_n($recip[0]['abook_channel']);
	if(! $channel)
		return;

	$p = asencode_person($channel);
	if(! $p)
		return;

	// send an unfollow activity to the followee's inbox

	$orig_activity = get_abconfig($recip[0]['abook_channel'],$recip[0]['xchan_hash'],'activitypub','follow_id');

	if($orig_activity && $recip[0]['abook_pending']) {


		// was never approved

		$msg = array_merge(['@context' => [
				ACTIVITYSTREAMS_JSONLD_REV,
				'https://w3id.org/security/v1',
				z_root() . ZOT_APSCHEMA_REV

			]], 
			[
				'id'    => z_root() . '/follow/' . $recip[0]['abook_id'] . '#reject',
				'type'  => 'Reject',
				'actor' => $p,
				'object'     => [
					'type'   => 'Follow',
					'id'     => $orig_activity,
					'actor'  => $recip[0]['xchan_hash'],
					'object' => $p
				],
				'to' => [ $recip[0]['xchan_hash'] ]
		]);
		del_abconfig($recip[0]['abook_channel'],$recip[0]['xchan_hash'],'activitypub','follow_id');

	}
	else {

		// send an unfollow

		$msg = array_merge(['@context' => [
				ACTIVITYSTREAMS_JSONLD_REV,
				'https://w3id.org/security/v1',
				z_root() . ZOT_APSCHEMA_REV
			]], 
			[
				'id'    => z_root() . '/follow/' . $recip[0]['abook_id'] . '#Undo',
				'type'  => 'Undo',
				'actor' => $p,
				'object'     => [
					'id'     => z_root() . '/follow/' . $recip[0]['abook_id'],
					'type'   => 'Follow',
					'actor'  => $p,
					'object' => $recip[0]['xchan_hash']
				],
				'to' => [ $recip[0]['xchan_hash'] ]
			]
		);
	}

	$msg['signature'] = \Zotlabs\Lib\LDSignatures::dopplesign($msg,$channel);

	$jmsg = json_encode($msg, JSON_UNESCAPED_SLASHES);

	// is $contact connected with this channel - and if the channel is cloned, also on this hub?
	$single = deliverable_singleton($channel['channel_id'],$recip[0]);

	$h = q("select * from hubloc where hubloc_hash = '%s' limit 1",
		dbesc($recip[0]['xchan_hash'])
	);

	if($single && $h) {
		$qi = activitypub_queue_message($jmsg,$channel,$h[0]);
		if($qi) {
			\Zotlabs\Daemon\Master::Summon([ 'Deliver' , $qi ]);
		}
	}
		
}




function activitypub_permissions_create(&$x) {

	// send a follow activity to the followee's inbox

	if($x['recipient']['xchan_network'] !== 'activitypub') {
		return;
	}

	$p = asencode_person($x['sender']);
	if(! $p)
		return;

	$msg = array_merge(['@context' => [
			ACTIVITYSTREAMS_JSONLD_REV,
			'https://w3id.org/security/v1',
			z_root() . ZOT_APSCHEMA_REV
		]], 
		[
			'id'     => z_root() . '/follow/' . $x['recipient']['abook_id'],
			'type'   => 'Follow',
			'actor'  => $p,
			'object' => $x['recipient']['xchan_url'],
			'to'     => [ $x['recipient']['xchan_hash'] ]
	]);


	$msg['signature'] = \Zotlabs\Lib\LDSignatures::dopplesign($msg,$x['sender']);

	$jmsg = json_encode($msg, JSON_UNESCAPED_SLASHES);

	// is $contact connected with this channel - and if the channel is cloned, also on this hub?
	$single = deliverable_singleton($x['sender']['channel_id'],$x['recipient']);

	$h = q("select * from hubloc where hubloc_hash = '%s' limit 1",
		dbesc($x['recipient']['xchan_hash'])
	);

	if($single && $h) {
		$qi = activitypub_queue_message($jmsg,$x['sender'],$h[0]);
		if($qi)
			$x['deliveries'] = $qi;
	}
		
	$x['success'] = true;

}


function activitypub_permissions_accept(&$x) {

	// send an accept activity to the followee's inbox

	if($x['recipient']['xchan_network'] !== 'activitypub') {
		return;
	}

	// we currently are not handling send of reject follow activities; this is permitted by protocol

	$accept = get_abconfig($x['recipient']['abook_channel'],$x['recipient']['xchan_hash'],'activitypub','their_follow_id');
	if(! $accept)
		return;

	$p = asencode_person($x['sender']);
	if(! $p)
		return;

	$msg = array_merge(['@context' => [
			ACTIVITYSTREAMS_JSONLD_REV,
			'https://w3id.org/security/v1',
			z_root() . ZOT_APSCHEMA_REV
		]], 
		[
			'id'     => z_root() . '/follow/' . $x['recipient']['abook_id'],
			'type'   => 'Accept',
			'actor'  => $p,
			'object' => [
				'type'   => 'Follow',
				'id'     => $accept,
				'actor'  => $x['recipient']['xchan_hash'],
				'object' => z_root() . '/channel/' . $x['sender']['channel_address']
			],
			'to' => [ $x['recipient']['xchan_hash'] ]
	]);

	$msg['signature'] = \Zotlabs\Lib\LDSignatures::dopplesign($msg,$x['sender']);

	$jmsg = json_encode($msg, JSON_UNESCAPED_SLASHES);

	// is $contact connected with this channel - and if the channel is cloned, also on this hub?
	$single = deliverable_singleton($x['sender']['channel_id'],$x['recipient']);

	$h = q("select * from hubloc where hubloc_hash = '%s' limit 1",
		dbesc($x['recipient']['xchan_hash'])
	);

	if($single && $h) {
		$qi = activitypub_queue_message($jmsg,$x['sender'],$h[0]);
		if($qi)
			$x['deliveries'] = $qi;
	}
		
	$x['success'] = true;

}


function activitypub_profile_mod_init($x) {
	
	if(ActivityStreams::is_as_request()) {
		$chan = channelx_by_nick(argv(1));
		if(! $chan)
			http_status_exit(404, 'Not found');

		$p = Activity::encode_person($chan,true,true);
		if(! $p)
			http_status_exit(404, 'Not found');

		$x = [
			'@context' => [ 
				ACTIVITYSTREAMS_JSONLD_REV,
				'https://w3id.org/security/v1',
				z_root() . ZOT_APSCHEMA_REV
			],
			'type' => 'Profile',
			'describes' => $p
		];
				
		$headers = [];
		$headers['Content-Type'] = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"' ;
		$x['signature'] = LDSignatures::dopplesign($x,$chan);
		$ret = json_encode($x, JSON_UNESCAPED_SLASHES);
		$headers['Digest'] = HTTPSig::generate_digest_header($ret);
		$h = HTTPSig::create_sig($headers,$chan['channel_prvkey'],channel_url($chan));
		HTTPSig::set_headers($h);
		echo $ret;
		killme();

	}
}


function activitypub_item_mod_init($x) {
	
	if(ActivityStream::is_as_request()) {
		$item_id = argv(1);
		if(! $item_id)
			http_status_exit(404, 'Not found');


		$item_normal = " and item.item_hidden = 0 and item.item_type = 0 and item.item_unpublished = 0 
			and item.item_delayed = 0 and item.item_blocked = 0 ";

		$sql_extra = item_permissions_sql(0);

		$r = q("select * from item where mid like '%s' $item_normal $sql_extra limit 1",
			dbesc($item_id . '%')
		);
		if(! $r) {
			$r = q("select * from item where mid like '%s' $item_normal limit 1",
				dbesc($item_id . '%')
			);
			if($r) {
				http_status_exit(403, 'Forbidden');
			}
			http_status_exit(404, 'Not found');
		}

		xchan_query($r,true);
		$items = fetch_post_tags($r,true);


		$chan = channelx_by_n($items[0]['uid']);

		if(! $chan)
			http_status_exit(404, 'Not found');

		if(! perm_is_allowed($chan['channel_id'],get_observer_hash(),'view_stream'))
			http_status_exit(403, 'Forbidden');

		$i = Activity::encode_item($items[0]);
		if(! $i)
			http_status_exit(404, 'Not found');


		$x = array_merge(['@context' => [
			ACTIVITYSTREAMS_JSONLD_REV,
			'https://w3id.org/security/v1',
			z_root() . ZOT_APSCHEMA_REV
			]], $i);


		$headers = [];
		$headers['Content-Type'] = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"' ;
		$x['signature'] = LDSignatures::dopplesign($x,$chan);
		$ret = json_encode($x, JSON_UNESCAPED_SLASHES);
		$headers['Digest'] = HTTPSig::generate_digest_header($ret);
		$h = HTTPSig::create_sig($headers,$chan['channel_prvkey'],channel_url($chan));
		HTTPSig::set_headers($h);
		echo $ret;
		killme();

	}
}


function activitypub_follow_mod_init($x) {

	if(ActivityStreams::is_as_request() && argc() == 2) {
		$abook_id = intval(argv(1));
		if(! $abook_id)
			return;
		$r = q("select * from abook left join xchan on abook_xchan = xchan_hash where abook_id = %d",
			intval($abook_id)
		);
		if (! $r)
			return;

		$chan = channelx_by_n($r[0]['abook_channel']);

		if(! $chan)
			http_status_exit(404, 'Not found');

		$actor = Activity::encode_person($chan);
		if(! $actor)
			http_status_exit(404, 'Not found');


		$x = array_merge(['@context' => [
				ACTIVITYSTREAMS_JSONLD_REV,
				'https://w3id.org/security/v1',
				z_root() . ZOT_APSCHEMA_REV
			]], 
			[
				'id'     => z_root() . '/follow/' . $r[0]['abook_id'],
				'type'   => 'Follow',
				'actor'  => $actor,
				'object' => $r[0]['xchan_url']
		]);
				

		$headers = [];
		$headers['Content-Type'] = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"' ;
		$x['signature'] = \Zotlabs\Lib\LDSignatures::dopplesign($x,$chan);
		$ret = json_encode($x, JSON_UNESCAPED_SLASHES);
		$headers['Digest'] = HTTPSig::generate_digest_header($ret);
		$h = HTTPSig::create_sig($headers,$chan['channel_prvkey'],channel_url($chan));
		HTTPSig::set_headers($h);
		echo $ret;
		killme();

	}
}


function activitypub_queue_deliver(&$b) {

	$outq = $b['outq'];
	$base = $b['base'];
	$immediate = $b['immediate'];


	if($outq['outq_driver'] === 'activitypub') {
		$b['handled'] = true;

		$channel = channelx_by_n($outq['outq_channel']);

		$retries = 0;

		$headers = [];
		$headers['Content-Type'] = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"' ;
		$ret = $outq['outq_msg'];
		$headers['Digest'] = HTTPSig::generate_digest_header($ret);
		$xhead = HTTPSig::create_sig($headers,$channel['channel_prvkey'],channel_url($channel));
		$result = z_post_url($outq['outq_posturl'],$outq['outq_msg'],$retries,[ 'headers' => $xhead ]);

		if($result['success'] && $result['return_code'] < 300) {
			logger('deliver: queue post success to ' . $outq['outq_posturl'], LOGGER_DEBUG);
			if($base) {
				q("update site set site_update = '%s', site_dead = 0 where site_url = '%s' ",
					dbesc(datetime_convert()),
					dbesc($base)
				);
			}
			q("update dreport set dreport_result = '%s', dreport_time = '%s' where dreport_queue = '%s'",
				dbesc('accepted for delivery'),
				dbesc(datetime_convert()),
				dbesc($outq['outq_hash'])
			);
			Queue::remove($outq['outq_hash']);

			// server is responding - see if anything else is going to this destination and is piled up 
			// and try to send some more. We're relying on the fact that do_delivery() results in an 
			// immediate delivery otherwise we could get into a queue loop. 

			if(! $immediate) {
				$x = q("select outq_hash from outq where outq_posturl = '%s' and outq_delivered = 0",
					dbesc($outq['outq_posturl'])
				);

				$piled_up = array();
				if($x) {
					foreach($x as $xx) {
						 $piled_up[] = $xx['outq_hash'];
					}
				}
				if($piled_up) {
					do_delivery($piled_up,true);
				}
			}
		}
	}
}

function activitypub_feature_settings_post(&$b) {

	if($_POST['activitypub-submit']) {
		set_pconfig(local_channel(),'system','activitypub_allowed',intval($_POST['activitypub_allowed']));
		set_pconfig(local_channel(),'activitypub','downgrade_media', 1 - intval($_POST['activitypub_send_media']));
		set_pconfig(local_channel(),'activitypub','include_groups',intval($_POST['include_groups']));		
		info( t('ActivityPub Protocol Settings updated.') . EOL);
	}
}


function activitypub_feature_settings(&$s) {

	$ap_allowed = get_pconfig(local_channel(),'system','activitypub_allowed');

	$sc = '<div>' . t('The ActivityPub protocol does not support location independence. Connections you make within that network may be unreachable from alternate channel locations.') . '</div><br>';

	$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
		'$field'	=> array('activitypub_allowed', t('Enable the ActivityPub protocol for this channel'), $ap_allowed, '', $yes_no),
	));
	$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
		'$field'	=> array('include_groups', t('Deliver to ActivityPub recipients in privacy groups'), get_pconfig(local_channel(),'activitypub','include_groups'), t('May result in a large number of mentions and expose all the members of your privacy group'), $yes_no),
	));

	$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
		'$field'	=> array('activitypub_send_media', t('Send multi-media HTML articles'), 1 - intval(get_pconfig(local_channel(),'activitypub','downgrade_media',true)), t('Not supported by some microblog services such as Mastodon'), $yes_no),
	));

	$s .= replace_macros(get_markup_template('generic_addon_settings.tpl'), array(
		'$addon' 	=> array('activitypub', '<img src="addon/activitypub/activitypub.png" style="width:auto; height:1em; margin:-3px 5px 0px 0px;">' . t('ActivityPub Protocol Settings'), '', t('Submit')),
		'$content'	=> $sc
	));

	return;

}
