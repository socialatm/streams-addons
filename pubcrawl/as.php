<?php

/**
 * @brief
 *
 * @param array $items
 * @return array
 */
function gen_asld($items) {
	$ret = array();
	if(! $items)
		return $ret;

	foreach($items as $item) {
		$ret[] = i2asld($item);
	}

	return $ret;
}

/**
 * @brief
 *
 * @param array $i
 * @return array
 */
function i2asld($i) {

	if(! $i)
		return array();

	$ret = array();

	$ret['@context'] = array( 'https://www.w3.org/ns/activitystreams', 'zot' => 'http://purl.org/zot/protocol');

	if($i['verb']) {
		if(strpos(dirname($i['verb'],'activitystrea.ms/schema/1.0'))) {
			$ret['type'] = ucfirst(basename($i['verb']));
		}
		elseif(strpos(dirname($i['verb'],'purl.org/zot'))) {
			$ret['type'] = 'zot:' . ucfirst(basename($i['verb']));
		}
	}
	$ret['id'] = $i['plink'];

	$ret['published'] = datetime_convert('UTC','UTC',$i['created'],ATOM_TIME);

	if($i['obj_type'] === ACTIVITY_OBJ_NOTE)
		$ret['object'] = asencode_item($i);

	$ret['actor'] = asencode_person($i['author']);

	return $ret;
}

function asencode_object($x) {

	if((substr(0,1,trim($x))) === '{' ) {
		$x = json_decode($x,true);
	}
	if($x['type'] === ACTIVITY_OBJ_PERSON) {
		return asfetch_person($x); 
	}
	if($x['type'] === ACTIVITY_OBJ_PROFILE) {
		return asfetch_profile($x); 
	}
}	

function asfetch_person($x) {
	return $x;
}

function asfetch_profile($x) {
	return $x;
}

function asencode_item($i) {

	$ret = array();

	if(intval($i['item_deleted'])) {
		$ret['type'] = 'Tombstone';
		$ret['formerType'] = 'Note';
		$ret['id'] = z_root() . '/item/' . urlencode($i['mid']);
		return $ret;
	}

	$ret['type'] = 'Note';
	$ret['id']   = z_root() . '/item/' . urlencode($i['mid']);

	if($i['title'])
		$ret['title'] = bbcode($i['title']);

	$ret['published'] = datetime_convert('UTC','UTC',$i['created'],ATOM_TIME);
	if($i['created'] !== $i['edited'])
		$ret['updated'] = datetime_convert('UTC','UTC',$i['edited'],ATOM_TIME);
	if($i['app']) {
		$ret['instrument'] = [ 'type' => 'Service', 'name' => $i['app'] ];
	}
	if($i['location'] || $i['coord']) {
		$ret['location'] = [ 'type' => 'Place' ];
		if($i['location']) {
			$ret['location']['name'] = $i['location'];
		}
		if($i['coord']) {
			$l = explode(' ',$i['coord']);
			$ret['location']['latitude'] = $l[0];
			$ret['location']['longitude'] = $l[1];
		}
	}

	if($i['id'] != $i['parent']) {
		$ret['inReplyTo'] = z_root() . '/item/' . urlencode($i['parent_mid']);
	}

	$ret['content']   = bbcode($i['body']);

	$ret['zot:owner'] = asencode_person($i['owner']);
	$ret['actor']     = asencode_person($i['author']);

	$ret['tag'] = [];
	$ret['tag'][] = [ 
		'type' => 'zot:messageId', 
		'id'   => z_root() . '/display/' . urlencode($i['mid']),
		'name' => $i['mid']
	];

	return $ret;
}




function asencode_activity($i) {

	$ret = array();

	if(intval($i['item_deleted'])) {
		$ret['type'] = 'Tombstone';
		$ret['formerType'] = activity_obj_mapper($i['obj_type']);
		$ret['id'] = z_root() . '/item/' . urlencode($i['mid']);
		return $ret;
	}

	$ret['type'] = activity_mapper($i['verb']);
	$ret['id']   = z_root() . '/activity/' . urlencode($i['mid']);

	if($i['title'])
		$ret['title'] = html2plain(bbcode($i['title']));

	$ret['published'] = datetime_convert('UTC','UTC',$i['created'],ATOM_TIME);
	if($i['created'] !== $i['edited'])
		$ret['updated'] = datetime_convert('UTC','UTC',$i['edited'],ATOM_TIME);
	if($i['app']) {
		$ret['instrument'] = [ 'type' => 'Service', 'name' => $i['app'] ];
	}
	if($i['location'] || $i['coord']) {
		$ret['location'] = [ 'type' => 'Place' ];
		if($i['location']) {
			$ret['location']['name'] = $i['location'];
		}
		if($i['coord']) {
			$l = explode(' ',$i['coord']);
			$ret['location']['latitude'] = $l[0];
			$ret['location']['longitude'] = $l[1];
		}
	}

	if($i['id'] != $i['parent']) {
		$ret['inReplyTo'] = z_root() . '/item/' . urlencode($i['parent_mid']);
	}

	$ret['content']   = bbcode($i['body']);

	$ret['zot:owner'] = asencode_person($i['owner']);
	$ret['actor']     = asencode_person($i['author']);
	if($i['obj']) {
		$ret['object'] = asencode_object($i['obj']);
	}
	else {
		$ret['object'] = asencode_item($i);
	}

	if($i['target']) {
		$ret['target'] = asencode_object($i['target']);
	}

	$ret['tag'] = [];
	$ret['tag'][] = [ 
		'type' => 'zot:messageId', 
		'id'   => z_root() . '/display/' . urlencode($i['mid']),
		'name' => $i['mid']
	];

	if(! $i['item_private']) {
		$ret['to'] = [ ACTIVITY_PUBLIC_INBOX ];
	}
	else {
		$ret['bto'] = as_map_acl($i);
	} 


	return $ret;
}


function as_map_acl($i) {

	$private = false;
	$list = [];
	$x = collect_recipients($i,$private);
	if($x) {
		stringify_array_elms($x);
		if(! $x)
			return;

		$strict = get_config('activitypub','compliance');
		$sql_extra = (($strict) ? " and xchan_network = 'activitypub' " : '');

		$details = q("select xchan_url from xchan where xchan_hash in (" . implode(',',$recipients) . ") $sql_extra");
		if($details) {
			foreach($details as $d) {
				$list[] = $d['xchan_url'];
			}
		}
	}

	return $list;

}


function asencode_person($p) {

	$ret = [];
	$ret['type']  = 'Person';
	$ret['id']    = $p['xchan_url'];
	if($p['xchan_addr'] && strpos($p['xchan_addr'],'@'))
		$ret['preferredUsername'] = substr($p['xchan_addr'],0,strpos($p['xchan_addr'],'@'));
	$ret['name']  = $p['xchan_name'];
	$ret['icon']  = [ 
		[
			'type'      => 'Image',
			'mediaType' => $p['xchan_photo_mimetype'],
			'url'       => $p['xchan_photo_l'],
			'height'    => 300,
			'width'     => 300,
		],
		[
			'type'      => 'Image',
			'mediaType' => $p['xchan_photo_mimetype'],
			'url'       => $p['xchan_photo_m'],
			'height'    => 80,
			'width'     => 80,
		],
		[
			'type'      => 'Image',
			'mediaType' => $p['xchan_photo_mimetype'],
			'url'       => $p['xchan_photo_s'],
			'height'    => 48,
			'width'     => 48,
		]
	];
	$ret['url'] = [
		'type'      => 'Link',
		'mediaType' => 'text/html',
		'href'      => $p['xchan_url']
	];

	$ret['me:magic_keys'] = [
		[ 
			'value'  => salmon_key($p['xchan_pubkey']), 
			'key_id' => base64url_encode(hash('sha256',salmon_key($p['xchan_pubkey'])),true)
		]
	];

	$c = channelx_by_hash($p['xchan_hash']);
	if($c) {
		$ret['inbox']       = z_root() . '/inbox/' . $c['channel_address'];
		$ret['outbox']      = z_root() . '/outbox/' . $c['channel_address'];
		$ret['endpoints']   = [ 'publicInbox' => z_root() . '/inbox/[public]' ];

		$ret['publicKey'] = [
			'id'           => $p['xchan_url'] . '/public_key_pem',
			'owner'        => $p['xchan_url'],
			'publicKeyPem' => $p['xchan_pubkey']
		];
	}
	else {
		$collections = get_xconfig($p['xchan_hash'],'activitystreams','collections',[]);
		if($collections) {
			$ret = array_merge($ret,$collections);
		}
		else {
			$ret['inbox'] = z_root() . '/nullbox';
			$ret['outbox'] = z_root() . '/nullbox';
		}
	}

	return $ret;
}


function activity_mapper($verb) {

	$acts = [
		'http://activitystrea.ms/schema/1.0/post'      => 'Create',
		'http://activitystrea.ms/schema/1.0/update'    => 'Update',
		'http://activitystrea.ms/schema/1.0/like'      => 'Like',
		'http://activitystrea.ms/schema/1.0/favorite'  => 'Like',
		'http://purl.org/zot/activity/dislike'         => 'Dislike',
		'http://activitystrea.ms/schema/1.0/tag'       => 'Add',
		'http://activitystrea.ms/schema/1.0/follow'    => 'Follow',
		'http://activitystrea.ms/schema/1.0/unfollow'  => 'Unfollow',
	];


	if(array_key_exists($verb,$acts)) {
		return $acts[$verb];
	}
	return false;
}


function activity_obj_mapper($obj) {

	$objs = [
		'http://activitystrea.ms/schema/1.0/note'           => 'Note',
		'http://activitystrea.ms/schema/1.0/comment'        => 'Note',
		'http://activitystrea.ms/schema/1.0/person'         => 'Person',
		'http://purl.org/zot/activity/profile'              => 'Profile',
		'http://activitystrea.ms/schema/1.0/photo'          => 'Image',
		'http://activitystrea.ms/schema/1.0/profile-photo'  => 'Icon',
		'http://activitystrea.ms/schema/1.0/event'          => 'Event',
		'http://activitystrea.ms/schema/1.0/wiki'           => 'Document',
		'http://purl.org/zot/activity/location'             => 'Place',
		'http://purl.org/zot/activity/chessgame'            => 'Game',
		'http://purl.org/zot/activity/tagterm'              => 'zot:Tag',
		'http://purl.org/zot/activity/thing'                => 'zot:Thing',
		'http://purl.org/zot/activity/file'                 => 'zot:File',
		'http://purl.org/zot/activity/poke'                 => 'zot:Action',
		'http://purl.org/zot/activity/react'                => 'zot:Reaction',
		'http://purl.org/zot/activity/mood'                 => 'zot:Mood',
		
	];

	if(array_key_exists($obj,$objs)) {
		return $objs[$obj];
	}
	return false;
}


function as_fetch($url) {
	$redirects = 0;
	$x = z_fetch_url($url,true,$redirects,
		['headers' => [ 'Accept: application/ld+json; profile="https://www.w3.org/ns/activitystreams"']]);
	if($x['success']) {
		return $x['body'];
	}
	return null;
}

function as_follow($channel,$act) {

	$contact = null;

	/* actor is now following $channel */

	$person_obj = $act->actor;
	if(is_array($person_obj)) {

		as_actor_store($person_obj['id'],$person_obj);

		// Do we already have an abook record? 

		$r = q("select * from abook left join xchan on abook_xchan = xchan_hash where abook_xchan = '%s' and abook_channel = %d limit 1",
			dbesc($person_obj['id']),
			intval($channel['channel_id'])
		);
		if($r) {
			$contact = $r[0];
		}
	}

	$x = \Zotlabs\Access\PermissionRoles::role_perms('social');
	$their_perms = \Zotlabs\Access\Permissions::FilledPerms($x['perms_connect']);

	if($contact && $contact['abook_id']) {

		// perhaps we were already sharing with this person. Now they're sharing with us.
		// That makes us friends. Maybe.

		foreach($their_perms as $k => $v)
			set_abconfig($channel['channel_id'],$contact['abook_xchan'],'their_perms',$k,$v);

		$abook_instance = $contact['abook_instance'];

		if(strpos($abook_instance,z_root()) === false) {
			if($abook_instance) 
				$abook_instance .= ',';
			$abook_instance .= z_root();

			$r = q("update abook set abook_instance = '%s', abook_not_here = 0 where abook_id = %d and abook_channel = %d",
				dbesc($abook_instance),
				intval($contact['abook_id']),
				intval($channel['channel_id'])
			);
		}
		return;
	}

	$r = q("select * from xchan where xchan_hash = '%s' and xchan_network = 'activitypub' limit 1",
		dbesc($person_obj['id'])
	);

	if(! $r) {
		logger('xchan not found for ' . $person_obj['id']);
		return;
	}
	$ret = $r[0];


	$p = \Zotlabs\Access\Permissions::connect_perms($channel['channel_id']);
	$my_perms  = $p['perms'];
	$automatic = $p['automatic'];

	$closeness = get_pconfig($channel['channel_id'],'system','new_abook_closeness');
	if($closeness === false)
		$closeness = 80;

	$r = abook_store_lowlevel(
		[
			'abook_account'   => intval($channel['channel_account_id']),
			'abook_channel'   => intval($channel['channel_id']),
			'abook_xchan'     => $ret['xchan_hash'],
			'abook_closeness' => intval($closeness),
			'abook_created'   => datetime_convert(),
			'abook_updated'   => datetime_convert(),
			'abook_connected' => datetime_convert(),
			'abook_dob'       => NULL_DATE,
			'abook_pending'   => intval(($automatic) ? 0 : 1),
			'abook_instance'  => z_root()
		]
	);
		
	if($my_perms)
		foreach($my_perms as $k => $v)
			set_abconfig($channel['channel_id'],$ret['xchan_hash'],'my_perms',$k,$v);

	if($their_perms)
		foreach($their_perms as $k => $v)
			set_abconfig($channel['channel_id'],$ret['xchan_hash'],'their_perms',$k,$v);


	if($r) {
		logger("New ActivityPub follower for {$channel['channel_name']}");

		$new_connection = q("select * from abook left join xchan on abook_xchan = xchan_hash left join hubloc on hubloc_hash = xchan_hash where abook_channel = %d and abook_xchan = '%s' order by abook_created desc limit 1",
			intval($channel['channel_id']),
			dbesc($ret['xchan_hash'])
		);
		if($new_connection) {
			\Zotlabs\Lib\Enotify::submit(
				[
					'type'	       => NOTIFY_INTRO,
					'from_xchan'   => $ret['xchan_hash'],
					'to_xchan'     => $channel['channel_hash'],
					'link'         => z_root() . '/connedit/' . $new_connection[0]['abook_id'],
				]
			);

			if($my_perms) {
				// Send back a follow notification to them
// @FIXME
//					$x = diaspora_share($channel,$new_connection[0]);
//					if($x)
//						Zotlabs\Daemon\Master::Summon(array('Deliver',$x));
		
			}

			$clone = array();
			foreach($new_connection[0] as $k => $v) {
				if(strpos($k,'abook_') === 0) {
					$clone[$k] = $v;
				}
			}
			unset($clone['abook_id']);
			unset($clone['abook_account']);
			unset($clone['abook_channel']);
		
			$abconfig = load_abconfig($channel['channel_id'],$clone['abook_xchan']);

			if($abconfig)
				$clone['abconfig'] = $abconfig;

			build_sync_packet($channel['channel_id'], [ 'abook' => array($clone) ] );
		}
	}


	/* If there is a default group for this channel, add this member to it */

	if($channel['channel_default_group']) {
		require_once('include/group.php');
		$g = group_rec_byhash($channel['channel_id'],$channel['channel_default_group']);
		if($g)
			group_add_member($channel['channel_id'],'',$ret['xchan_hash'],$g['id']);
	}

	return;

}



function as_actor_store($url,$person_obj) {

	$name = $person_obj['name'];
	if(! $name)
		$name = t('unknown');

	if($person_obj['icon']) {
		if(is_array($person_obj['icon'])) {
			if(array_key_exists('url',$person_obj['icon']))
				$icon = $person_obj['icon']['url'];
			else
				$icon = $person_obj['icon'][0]['url'];
		}
		else
			$icon = $person_obj['icon'];
	}

	if($person_obj['url'] && $person_obj['url']['href'])
		$profile = $person_obj['url']['href'];
	else
		$profile = $url;


	$inbox = $person_obj['inbox'];

	$collections = [];

	if($inbox) {
		$collectons['inbox'] = $inbox;
		if($person['outbox'])
			$collections['outbox'] = $person_obj['outbox'];
		if($person['publicInbox'])
			$collections['publicInbox'] = $person_obj['publicInbox'];
		if($person['followers'])
			$collections['followers'] = $person_obj['followers'];
		if($person['following'])
			$collections['following'] = $person_obj['following'];
	}

	// @todo fetch pubkey

	if(array_key_exists('publicKey',$person) && array_key_exists('publicKeyPem',$person['publicKey'])) {
		if($person['id'] === $person['publicKey']['owner']) {
			$pubkey = $person['publicKey']['publicKeyPem'];
		}
	}

	$r = q("select * from xchan where xchan_hash = '%s' limit 1",
		dbesc($person_obj['id'])
	);
	
	if(! $r) {
		// create a new record
		$r = xchan_store_lowlevel(
			[
				'xchan_hash'         => $url,
				'xchan_guid'         => $url,
				'xchan_pubkey'       => $pubkey,
				'xchan_addr'         => '',
				'xchan_url'          => $profile,
				'xchan_name'         => $name,
				'xchan_name_date'    => datetime_convert(),
				'xchan_network'      => 'activitypub'
			]
		);
	}
	else {

		// Record exists. Cache existing records for one week at most
		// then refetch to catch updated profile photos, names, etc. 

		$d = datetime_convert('UTC','UTC','now - 1 week');
		if($r[0]['xchan_name_date'] > $d)
			return;

		// update existing record
		$r = q("update xchan set xchan_name = '%s', xchan_network = '%s', xchan_name_date = '%s' where xchan_hash = '%s'",
			dbesc($name),
			dbesc('activitypub'),
			dbesc(datetime_convert()),
			dbesc($url)
		);
	}

	if($collections) {
		set_xconfig($url,'activitypub','collections',$collections);
	}

	$r = q("select * from hubloc where hubloc_hash = '%s' limit 1",
		dbesc($url)
	);

	if(! $r) {
		$r = hubloc_store_lowlevel(
			[
				'hubloc_guid'     => $url,
				'hubloc_hash'     => $url,
				'hubloc_addr'     => '',
				'hubloc_network'  => 'activitypub',
				'hubloc_url'      => $url,
				'hubloc_host'     => $hostname,
				'hubloc_callback' => $inbox,
				'hubloc_updated'  => datetime_convert(),
				'hubloc_primary'  => 1
			]
		);
	}

	if(! $icon)
		$icon = get_default_profile_photo(300);

	$photos = import_xchan_photo($icon,$url);
	$r = q("update xchan set xchan_photo_date = '%s', xchan_photo_l = '%s', xchan_photo_m = '%s', xchan_photo_s = '%s', xchan_photo_mimetype = '%s' where xchan_hash = '%s'",
		dbescdate(datetime_convert('UTC','UTC',$arr['photo_updated'])),
		dbesc($photos[0]),
		dbesc($photos[1]),
		dbesc($photos[2]),
		dbesc($photos[3]),
		dbesc($url)
	);

}