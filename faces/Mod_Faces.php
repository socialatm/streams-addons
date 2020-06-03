<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Libsync;

require_once('addon/faces/FacesPortability.php');
require_once('addon/faces/FacesPermission.php');

class Faces extends Controller {

	private $is_owner;
	private $can_write;
	private $owner;
	private $acl_item;
	private $observer;
	private $findersInEncodings = [];
	private $findersInConfig = [];

	function init() {

		$this->observer = \App::get_observer();

//		logger('This is the observer...', LOGGER_DEBUG);
//		logger(print_r($this->observer, true), LOGGER_DEBUG);

		$this->checkOwner();

		$this->loadFinders();

		$this->getPermissionOject();
	}

	function get() {

		//----------------------------------------------------------------------
		// permisson checks
		//----------------------------------------------------------------------
		if (!$this->owner) {
			if (local_channel()) { // if no channel name was provided, assume the current logged in channel
				$channel = \App::get_channel();
				logger('No nick but local channel - channel = ' . $channel, LOGGER_DEBUG);
				if ($channel && $channel['channel_address']) {
					$nick = $channel['channel_address'];
					goaway(z_root() . '/faces/' . $nick);
				}
			}
		}

		if (!$this->owner) {
			logger('No nick and no local channel', LOGGER_DEBUG);
			notice(t('Profile Unavailable.') . EOL);
			goaway(z_root());
		}

		if (is_null($this->observer)) {
			logger('observer unkown', LOGGER_DEBUG);
			goaway(z_root());
		}

		$status = $this->permChecksMin();

		if (!$status['status']) {
			logger('observer prohibited', LOGGER_DEBUG);
			notice($status['errormsg'] . EOL);
			goaway(z_root());
		}

		if (argc() > 2 && argv(2) == 'searchme') {
			// API: /faces/nick/searchme
			// get boxes of other instances
			logger("is searchme > skip more permission checks");
		} else {
			$status = $this->permChecks();
			if (!$status['status']) {
				logger('observer prohibited', LOGGER_DEBUG);
				notice($status['errormsg'] . EOL);
				goaway(z_root());
			}
		}

		$ret['status'] = true;
		$ret['message'] = "";

		// Does the user want to remove all the face encodings and names?
		if (argc() > 2 && argv(2) === "remove") {
			$ret = $this->removeChannelData();
			if (!$ret['status']) {
				notice($ret['errormsg'] . EOL);
				return $ret['errormsg'];
			} else {
				return $ret['msg'];
			}
		}
		$from = "";
		if (isset($_GET['from'])) {
			$from = $_GET['from'];
		}
		$to = "";
		if (isset($_GET['to'])) {
			$to = $_GET['to'];
		}

		// tell the browser about the log level
		$loglevel = -1;
		$logEnabled = get_config('system', 'debugging');
		if ($logEnabled) {
			$loglevel = (get_config('system', 'loglevel') ? get_config('system', 'loglevel') : LOGGER_NORMAL);
		}

		//----------------------------------------------------------------------
		// fill some elements in the ui including ACL
		//----------------------------------------------------------------------

		require_once('include/acl_selectors.php');

		$channel = \App::get_channel();

		$aclselect_e = populate_acl($this->acl_item, false, \Zotlabs\Lib\PermissionDescription::fromGlobalPermission('view_storage'));

		$lockstate = (($this->acl_item['allow_cid'] || $this->acl_item['allow_gid'] || $this->acl_item['deny_cid'] || $this->acl_item['deny_gid']) ? 'lock' : 'unlock');


		$version = $this->getAppVersion();
		logger("App version is " . $version);

		$zoom = get_config('faces', 'zoom');
		if (!$zoom) {
			$zoom = 3;
		}

		head_add_css('/addon/faces/view/css/faces.css');
		$o = replace_macros(get_markup_template('faces.tpl', 'addon/faces'), array(
			'$status' => $ret['status'],
			'$message' => $ret['message'],
			'$can_write' => $this->can_write ? 'true' : 'false',
			'$is_owner' => $this->is_owner ? 'true' : 'false',
			'$faces_date_from' => $from,
			'$faces_date_to' => $to,
			'$log_level' => $loglevel,
			'$version' => $version,
			'$faces_zoom' => $zoom,
			'$uid' => $channel['channel_id'],
			'$channelnick' => $channel['channel_address'],
			'$permissions' => t('Permissions'),
			'$aclselect' => $aclselect_e,
			'$allow_cid' => acl2json($this->acl_item['allow_cid']),
			'$allow_gid' => acl2json($this->acl_item['allow_gid']),
			'$deny_cid' => acl2json($this->acl_item['deny_cid']),
			'$deny_gid' => acl2json($this->acl_item['deny_gid']),
			'$lockstate' => $lockstate,
			'$permset' => t('Set/edit permissions'),
			'$submit' => t('Submit'),
			'acl_modal' => $aclselect_e
		));

		return $o;
	}

	function post() {

		$status = $this->permChecksMin();

		if (!$status['status']) {
			notice($status['errormsg'] . EOL);
			json_return_and_die(array('status' => false, 'errormsg' => $status['errormsg']));
		}

		if (argc() > 2 && argv(2) == 'searchme') {
			// API: /faces/nick/searchme
			// get boxes of other instances
			$this->searchMe();
		}

		if (!$status['status']) {
			notice($status['errormsg'] . EOL);
			json_return_and_die(array('status' => false, 'errormsg' => $status['errormsg']));
		}
		if (!$this->observer) {
			json_return_and_die(array('status' => false, 'errormsg' => 'Unknown observer. Please login.'));
		}

		$status = $this->permChecks();

		if (!$status['status']) {
			notice($status['errormsg'] . EOL);
			json_return_and_die(array('status' => false, 'errormsg' => $status['errormsg']));
		}
		if (!$this->observer) {
			json_return_and_die(array('status' => false, 'errormsg' => 'Unknown observer. Please login.'));
		}

		if (argc() > 2) {
			switch (argv(2)) {
				case 'search':
					// API: /faces/nick/search
					// get boxes of other instances
					$this->search();
				case 'name':
					// API: /faces/nick/search
					// get boxes of other instances
					$this->writeName();
				case 'permissions':
					// API: /faces/nick/permissions
					// set acl
					$this->setACL();
				case 'start':
					// API: /faces/nick/start
					// set acl
					$this->startFaceRecognition();
				default:
					break;
			}
		}
	}

	private function permChecksMin() {

		$owner_uid = $this->owner['channel_id'];

//		logger('DELETE ME: This is the owner...', LOGGER_DEBUG);
//		logger(print_r($this->owner, true), LOGGER_DEBUG);

		if (!$owner_uid) {
			logger('Stop: No owner profil', LOGGER_DEBUG);
			return array('status' => false, 'errormsg' => 'No owner profil');
		}

		if (!Apps::addon_app_installed($owner_uid, 'faces')) {
			logger('Stop: Owner profil has not addon installed', LOGGER_DEBUG);
			return array('status' => false, 'errormsg' => 'Owner profil has not addon installed');
		}

		$this->can_write = perm_is_allowed($owner_uid, get_observer_hash(), 'write_storage');
		logger('observer can write: ' . $this->can_write, LOGGER_DEBUG);

		logger('observer = ' . $this->observer['xchan_addr'] . ', owner = ' . $this->owner['xchan_addr'], LOGGER_DEBUG);

		$this->is_owner = ($this->observer['xchan_hash'] && $this->observer['xchan_hash'] == $this->owner['xchan_hash']);
		if ($this->is_owner) {
			logger('observer = owner', LOGGER_DEBUG);
		} else {
			logger('observer != owner', LOGGER_DEBUG);
		}

		return array('status' => true);
	}

	private function permChecks() {

		$owner_uid = $this->owner['channel_id'];

		if (observer_prohibited(true)) {
			logger('Stop: observer prohibited', LOGGER_DEBUG);
			return array('status' => false, 'errormsg' => 'observer prohibited');
		}

		if (!Apps::addon_app_installed($owner_uid, 'faces')) {
			logger('Stop: Owner profil has not addon installed', LOGGER_DEBUG);
			return array('status' => false, 'errormsg' => 'Owner profil has not addon installed');
		}

		if (!perm_is_allowed($owner_uid, get_observer_hash(), 'view_faces')) {
			logger('Stop: Permission view faces denied', LOGGER_DEBUG);
			return array('status' => false, 'errormsg' => 'Permission view faces denied');
		}

		// Leave this check because the observer needs permissions to view photos too
		if (!perm_is_allowed($owner_uid, get_observer_hash(), 'view_storage')) {
			logger('Stop: Permission view storage denied', LOGGER_DEBUG);
			return array('status' => false, 'errormsg' => 'Permission view storage denied');
		}

		return array('status' => true);
	}

	private function checkOwner() {
		// Determine which channel's faces to display to the observer
		$nick = null;
		if (argc() > 1) {
			$nick = argv(1); // if the channel name is in the URL, use that
		}
		logger('nick = ' . $nick, LOGGER_DEBUG);

		$this->owner = channelx_by_nick($nick);
	}

	function getPermissionOject() {

		$r = q("SELECT * "
				. "FROM "
				. "  obj "
				. "WHERE "
				. "  obj_channel = %d "
				. "  AND obj_term = '%s' "
				. "LIMIT 1", //
				intval($this->owner['channel_id']), //
				dbesc("view_faces")
		);

		if ($r) {
			logger('obj to hold permissions to view faces was found (does not have to be created)');
			$this->acl_item = $r[0];
			return;
		}

		$this->createPermissionObject();
	}

	function createPermissionObject() {

		$allow_cid = $this->owner['channel_allow_cid'];
		$allow_gid = $this->owner['channel_allow_gid'];
		$deny_cid = $this->owner['channel_deny_cid'];
		$deny_gid = $this->owner['channel_deny_gid'];

		$uuid = new_uuid();

		// the table item has different columns in ZAP and Hubzilla
		$r = q("INSERT INTO obj ( 	obj_obj, obj_channel, obj_term, obj_created, obj_edited, obj_quantity,  allow_cid, allow_gid, deny_cid, deny_gid )"
				. "VALUES (         '%s',    %d,          '%s',     '%s',        '%s',       %d,            '%s',     '%s',      '%s',     '%s') ", //
				dbesc($uuid), //
				intval($this->owner['channel_id']), // uid
				dbesc('view_faces'), // obj_term
				dbesc(datetime_convert()), // created
				dbesc(datetime_convert()), // edited
				intval(1), // uid
				dbesc($allow_cid), //
				dbesc($allow_gid), //
				dbesc($deny_cid), //
				dbesc($deny_gid)
		);

		$this->syncPermissionObject();
	}

	function syncPermissionObject() {

		// check and return
		$r = q("SELECT * "
				. "FROM "
				. "  obj "
				. "WHERE "
				. "  obj_channel = %d "
				. "  AND obj_term = '%s' "
				. "LIMIT 1", //
				intval($this->owner['channel_id']), //
				dbesc("view_faces")
		);
		if (!$r) {
			logger('ERROR just befor to sync the permission obj to clones. Obj to hold permissions for faces does not exist. You should never see this message in the logs.');
			json_return_and_die(array('status' => false, 'errormsg' => 'Failed to sync permissions using obj'));
		}
		logger('About to sync permission obj to clones.');

		$this->acl_item = $r[0];

		logger(print_r($this->acl_item, true), LOGGER_DEBUG);

		Libsync::build_sync_packet($this->owner['channel_id'], array('faces_permission' => array($this->acl_item)));
	}

	private function setACL() {

		if (!$this->is_owner) {
			logger('no permission to set permissions', LOGGER_DEBUG);
			json_return_and_die(array('status' => false, 'errormsg' => 'no permission to set permissions'));
		}

		$a = $_POST['acl'];
		if (!isset($a)) {
			logger('no name received from client', LOGGER_DEBUG);
			json_return_and_die(array('status' => false, 'errormsg' => 'No acl was sent by client (browser)'));
		}
		$aclArr = json_decode($a, true);

		$channel = \App::get_channel();

		require_once('ZapHubSpecific.php');
		$zs = new ZapHubSpecific();
		$x = $zs->setACL($aclArr, $channel);

		$this->updatePermissions($x);

		logger('sending post response for setting permissons successfully...', LOGGER_DEBUG);

		json_return_and_die(array('status' => true));
	}

	function updatePermissions($x) {
		$this->updatePermissionsNames($x);
		$this->updatePermissionObject($x);
	}

	function updatePermissionObject($x) {

		$r = q("update "
				. "  obj "
				. "set "
				. "  obj_edited = '%s', "
				. "  allow_cid = '%s', "
				. "  allow_gid = '%s', "
				. "  deny_cid = '%s', "
				. "  deny_gid = '%s' "
				. "where "
				. "  obj_channel = %d "
				. "  AND obj_term = '%s' "
				. "LIMIT 1 ", //
				dbesc(datetime_convert()), // edited
				dbesc($x['allow_cid']), //
				dbesc($x['allow_gid']), //
				dbesc($x['deny_cid']), //
				dbesc($x['deny_gid']), // 
				intval($this->owner['channel_id']), // uid
				dbesc("view_faces") // obj_term
		);

		if (!isset($r)) {
			json_return_and_die(array('status' => false, 'errormsg' => 'Failed to update permissions using obj'));
		}

		$this->syncPermissionObject();
	}

	function updatePermissionsNames($x) {

		$r = q("update "
				. "  faces_person "
				. "set "
				. "  updated = '%s', "
				. "  allow_cid = '%s', "
				. "  allow_gid = '%s', "
				. "  deny_cid = '%s', "
				. "  deny_gid = '%s' "
				. "where "
				. "  channel_id = %d ", // autoformat
				dbesc(datetime_convert()), // updated
				dbesc($x['allow_cid']), //
				dbesc($x['allow_gid']), //
				dbesc($x['deny_cid']), //
				dbesc($x['deny_gid']), // 
				intval($this->owner['channel_id']) // 
		);

		if (!isset($r)) {
			json_return_and_die(array('status' => false, 'errormsg' => 'Failed to update permissions of names'));
		}
	}

	private function removeChannelData() {

		if (!$this->is_owner) {
			logger('you are not allowe to delete data of this owner', LOGGER_DEBUG);
			return array('status' => false, 'errormsg' => 'you are not allowed to delete data of this owner');
		}

		$r = q("delete from"
				. "  faces_encoding "
				. "where "
				. "  channel_id = %d ", // autoformat
				intval($this->owner['channel_id'])
		);

		if (!$r) {
			return array('status' => false, 'errormsg' => 'Failed to remove face encodings');
		}

		$r = q("delete from"
				. "  faces_person "
				. "where "
				. "  channel_id = %d ", // autoformat
				intval($this->owner['channel_id'])
		);

		if (!$r) {
			return array('status' => false, 'errormsg' => 'Failed to remove names');
		}

		logger('Removed face encodings and name for channel', LOGGER_NORMAL);
		return array('status' => true, 'msg' => 'Removed face encodings and names for channel');
	}

	private function writeName() {

		$n = $_POST['name'];
		if (!isset($n)) {
			logger('no name received from client');
			json_return_and_die(array('status' => false, 'errormsg' => 'No name was sent by client (browser)'));
		}
		logger('received name = ' . $n, LOGGER_DEBUG);
		$nameRequestFromBrowser = json_decode($n, true);

		//----------------------------------------------------------------------
		//-- names --
		//----------------------------------------------------------------------
		// this holds the name the server sends back
		$name_db = [];
		$uuid = "";

		if ($nameRequestFromBrowser['new_name'] != "") {
			logger("Write encoding for a new name=" . $nameRequestFromBrowser['new_name'] . " - This is not a name of a real channel.", LOGGER_DEBUG);
			//------------------------------------------------------------------
			// This is not a real channel (it is just a new name)
			// Prevent double entries. This might happen in praxis
			$name_db = q("SELECT "
					. "  faces_person.name, faces_person.id, faces_person.channel_id, channel.channel_address "
					. "FROM "
					. "  faces_person "
					. "JOIN "
					. "  channel "
					. "ON channel.channel_id = faces_person.channel_id "
					. "WHERE "
					. "  faces_person.name = '%s' "
					. "  AND faces_person.channel_id = %d", //
					dbesc($nameRequestFromBrowser['new_name']), //
					intval($this->owner['channel_id']));
			if ($name_db) {
				logger('Please choose another name. The name exists already for the owner of this channel. Name: ' . $n, LOGGER_DEBUG);
				$enc['id'] = $nameRequestFromBrowser['encoding_id'];
				$encs[] = $enc;
				json_return_and_die(array('status' => false, 'encodings' => $encs, 'errormsg' => 'Please choose another name. The name exists already for the owner of this channel.'));
			} else {
				if (!$this->can_write) {
					logger('not allowed to create new names', LOGGER_DEBUG);
					$enc['id'] = $nameRequestFromBrowser['encoding_id'];
					$encs[] = $enc;
					json_return_and_die(array('status' => false, 'encodings' => $encs, 'errormsg' => 'not allowed to create new names for this channel'));
				}

				// Set the default permission: view_faces permission (set via lock icon)
				// 
				// TODO: The user should be able to set the permission for every name.
				$uuid = new_uuid();
				$r = q("INSERT "
						. "INTO faces_person (hash, name, channel_id, updated, allow_cid, allow_gid, deny_cid, deny_gid) "
						. "VALUES ('%s', '%s', %d, '%s', '%s', '%s', '%s', '%s')" //
						, dbesc($uuid), //
						dbesc($nameRequestFromBrowser['new_name']), //
						intval($this->owner['channel_id']), //
						dbesc(datetime_convert()), //
						dbesc($this->acl_item['allow_cid']), //
						dbesc($this->acl_item['allow_gid']), //P
						dbesc($this->acl_item['deny_cid']), //
						dbesc($this->acl_item['deny_gid']) // 
				);
				$name_db = q("SELECT "
						. "  faces_person.*, channel.channel_address "
						. "FROM "
						. "  faces_person "
						. "JOIN "
						. "  channel "
						. "ON channel.channel_id = faces_person.channel_id "
						. "WHERE "
						. "  faces_person.name = '%s' "
						. "  AND faces_person.channel_id = %d", //
						dbesc($nameRequestFromBrowser['new_name']), //
						intval($this->owner['channel_id']));
			}
		} else if ($nameRequestFromBrowser['xchan_hash'] != "") {
			logger("Write enconding for name with xchan_hash=" . $nameRequestFromBrowser['xchan_hash'] . " - This is a real channel.", LOGGER_DEBUG);
			//------------------------------------------------------------------
			// This is a real channel (not just a name)
			$name_db = q("SELECT "
					. "  faces_person.name, "
					. "  faces_person.id, "
					. "  faces_person.channel_id, "
					. "  faces_person.xchan_hash, "
					. "  xchan.xchan_addr as channel_address " // displayed in dropdown in brackets after the name 
					. "FROM "
					. "  faces_person "
					. "JOIN "
					. "  xchan "
					. "ON "
					. "  xchan.xchan_hash = faces_person.xchan_hash  "
					. "WHERE "
					. "  faces_person.xchan_hash = '%s' ", //
					dbesc($nameRequestFromBrowser['xchan_hash'])
			);
			if (!$name_db) {
				$addr = q("SELECT xchan.xchan_name FROM xchan WHERE xchan.xchan_hash = '%s' ", dbesc($nameRequestFromBrowser['xchan_hash']));
				$uuid = new_uuid();
				$r = q("INSERT "
						. "INTO faces_person ( hash, name, xchan_hash, updated ) "
						. "VALUES            ( '%s', '%s', '%s',       '%s'    )" //
						, dbesc($uuid), //
						dbesc($addr[0]['xchan_name']), //
						dbesc($nameRequestFromBrowser['xchan_hash']), //
						dbesc(datetime_convert())
				);
				$name_db = q("SELECT "
						. "  faces_person.name, "
						. "  faces_person.id, "
						. "  faces_person.channel_id, "
						. "  faces_person.xchan_hash, "
						. "  xchan.xchan_addr as channel_address " // displayed in dropdown in brackets after the name 
						. "FROM "
						. "  faces_person "
						. "JOIN "
						. "  xchan "
						. "ON "
						. "  xchan.xchan_hash = faces_person.xchan_hash  "
						. "WHERE "
						. "  faces_person.xchan_hash = '%s' ", //
						dbesc($nameRequestFromBrowser['xchan_hash'])
				);
			}
			$this->notifyTaggedContact($nameRequestFromBrowser['xchan_hash'], $name_db[0]['channel_address']);
		} else {
			logger("Write enconding for name with person_verified=" . $nameRequestFromBrowser['person_verified'] . " - This is not a real channel.", LOGGER_DEBUG);
			// This request is obviously about the encoding only.
			// The name was sent and is
			// - known and not changed
			// - neither a new name 
			// - nor a real channel (xchan_hash)
			$name_db = q("SELECT "
					. "  faces_person.name, faces_person.id, faces_person.channel_id, channel.channel_address "
					. "FROM "
					. "  faces_person "
					. "JOIN "
					. "  channel "
					. "ON channel.channel_id = faces_person.channel_id "
					. "WHERE "
					. "  faces_person.id = %d ", //
					intval($nameRequestFromBrowser['person_verified'])
			);
		}

		//----------------------------------------------------------------------
		//-- encodings --
		//----------------------------------------------------------------------		

		if (!$this->can_write) {
			logger('not allowed to write, but check if observer is the owner of the old name or has write permissions on the old name. This will be allowed. Example: set the face to unknown, ignored.', LOGGER_DEBUG);
			$allowed = $this->canWriteOldName($nameRequestFromBrowser);
			if (!$allowed) {
				$enc['id'] = $nameRequestFromBrowser['encoding_id'];
				$encs[] = $enc;
				logger('not allowed to change the old name to another one', LOGGER_DEBUG);
				json_return_and_die(array('status' => false, 'encodings' => $encs, 'errormsg' => 'not allowed to change the old name to another one'));
			}
		}

		// This is how a face will get or change a (verified) name in the ui.
		// The name id and the name itself (both from table faces_person) will be sent as response.
		// Only then (after the server response) the ui will change
		// - name inside the face frame
		// - color and style of the border of the face frame
		// Why this way?
		// The change (after the server response) is indicateng that the server/DB 
		// received the changes. The face recognition can be run again and guess more faces of the same person.
		$nameSetByUser = $name_db[0]['id'];

		$r = q("update "
				. "  faces_encoding "
				. "set "
				. "  person_verified = %d, "
				. "  verified_updated = '%s', "
				. "  person_marked_unknown = %d, "
				. "  marked_ignore = %d  "
				. "where "
				. "  encoding_id = %d ", // autoformat
				intval($nameSetByUser), // is null for "ignore" or "unknown". Null is converted to 0 what is intended here.
				dbesc(datetime_convert()), // just for auto format
				intval($nameRequestFromBrowser['person_marked_unknown']), // just for auto format
				intval($nameRequestFromBrowser['marked_ignore']), // just for auto format
				intval($nameRequestFromBrowser['encoding_id'])
		);

		if (!isset($r)) {
			$enc['id'] = $nameRequestFromBrowser['encoding_id'];
			$encs[] = $enc;
			json_return_and_die(array('status' => false, 'encodings' => $encs, 'errormsg' => 'Failed to update table face encodings'));
		}

		$encodings = q("SELECT "
				. "faces_encoding.encoding_id, "
				. "faces_encoding.id, "
				. "faces_encoding.finder, "
				. "faces_encoding.location_css, "
				. "faces_encoding.person_verified, "
				. "faces_encoding.person_recognized, "
				. "faces_encoding.person_marked_unknown, "
				. "faces_encoding.marked_ignore "
				. "FROM faces_encoding "
				. "WHERE "
				. "faces_encoding.encoding_id = %d", // autoformat
				intval($nameRequestFromBrowser['encoding_id']));
		if (!$encodings) {
			$enc['id'] = $nameRequestFromBrowser['encoding_id'];
			$encs[] = $enc;
			json_return_and_die(array('status' => false, 'encodings' => $encs, 'errormsg' => 'Failed to select encoding from db'));
		}

		$this->syncEncodingById($nameRequestFromBrowser['encoding_id'], $uuid);

		$encsToReturn = [];

		$sameFace = $this->writeFaceForOtherFinder($encodings[0], $uuid);
		if ($sameFace) {
			// to prevent a bug: browser shows encoding fount by other finder
			$encsToReturn[] = $this->prepareToSend($sameFace);
		}

		$encsToReturn[] = $this->prepareToSend($encodings[0]);

		//----------------------------------------------------------------------
		//-- upate exif if image with name as tag --
		//----------------------------------------------------------------------
		$this->updateImage($encodings[0]['id'], $encodings[0]['person_verified']);

		//----------------------------------------------------------------------
		//-- recognize --
		//----------------------------------------------------------------------
		$ret = $this->startFaceRecognition();

		logger("Set (verified) name id=" . $encodings[0]['person_verified'] . " for encoding id=" . $encodings[0]['id'], LOGGER_NORMAL);

		json_return_and_die(
				array(
					'status' => true,
					'encodings' => $encsToReturn,
					'name' => $name_db[0],
					'$message' => $ret['message']
		));
	}

	private function canWriteOldName($nameRequestFromBrowser) {
		// What was the old name of the face encoding before the user changed it?
		$person = q("SELECT "
				. "  faces_person.id, "
				. "  faces_person.channel_id "
				. "  FROM "
				. "    faces_person "
				. "  JOIN "
				. "    faces_encoding "
				. "  ON "
				. "    faces_person.id = faces_encoding.person_verified "
				. "  WHERE "
				. "    faces_person.id = %d "
				. "  LIMIT 1", //
				intval($nameRequestFromBrowser['name_id_old']));

		if (!$person) {
			logger("no existing name found for this old name id=" . $nameRequestFromBrowser['name_id_old'], LOGGER_DEBUG);
			return false;
		}
		// check if the observer has write permissions to change the name
		$chan_id = $person[0]['channel_id'];
		$observer_hash = get_observer_hash();
		$allowed = perm_is_allowed($chan_id, $observer_hash, 'write_storage');

		return $allowed;
	}

	private function writeFaceForOtherFinder($encoding, $name_hash) {
		// the browser shows one single finder only
		// thats why we tell the other finder what face (person) belongs to the frame
		$finder = 1;
		if ($encoding['finder'] == 1) {
			$finder = 2;
		}
		// Get the same image that was changed in browser		
		$encodings = q("SELECT "
				. "  faces_encoding.encoding_id, "
				. "  faces_encoding.encoding, "
				. "  faces_encoding.id, "
				. "  faces_encoding.finder, "
				. "  faces_encoding.location_css "
				. "FROM "
				. "  faces_encoding "
				. "WHERE "
				. "  faces_encoding.id = %d AND "
				. "  faces_encoding.finder = %d", intval($encoding['id']), intval($finder));
		if (!$encodings) {
			// if only one single finder is used (admin setting)
			// or the other finder has not found a face in this image
			return false;
		}

		// find the same location of the face changed by user
		$tmpLoc = $encoding['location_css'];
		$frameBrowser = explode(",", $tmpLoc);
		$encoding_id = -1;
		foreach ($encodings as $enc) {
			if ($enc['encoding'] == "") {
				continue;
			}
			$frameDB = explode(",", $enc['location_css']);
			$result1 = $this->isSameFaceFrame($frameBrowser, $frameDB);
			$result2 = $this->isSameFaceFrame($frameDB, $frameBrowser);
			if ($result1 && $result2) {
				$encoding_id = $enc['encoding_id'];
				breaK;
			}
		}
		if ($encoding_id < 0) {
			return false;
		}

		// write the name of the face that was found by the (other) finder
		$r = q("update "
				. "  faces_encoding "
				. "set "
				. "  person_verified = %d, "
				. "  verified_updated = '%s', "
				. "  person_marked_unknown = %d, "
				. "  marked_ignore = %d "
				. "where "
				. "  encoding_id = %d ", // autoformat
				intval($encoding['person_verified']), // is null for "ignore" or "unknown". Null is converted to 0 what is intended here.
				dbesc(datetime_convert()), // just for auto format
				intval($encoding['person_marked_unknown']), // just for auto format
				intval($encoding['marked_ignore']), // just for auto format
				intval($encoding_id)
		);

		if (!isset($r)) {
			logger("This is an error. Please investigate. Failed to update face encoding for the other finder=" . $finder, LOGGER_DEBUG);
			return false;
		}

		$encodings = q("SELECT "
				. "faces_encoding.encoding_id, "
				. "faces_encoding.id, "
				. "faces_encoding.finder, "
				. "faces_encoding.location_css, "
				. "faces_encoding.person_verified, "
				. "faces_encoding.person_recognized, "
				. "faces_encoding.person_marked_unknown, "
				. "faces_encoding.marked_ignore "
				. "FROM faces_encoding "
				. "WHERE "
				. "faces_encoding.encoding_id = %d", // autoformat
				intval($encoding_id));
		if (!$encodings) {
			return false;
		}

		$this->syncEncodingById($encoding_id, $name_hash);

		return $encodings[0];
	}

	private function isSameFaceFrame($frame1, $frame2) {
		// CSS absolut position in image from the edges of the picture in %
		// left_percent, right_percent, top_percent, bottom_percent
		$middleX1 = $frame1[0] + (100 - $frame1[1] - $frame1[0]) / 2;
		$rightX2 = 100 - $frame2[1];
		if ($middleX1 > $frame2[0] && $middleX1 < $rightX2) {
			$middleY1 = $frame1[2] + (100 - $frame1[3] - $frame1[2]) / 2;
			$rightY2 = 100 - $frame2[3];
			if ($middleY1 > $frame2[2] && $middleY1 < $rightY2) {
				return true;
			}
		}
		return false;
	}

	private function deleteMyFaceEncoding($encoding_id) {

		$r = q("update "
				. "  faces_encoding "
				. "set "
				. "  person_verified = 0, "
				. "  verified_updated = '%s', "
				. "  marked_ignore = 1  "
				. "where "
				. "  encoding_id = %d ", // 
				dbesc(datetime_convert()), // 
				intval($encoding_id)
		);

		if (!isset($r)) {
			json_return_and_die(array('status' => false, 'errormsg' => 'Failed to update table face encodings'));
		}

		$encodings = q("SELECT "
				. "faces_encoding.encoding_id, "
				. "faces_encoding.id, "
				. "faces_encoding.finder, "
				. "faces_encoding.location_css, "
				. "faces_encoding.person_verified, "
				. "faces_encoding.person_recognized, "
				. "faces_encoding.person_marked_unknown, "
				. "faces_encoding.marked_ignore "
				. "FROM faces_encoding "
				. "WHERE "
				. "faces_encoding.encoding_id = %d", // autoformat
				intval($encoding_id));
		if (!$encodings) {
			json_return_and_die(array('status' => false, 'encodings' => $encs, 'errormsg' => 'Failed to select encoding from db after update'));
		}

		$this->writeFaceForOtherFinder($encodings[0], "");

		$this->syncEncodingById($encoding_id, "");

		logger("A tagged user deleted his encoding_id=" . $encoding_id, LOGGER_NORMAL);

		json_return_and_die(array('status' => true, 'encoding_id' => $encoding_id));
	}

	/*
	 * One of the your contacts is viewing the images where he/she was tagged.
	 * 
	 * The contact might not have the permission to view your addon (faces).
	 * That's wyh some permission checks are switched off.
	 * 
	 * Result:
	 * - the contact is able to see himself only (no other face names in the same image)
	 * - the contact is able to remove himself permanently from the image
	 * 
	 */

	private function searchMe() {

		$n = $_POST['name'];

		if ($n) {

			logger('received name = ' . $n, LOGGER_DEBUG);
			$nameRequestFromBrowser = json_decode($n, true);

			if ($nameRequestFromBrowser['marked_ignore'] == 1 && $nameRequestFromBrowser['encoding_id'] != 0) {
				$this->deleteMyFaceEncoding($nameRequestFromBrowser['encoding_id']);
			}
		}

		$e = $_POST['delete_encoding_id'];

		if ($e) {

			logger('received encoding id = ' . $e . ' to delete (from tagged contact without permission to view the image where he was tagged', LOGGER_DEBUG);
			$deleteEncodingRequestFromBrowser = json_decode($e, true);

			$this->deleteMyFaceEncoding($deleteEncodingRequestFromBrowser);
		}

		$encodings = q("SELECT "
				. "  faces_encoding.encoding_id, "
				. "  faces_encoding.id, "
				. "  faces_encoding.finder, "
				. "  faces_encoding.location_css, "
				. "  faces_encoding.person_verified, "
				. "  faces_encoding.person_recognized, "
				. "  faces_encoding.person_marked_unknown, "
				. "  faces_encoding.marked_ignore, "
				. "  attach.hash "
				. "FROM "
				. "  attach "
				. "JOIN "
				. "  faces_encoding "
				. "ON "
				. "  faces_encoding.id = attach.id "
				. "JOIN "
				. "  faces_person "
				. "ON "
				. "  faces_encoding.person_verified = faces_person.id "
				. "WHERE "
				. "  faces_encoding.channel_id = %d AND "
				. "  faces_person.xchan_hash = '%s' "
				. "ORDER BY faces_encoding.id DESC "
				. "LIMIT 100 ", //
				intval($this->owner['channel_id']), //
				dbesc($this->observer['xchan_hash'])
		);

		if (!$encodings) {
			logger('did not find the observer in any encoding for channel_id=' . $this->owner['channel_id'] . ' and xchan_hash=' . $this->observer['xchan_hash'], LOGGER_DEBUG);
			json_return_and_die(array('status' => false, 'errormsg' => 'You are not found'));
		}

		$name_id = $encodings[0]['person_verified'];

		$names = q("SELECT "
				. "  faces_person.name, "
				. "  faces_person.id, "
				. "  faces_person.channel_id "
				. "FROM "
				. "  faces_person "
				. "WHERE faces_person.id = %d "
				. "LIMIT 1", intval($name_id));

		$names[0]['channel_address'] = 'me';

		$images = $this->filterMyImages($encodings, $name_id);

		json_return_and_die(
				array(
					'status' => true,
					'images' => $images,
					'names' => $names
		));
	}

	private function search() {

		//remove all encodings without images (deleted by user)
		$this->removeObsoleteEncodings();
		// remove all names that do not belong to any face
		$this->removeObsoleteNames();

		// the defaults
		$from = '0000-00-00';
		$to = date('Y-m-d') . " 23:59:59";
		$filter_names = [];
		$AND = "0";

		// read the request by the browser
		$filter = $_POST['filter'];
		if ($filter != "") {
			$filterArr = json_decode($filter, true);
			if ($filterArr['from'] != "") {
				$from = $filterArr['from'] . " 00:00:00";
			}
			if ($filterArr['to'] != "") {
				$to = $filterArr['to'] . " 23:59:59";
			}
			if ($filterArr['names'] != "") {
				$filter_names = $filterArr['names'];
			}
			if ($filterArr['and'] != "") {
				$AND = $filterArr['and'];
			}
		} else {
			logger('no filter received from client', LOGGER_DEBUG);
		}
		logger('received name = ' . $filter, LOGGER_DEBUG);

		//----------------------------------------------------------------------
		//-- get list of all names
		//----------------------------------------------------------------------

		$names = $this->listAllowedNames();

		logger("Found " . sizeof($names) . " names.", LOGGER_DEBUG);

		//----------------------------------------------------------------------
		//-- filter face encodings by time 
		//----------------------------------------------------------------------

		$perms = permissions_sql($this->owner['channel_id'], null, 'attach');

		// select all faces for
		// - the user (channel)
		// - the date from / to
		$encodings = q("SELECT "
				. "  faces_encoding.encoding_id, "
				. "  faces_encoding.id, "
				. "  faces_encoding.finder, "
				. "  faces_encoding.location_css, "
				. "  faces_encoding.person_verified, "
				. "  faces_encoding.person_recognized, "
				. "  faces_encoding.person_marked_unknown, "
				. "  faces_encoding.marked_ignore, "
				. "  attach.hash "
				. "FROM "
				. "  attach "
				. "JOIN "
				. "  faces_encoding "
				. "ON "
				. "  faces_encoding.id = attach.id "
				. "WHERE "
				. "  faces_encoding.encoding != '' "
				. "  AND faces_encoding.marked_ignore != 1 "
				. "  AND faces_encoding.no_faces != 1 "
				. "  AND faces_encoding.marked_ignore != 1 "
				. "  AND faces_encoding.error != 1 "
				. "  AND faces_encoding.channel_id = %d "
//				. "  AND faces_encoding.finder = %d "
				. "  AND attach.created between '%s' and '%s' $perms "
				. "ORDER BY faces_encoding.id  DESC " // 
				, intval($this->owner['channel_id']), // 
//				intval(1), //
				dbesc($from), //
				dbesc($to)
		);

		logger("Found " . sizeof($encodings) . " face encodings after date filter.", LOGGER_DEBUG);

		//----------------------------------------------------------------------
		//-- filter face encodings by names (AND / OR)
		//----------------------------------------------------------------------

		$images = $this->filterImages($encodings, $filter_names, $AND, $names);

		logger("Sending " . sizeof($images) . " images after name filter.", LOGGER_DEBUG);

		$names = $this->appendMissingNames($images, $names);
		$names = $this->appendAllowedContacts($names);

		json_return_and_die(
				array(
					'status' => true,
					'images' => $images,
					'names' => $names
		));
	}

	private function listAllowedNames() {
		// Who has granted the observer write permissions?
		// This includes his own names.
		// 
		// The effect will be:
		// If the observer B looks at the faces (images) of owner A then 
		// observer B will be able
		// - to use his own name to tag faces.
		// - to change the names (he owns) in the browser
		//  even he has no write permissions for channel A.
		//  
		// To be clear: This applies to names only that
		// are owned by B. This permission is checked by the browser and later on by the server
		// if B wants to write the name (for image of A) to the database.
		// 
		// The idea behind:
		// This opens the door a little bit to let user B tag faces of images of A
		// without B having the permission to write files and photos for A's channel.
		//
		// Think it over! Is it to complicated?
		//
		$channels = q("SELECT channel_id FROM faces_person GROUP by channel_id");
		$allowedChannelsWithWritePermissions = [];
		if (!$this->is_owner) {
			foreach ($channels as $channel) {
				$chan_id = $channel['channel_id'];
				$observer_hash = get_observer_hash();
				$allowed = perm_is_allowed($chan_id, $observer_hash, 'write_storage');
				if ($allowed) {
					array_push($allowedChannelsWithWritePermissions, $channel['channel_id']);
				}
			}
		}

		// Who has granted the observer read permissions on the names.
		// This has the effect that the name list in the browser shows the names
		// that are owned by the observer itself.
		$names = q("SELECT "
				. "  faces_person.name, "
				. "  faces_person.id, "
				. "  faces_person.channel_id, "
				. "  channel.channel_address, "
				. "  faces_person.xchan_hash "
				. "FROM "
				. "  faces_person "
				. "JOIN "
				. "  channel "
				. "ON channel.channel_id = faces_person.channel_id");
		$allowedNameIDs = $this->selectAllowedNameIDsForObserver();

		// add names the name where the observer has the permissions
		$namesToSend = [];
		foreach ($names as $name) {
			$chan_id = $name['channel_id'];
			$name_id = $name['id'];
			if (in_array($chan_id, $allowedChannelsWithWritePermissions) ? 1 : 0) {
				// look for the write permissions first
				$n = array(
					'id' => $name['id'],
					'name' => $name['name'],
					'channel_id' => $name['channel_id'],
					'channel_address' => $name['channel_address'],
					'xchan_hash' => ($name['xchan_hash'] ? $name['xchan_hash'] : ''),
					'w' => 1
				);
				$namesToSend[] = $n;
			} else if (in_array($name_id, $allowedNameIDs) ? 1 : 0) {
				// add the names with read permissions
				// The effect will be that the user can see and search this name
				// but he can not change the name of the face. No dialog will open.
				$n = array(
					'id' => $name['id'],
					'name' => $name['name'],
					'channel_id' => $name['channel_id'],
					'channel_address' => $name['channel_address'],
					'xchan_hash' => ($name['xchan_hash'] ? $name['xchan_hash'] : ''),
					'w' => 0
				);
				$namesToSend[] = $n;
			}
		}

		return $namesToSend;
	}

	private function appendAllowedContacts($names) {

		if (perm_is_allowed($this->owner['channel_id'], get_observer_hash(), 'view_contacts')) {

			logger('view_contacts allowed', LOGGER_DEBUG);

			//------------------------------------------------------------------
			// Select the real channels
			// - used already in images
			// - and are contacts of the owner (table abook)

			$r = q("SELECT "
					. "    faces_person.id, "
					. "    faces_person.name, "
					. "    faces_person.channel_id, "
					. "    faces_person.xchan_hash, "
					. "    xchan.xchan_addr AS channel_address "
					. "FROM "
					. "    faces_person "
					. "JOIN "
					. "   xchan "
					. "ON "
					. "   xchan.xchan_hash = faces_person.xchan_hash "
					. "JOIN "
					. "   abook "
					. "ON "
					. "    abook.abook_xchan = faces_person.xchan_hash "
					. "WHERE "
					. "   faces_person.xchan_hash != '' AND "
					. "   abook.abook_channel = %d ", //
					intval($this->owner['channel_id'])
			);

			if ($r) {
				$names = array_merge($names, $r);
			}

			$tmpList = [];
			foreach ($names as $nameInSendList) {
				if ($nameInSendList['xchan_hash']) {
					$tmpList[] = $nameInSendList['xchan_hash'];
				}
			}

			//------------------------------------------------------------------
			// Select the real channels
			// - not used already in images
			// - and are contact of the owner (table abook)

			$r = q("SELECT "
					. "  xchan.xchan_name, xchan.xchan_hash, xchan.xchan_addr "
					. "FROM "
					. "  xchan "
					. "JOIN "
					. "  abook "
					. "ON "
					. "  abook.abook_xchan = xchan.xchan_hash "
					. "WHERE "
					. "  abook.abook_channel = %d AND "
					. "  abook.abook_pending = 0 AND " // include accepted channesl only (so the other side is not annoyed by unwanted contacts)
					. "  xchan.xchan_network REGEXP 'zot' " //
					. " ORDER BY abook.abook_closeness ", //
					intval($this->owner['channel_id'])
			);

			if ($r) {
				foreach ($r as $name) {
					if (!in_array($name['xchan_hash'], $tmpList) ? 1 : 0) {
						$n = array(
							'id' => $this->owner['channel_id'] . "_" . $name['xchan_name'],
							'name' => $name['xchan_name'],
							'channel_id' => $this->owner['channel_id'],
							'channel_address' => $name['xchan_addr'],
							'xchan_hash' => ($name['xchan_hash'] ? $name['xchan_hash'] : ''),
							'w' => 0
						);
						$names[] = $n;
					}
				}
			}
		}

		return $names;
	}

	private function appendMissingNames($images, $names) {
		$sizeStart = sizeof($names);
		logger("Found " . sizeof($names) . " names before appending missing names.", LOGGER_DEBUG);
		// It can happen that a face has a name but this name is not in the
		// name list. Result: the face is framed in the image but shows no name.
		// When does it happen? If
		//   - The owner A of the channel is not the owner B of the name.
		//   - The owner B of the name has withdrawn the permission for the name.
		// it seems better to show him the name.
		// Keep mind that the observer C looks at the addon using the permissions of the
		// channel owner A.
		$ids_encodings = [];
		foreach ($images as $image) {
			foreach ($image['encodings'] as $encoding) {
				if (!in_array($encoding['pv'], $ids_encodings) && $encoding['pv'] != 0) {
					array_push($ids_encodings, $encoding['pv']);
				}
//				if (!in_array($encoding['pr'], $ids_encodings) && $encoding['pr'] != 0) {
//					array_push($ids_encodings, $encoding['pr']);
//				}
			}
		}
		$ids_names = [];
		foreach ($names as $name) {
			$ids_names[] = $name['id'];
		}
		foreach ($ids_encodings as $id_encoding) {
			if (!in_array($id_encoding, $ids_names)) {
				$r = q("SELECT "
						. "  faces_person.name, "
						. "  faces_person.id, "
						. "  faces_person.channel_id, "
						. "  channel.channel_address, "
						. "  faces_person.xchan_hash, "
						. "  xchan.xchan_addr "
						. "FROM "
						. "  faces_person "
						. "JOIN "
						. "  channel "
						. "ON "
						. "  channel.channel_id = faces_person.channel_id "
						. "LEFT JOIN "
						. "  xchan "
						. " ON  "
						. "  faces_person.xchan_hash  = xchan.xchan_hash  "
						. "WHERE "
						. "  faces_person.id = %d ", intval($id_encoding));
				if ($r) {
					$n = array(
						'id' => $r[0]['id'],
						'name' => $r[0]['name'],
						'channel_id' => ($r[0]['xchan_addr'] ? $r[0]['xchan_addr'] : $r[0]['channel_address']),
						'channel_address' => $r[0]['channel_address'],
						'xchan_hash' => ($r[0]['xchan_hash'] ? $r[0]['xchan_hash'] : ''),
						'w' => 0
					);
					$names[] = $n;
					logger("appending missing name with id=" . $r[0]['id'] . ", name=" . $r[0]['name'], LOGGER_DEBUG);
				}
			}
		}

		logger("Found " . sizeof($names) . " names after appending missing names.", LOGGER_DEBUG);
		$sizeEnd = sizeof($names);
		logger("Appended " . ($sizeEnd - $sizeStart) . " names (due to withdrawn permissions).", LOGGER_DEBUG);

		return $names;
	}

	private function selectAllowedNameIDsForObserver() {
		$channels = q("SELECT faces_person.channel_id, channel.channel_hash FROM faces_person JOIN channel  ON channel.channel_id = faces_person.channel_id GROUP by faces_person.channel_id");

		// who has granted the observer write permissions
		$allowed = [];
		foreach ($channels as $channel) {
			$perms = permissions_sql($channel['channel_id'], $this->owner['channel_hash']); // this channel is the owner of the name(s)
			// apply on observer
//			$r = q("SELECT id, name FROM faces_person WHERE channel_id = %d $perms ", intval($channel['channel_id']));
			// apply on owner of the channel
			// the obserer looks with the eyes (pemissions) of the owner of the images at the faces
			$r = q("SELECT id, name FROM faces_person WHERE channel_id = %d $perms ", intval($channel['channel_id']));
			if (!r) {
				continue;
			}
			foreach ($r as $name) {
				array_push($allowed, $name['id']);
			}
		}
		return $allowed;
	}

	private function filterMyImages($encodings, $name_id) {
		if (sizeof($encodings) == 0) {
			return [];
		}

		$file_id = -1;
		$images = [];
		foreach ($encodings as $encoding) {
			if ($file_id == $encoding['id']) {
				continue;
			}
			// the encondings are ordered by file id
			if ($encoding['person_verified'] == $name_id) {
				$img = $this->createMyImage($encoding);
				array_push($images, $img);
				$file_id = $encoding['id'];
			}
		}
		return $images;
	}

	private function filterImages($encodings, $filter_names, $AND, $allowedNames) {
		if (sizeof($encodings) == 0) {
			return [];
		}

		$preferedFinder = $this->choosePreferedeFinder();

		$allowedNameIDs = [];
		foreach ($allowedNames as $name) {
			if (!in_array($name['id'], $allowedNameIDs)) {
				$allowedNameIDs[] = $name['id'];
			}
		}

		$current_file_id = -1;
		$images = [];
		$image_encodings = [];
		foreach ($encodings as $encoding) {
			// the encondings are ordered by file id
			if ($encoding['id'] != $current_file_id && $current_file_id != -1) {
				$img = $this->checkImage($image_encodings, $filter_names, $AND, $preferedFinder, $allowedNameIDs);
				if ($img) {
					array_push($images, $img);
				}
				$image_encodings = [];
			}
			$current_file_id = $encoding['id'];
			array_push($image_encodings, $encoding);
		}
		// last image
		$img = $this->checkImage($image_encodings, $filter_names, $AND, $preferedFinder, $allowedNameIDs);
		if ($img) {
			array_push($images, $img);
		}
		return $images;
	}

	private function checkImage($encodings, $filter_names, $AND, $preferedFinder, $allowedNameIDs) {
		if (sizeof($encodings) < 1) {
			return false;
		}
		// Remove same frames (faces).
		// Why to Remove them before applying the name filter?
		//   Each finder might produce a different guess who the person is.
		//   So, choose the guessed name of the finder we trust more to have a correct guess.
		$encs = [];
		if (sizeof($this->findersInEncodings) > 1) {
			$encs = $this->chooseFrames($encodings, $preferedFinder);
		} else {
			$encs = $encodings;
		}

		if (sizeof($filter_names) < 1) {
			$img = $this->createImage($encs);
			return $img;
		}

		// apply name filter
		$img = $this->filterNames($encs, $filter_names, $AND, $allowedNameIDs);
		return $img;
	}

	/*
	 * Apply the name filter (if the user searches for names)
	 * - remove same frames (faces9
	 * - filter by names
	 */

	private function filterNames($encodings, $filter_names, $AND, $allowedNameIDs) {

		// filter by names
		$andResults = [];
		$counter = 0;
		foreach ($filter_names as $name) {
			$andResults[$counter] = false;
			$name_id = $name['id'];
			foreach ($encodings as $encoding) {
				if ($encoding['person_verified'] > 0) {
					if ($encoding['person_verified'] == $name_id) {
						if ($AND) {
							$andResults[$counter] = true;
							break;
						} else {
							return $this->createImage($encodings);
						}
					}
				} else if ($encoding['person_recognized'] > 0) {
					if ($encoding['person_recognized'] == $name_id) {
						if ($AND) {
							$andResults[$counter] = true;
							break;
						} else {
							return $this->createImage($encodings);
						}
					}
				}
			}
			$counter++;
		}
		if (!$AND) {
			return false;
		}
		foreach ($andResults as $result) {
			if (!$result) {
				return false;
			}
		}
		$img = $this->createImage($encodings);
		return $img;
	}

	private function choosePreferedeFinder() {
		$count1 = $this->countCorrectGuesses(1);
		$count2 = $this->countCorrectGuesses(2);
		$preferedFinder = 2;  // default
		if (!$this->findersInEncodings[2]) {
			$preferedFinder = 1;  // default
		}
		if ($count1 > $count2) {
			$preferedFinder = 1;
		}
		return $preferedFinder;
	}

	private function chooseFrames($encodings, $preferedFinder) {
		//
		// The site admin can switch on more than one methods (finders) to detect and recognize faces.
		// The finders will detect the same faces.
		// But the user wants to see just one frame around a face (not one for each finder).
		// The appoach is:
		// 1. Preparation (was one befor:
		//    What finder has the best recognition (recognition = this face belongs to Brigitte Bardot)?
		//    Justs count how often it makes a correct guess
		// 2. Find the  same faces found by each finder (detection = this is a face)
		// 3. Choose the finders we trust more, see above (what finder has the most correct recognitions)
		//    
		//----------------------------------------------------------------------
		// 2)
		$encodingsToRemove = [];
		foreach ($encodings as $encoding) {
			if ($encoding['finder'] == 2) {
				continue;
			}
			$loc1 = $encoding['location_css'];
			$frame1 = explode(",", $encoding['location_css']);
			foreach ($encodings as $enc) {
				if ($enc['finder'] == 1) {
					continue;
				}
				$loc2 = $enc['location_css'];
				$frame2 = explode(",", $enc['location_css']);
				$result1 = $this->isSameFaceFrame($frame1, $frame2);
				$result2 = $this->isSameFaceFrame($frame2, $frame1);
				//--------------------------------------------------------------
				// 3)
				if ($result1 && $result2) {
					if ($preferedFinder == 1) {
						array_push($encodingsToRemove, $enc['encoding_id']);
					} else {
						array_push($encodingsToRemove, $encoding['encoding_id']);
					}
				}
			}
		}
		// finally remove the encoding (from the image sent to browser)
		// TODO: this way to remove elements from an array seem to be overcomplicated
		//       > Fixme
		$validEncodings = [];
		foreach ($encodings as $e) {
			if (!in_array($e["encoding_id"], $encodingsToRemove)) {
				array_push($validEncodings, $e);
			}
		}
		return $validEncodings;
	}

	private function countCorrectGuesses($finder) {

		$count = q("SELECT "
				. "  COUNT(*) AS matches "
				. "FROM "
				. "  faces_encoding "
				. "WHERE "
				. "  person_verified = person_recognized "
				. "  ANd person_verified != 0 "
				. "  AND finder = %d " // autoformat
				, intval($finder));

		if (!$count) {
			return 0;
		}

		return intval($count[0]['matches']);
	}

	private function createImage($encodings) {
		$image = [];
		$image['id'] = $encodings[0]['id'];
		$image['src'] = $encodings[0]['hash'];
		$encs = [];
		for ($x = 0; $x < sizeof($encodings); $x++) {
			// shrink the size of the json response
			$encs[$x] = $this->prepareToSend($encodings[$x]);
		}
		$image['encodings'] = $encs;
		return $image;
	}

	private function createMyImage($encoding) {
		$image = [];
		$image['id'] = $encoding['id'];
		$image['srcs'] = $encoding['hash'];
		$image['src'] = $encoding['hash'];
		$encs = [];
		$enc['id'] = $encoding['encoding_id'];
		$enc['f'] = 0;
		$enc['l'] = $encoding['location_css'];
		$enc['pv'] = $encoding['person_verified'];
		$enc['pr'] = 0;
		$enc['pu'] = 0;
		$enc['pi'] = 0;
		$encs[0] = $enc;
		$image['encodings'] = $encs;
		return $image;
	}

	private function prepareToSend($encoding) {
		$enc = [];
		$enc['id'] = $encoding['encoding_id'];
		$enc['f'] = $encoding['finder'];
		$enc['l'] = $encoding['location_css'];
		$enc['pv'] = $encoding['person_verified'];
		$enc['pr'] = $encoding['person_recognized'];
		$enc['pu'] = $encoding['person_marked_unknown'];
		$enc['pi'] = $encoding['marked_ignore'];
		return $enc;
	}

	private function removeObsoleteNames() {
		// clean up names that are not used any more
		$names_deleted = q("Select "
				. "  faces_person.id, faces_encoding.person_verified "
				. "FROM "
				. "  faces_person "
				. "LEFT JOIN faces_encoding "
				. "  ON faces_encoding.person_verified = faces_person.id "
				. "WHERE "
				. " faces_encoding.person_verified IS NULL");
		if ($names_deleted) {
			foreach ($names_deleted as $row) {
				$tmp = q("DELETE from faces_person WHERE id = %d", intval($row['id']));
			}
		}
	}

	private function removeObsoleteEncodings() {
		// clean up names that are not used any more
		$files_deleted = q("SELECT "
				. "  faces_encoding.encoding_id, "
				. "  attach.id "
				. "FROM "
				. "  faces_encoding "
				. "LEFT JOIN  attach "
				. "  ON  attach.id = faces_encoding.id "
				. "WHERE "
				. "  attach.id IS NULL");
		if ($files_deleted) {
			foreach ($files_deleted as $row) {
				$tmp = q("DELETE from faces_encoding WHERE encoding_id = %d", intval($row['encoding_id']));
			}
		}
	}

	private function updateImage($fileId, $nameId) {

		if (get_config('faces', 'exiftool') != "1") {
			logger("exiftool is not configured to be used (admin settings)", LOGGER_DEBUG);
			return;
		}

		$rpath = q("SELECT "
				. "  content, "
				. "  hash "
				. "		FROM "
				. "  attach "
				. "		WHERE "
				. "  id = %d", intval($fileId));
		if (!$rpath) {
			logger("failed to get path for file id = " . $fileId, LOGGER_DEBUG);
			return;
		}
		$rname = q("SELECT "
				. "  name "
				. "		FROM "
				. "  faces_person "
				. "		WHERE "
				. "  id = %d", intval($nameId));
		if (!$rname) {
			logger("failed to get (verified) name for name id = " . $nameId, LOGGER_DEBUG);
			return;
		}
		require_once('ExifUpdate.php');
		$eu = new ExifUpdate();
		$path = getcwd() . "/" . $rpath[0]['content'];
		$name = $rname[0]['name'];
		$written = $eu->updateExif($path, $name);
		if ($written) {
			$this->syncImage($rpath[0]['hash']);
		}
	}

	private function syncImage($hash) {
		logger("Sync image with hash=" . $hash . " with updated names in exif data to clones", LOGGER_NORMAL);
		$sync = attach_export_data($this->owner, $hash);
		if ($sync) {
			Libsync::build_sync_packet($this->owner['channel_id'], array('file' => array($sync)));
		}
	}

	private function loadFinders() {
		$finder1 = q("SELECT * FROM  faces_encoding WHERE finder = 1 AND channel_id = %d", $this->owner['channel_id']);
		if ($finder1) {
			$this->findersInEncodings[1] = "finder 1";
		}

		$finder2 = q("SELECT * FROM  faces_encoding WHERE finder = 2 AND channel_id = %d", $this->owner['channel_id']);
		if ($finder2) {
			$this->findersInEncodings[2] = "finder 2";
		}

		if (get_config('faces', 'finder1') == "1") {
			$this->findersInConfig[1] = (get_config('faces', 'finder1config') ? get_config('faces', 'finder1config') : "confidence=0.5;minsize=20");
		}
		if (get_config('faces', 'finder2') == "1") {
			$this->findersInConfig[2] = (get_config('faces', 'finder2config') ? get_config('faces', 'finder2config') : "tolerance=0.6");
		}
	}

	private function startFaceRecognition() {
		if (sizeof($this->findersInConfig) < 1) {
			notice(t('Face detection is not activated') . EOL);
			return;
		}

		require_once('FaceRecognition.php');

		$fr = new FaceRecognition();

		$ret = $fr->isScriptRunning();
		if ($ret['status']) {
			notice(t('Face detection is still busy') . EOL);
			return;
		}

		$ret = $this->checkForNewImages();
		if (!$ret['status']) {
			return $ret['message'];
		}

		$ret = $fr->detect();
		return $ret;
	}

	private function checkForNewImages() {
		$images = $this->selectNewImages();
		$ret = $this->insertImageIDs($images);
		return $ret;
	}

	private function insertImageIDs($images) {
		$uuids = [];
		foreach ($images as $image) {
			foreach ($this->findersInConfig as $finder_id => $finder_Config) {
				$uuid = new_uuid();
				$r = q("INSERT "
						. "INTO faces_encoding "
						. "  (channel_id, id, finder, encoding_hash) "
						. "VALUES "
						. "  (%d, %d, %d, '%s')", // prevent autoformat
						intval($image[1]), intval($image[0]), intval($finder_id), dbesc($uuid)
				);
				if (!$r) {
					$msg = "Failed to insert new image into table faces_encoding. This should never happen.";
					logger($msg, LOGGER_DEBUG);
					return array('status' => false, 'message' => $msg);
				}
				array_push($uuids, $uuid);
				logger("Inserted face encoding into faces_encoding. Channel id=" . $image[1] . ", file id=" . $image[0] . ", finder=" . $finder_id . ", encoding hash=" . $uuid, LOGGER_DEBUG);
			}
		}
		$this->syncEncodingsByHash($uuids);
		$msg = "Inserted " . sizeof($images) . " image(s) into table faces_encoding";
		logger($msg, LOGGER_DEBUG);
		return array('status' => TRUE, 'message' => $msg);
	}

	private function syncEncodingsByHash($uuids) {
		$encs = [];
		foreach ($uuids as $uuid) {
			$enc = q("SELECT "
					. "  encoding_id "
					. "FROM "
					. "  faces_encoding "
					. "WHERE "
					. "  encoding_hash = '%s' "
					, dbesc($uuid));
			if ($enc && $enc[0]) {
				$encExport = attach_face_encoding_export_data($enc[0]['encoding_id']);
				if ($encExport) {
					$encExport[0]['overwrite'] = 1;
					array_push($encs, $encExport[0]);
				}
			}
		}
		if (sizeof($encs > 0)) {
			logger("about to sync new encodings to clones", LOGGER_NORMAL);
			Libsync::build_sync_packet($this->owner['channel_id'], array('faces_encoding' => $encs));
		}
	}

	private function syncEncodingById($encoding_id, $name_hash) {
		$enc = attach_face_encoding_export_data($encoding_id);
		$name = "";
		if ($name_hash) {
			$name = attach_face_name_export_data($name_hash);
		}
		if ($enc && $name) {
			logger("sync new encoding with hash=" . $enc[0]['encoding_hash'] . " and new name with hash=" . $name_hash . "to clones", LOGGER_NORMAL);
			Libsync::build_sync_packet($this->owner['channel_id'], array('faces_person' => $name, 'faces_encoding' => $enc));
		} else if ($enc) {
			logger("sync new encoding with hash=" . $enc[0]['encoding_hash'] . "  to clones", LOGGER_NORMAL);
			Libsync::build_sync_packet($this->owner['channel_id'], array('faces_encoding' => $enc));
		} else if ($name) {
			logger("Sync new name with hash=" . $name_hash . " to channel clones", LOGGER_NORMAL);
			Libsync::build_sync_packet($this->owner['channel_id'], array('faces_person' => $name));
		}
	}

	private function selectNewImages() {
		$images = [];
		$r = q("SELECT "
				. "  id, uid, content, hash "
				. "FROM "
				. "  attach "
				. "LEFT JOIN "
				. "  faces_encoding USING (id) "
				. "WHERE "
				. "  faces_encoding.id IS NULL "
				. "  AND attach.is_photo=1 "
				. "  AND attach.uid = %d", intval($this->owner['channel_id']));
		if ($r) {
			foreach ($r as $row) {
				array_push($images, [$row['id'], $row['uid'], $row['content'], $row['hash']]);
			}
		}
		return $images;
	}

	function notifyTaggedContact($xchan_hash, $channel_address) {
		$link = z_root() . '/faces/' . $this->owner['channel_address'] . '/searchme';
		$body = $channel_address . ', you where tagged [zrl=' . $link . ']here[/zrl].'
				. ' You can remove you by clicking into the frame around your face and then the eye icon.'
				. ' The removal can not be undone by the owner of the image. In case you do not have the'
				. ' permission to view an image a delete button will be displayed instead.';
		$allow_cid = '<' . $xchan_hash . '>';
		post_activity_item(array('body' => $body, 'allow_cid' => $allow_cid));
		logger("Posted notify message to " . $this->observer['xchan_url'] . ", link: " . $link, LOGGER_DEBUG);
	}

	function getAppVersion() {
		$r = q("SELECT app.app_version FROM app WHERE app.app_name = 'Faces' and app.app_channel = %d", $this->owner['channel_id']);
		if (!$r) {
			return "";
		}
		return $r[0]["app_version"];
	}

}
