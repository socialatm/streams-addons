<?php

namespace Code\Module;

/**
 * -----------------------------------------------------------------------------
 * 
 * Purpose 
 * 
 * 1) export / import face encodings and names for
 * 
 *   - creating clones of a channel
 *   - export / backup / import channel data (to/from files)
 *   - sync between clones
 * 
 * 2) side effect compliance with Art. 20 GDPR, see https://gdpr-info.eu/art-20-gdpr/
 * 
 * -----------------------------------------------------------------------------
 * 
 * Distinctive features for this addon if clones are synched:
 * 
 * - a name (table faces_person) is connected to a channel
 * - a face (table faces_encoding) is connected to a name AND a channel AND a file
 * - an image can have many face encodings
 * 
 * Especially the dependencies a face has make the sync a bit tricky. You have to take
 * care that you do not end up having double and empty encodings in the table.
 * 
 * Double or empty encodings can appear if
 * 
 * - there are clones and they have the addon installed
 * - the user (or) admin deletes all encodings and names on a clone
 * - the user starts the face detection at the same time on two clones
 *
 */
function attach_face_encoding_export_data($encoding_id) {

	insertEncodingHashes();

	// Append the file hash and channel hash. Both are unique over channel clones.
	$r = q("SELECT "
			. "  faces_encoding.*, "
			. "  attach.hash as file_hash, "
			. "  channel.channel_hash, "
			. "  faces_person.hash AS name_hash "
			. "FROM "
			. "  faces_encoding "
			. "JOIN "
			. "  attach ON faces_encoding.id = attach.id "
			. "JOIN "
			. "  channel ON faces_encoding.channel_id = channel.channel_id "
			. "LEFT JOIN "
			. "  faces_person "
			. "ON "
			. "  faces_encoding.person_verified = faces_person.id "
			. "WHERE "
			. "  faces_encoding.encoding_id = %d", //
			intval($encoding_id)
	);

	if (!$r) {
		return false;
	}

	logger(print_r($r, true), LOGGER_DEBUG);

	return $r;
}

function attach_face_encodings_export_data($channel_id) {

	insertEncodingHashes();

	// Append the file hash and channel hash and name hash. Both are unique over channel clones.
	// The import on the clone needs them to figure out what file/name/channel belongs to the encoding.
	// (CHANGE ME perhaps: We could forget about the channel hash. It is not needed because the calling importing hook 
	// provides the channel.)
	$r = q("SELECT "
			. "  faces_encoding.*, "
			. "  attach.hash AS file_hash, "
			. "  channel.channel_hash, "
			. "  faces_person.hash AS name_hash "
			. "FROM "
			. "  faces_encoding "
			. "JOIN "
			. "  attach "
			. "ON "
			. "  faces_encoding.id = attach.id "
			. "JOIN "
			. "  channel "
			. "ON "
			. "  faces_encoding.channel_id = channel.channel_id "
			. "LEFT JOIN "
			. "  faces_person "
			. "ON "
			. "  faces_encoding.person_verified = faces_person.id "
			. "WHERE "
			. "  faces_encoding.channel_id = %d", //
			intval($channel_id));

	if (!$r) {
		return false;
	}


	return $r;
}

function insertEncodingHashes() {

	$r = q("SELECT "
			. "  encoding_id "
			. "FROM "
			. "  faces_encoding "
			. "WHERE "
			. "  encoding_hash = ''"
	);

	if (!r) {
		return;
	}

	foreach ($r as $enc) {
		$r = q("update "
				. "  faces_encoding "
				. "set "
				. "  encoding_hash = '%s' "
				. "where "
				. "  encoding_id = %d ", //
				dbesc(new_uuid()), //
				intval($enc['encoding_id'])
		);
	}
}

function attach_face_name_export_data($hash) {

	// Append the channel hash that is unique over channel clones.
	$r = q("SELECT "
			. "  faces_person.*, "
			. "  channel.channel_hash "
			. "FROM "
			. "  faces_person "
			. "JOIN "
			. "  channel ON faces_person.channel_id = channel.channel_id "
			. " WHERE "
			. "  faces_person.hash = '%s'", //
			dbesc($hash)
	);
	if (!$r) {
		// the person (name) is a real channel
		$r = q("SELECT "
				. "  * "
				. "FROM "
				. "  faces_person "
				. " WHERE "
				. "  hash = '%s'", //
				dbesc($hash)
		);
		if (!$r) {
			return false;
		}
	}

	logger(print_r($r, true), LOGGER_DEBUG);

	return $r;
}

function attach_face_names_export_data($channel_id) {

	$exported = [];

	// Append the channel hash that is unique over channel clones.
	$r = q("SELECT "
			. "  faces_person.*, "
			. "  channel.channel_hash "
			. "FROM "
			. "  faces_person "
			. "JOIN "
			. "  channel ON faces_person.channel_id = channel.channel_id "
			. " WHERE "
			. "  faces_person.channel_id = %d", //
			intval($channel_id)
	);
	if ($r) {
		$exported = $r;
	}
	// all real channesl tagge din images that are in the contact list
	// and are accepted
	$real_contacts = q("SELECT "
			. "    faces_person.* "
			. "FROM "
			. "    faces_person "
			. "JOIN "
			. "   abook "
			. "ON "
			. "    abook.abook_xchan = faces_person.xchan_hash "
			. "WHERE "
			. "   faces_person.xchan_hash != '' AND "
			. "   abook.abook_pending = 0 AND "
			. "   abook.abook_channel = %d ", //
			intval($channel_id)
	);

	if ($real_contacts) {
		$exported = array_merge($exported, $real_contacts);
	}

	if (sizeof($exported) < 1) {
		return false;
	}

	return $exported;
}

function import_faces_all($import_data) {

	if (!array_key_exists('channel', $import_data)) {
		return;
	}

	$chan_id = q("SELECT "
			. "  channel_id "
			. "FROM "
			. "  channel "
			. " WHERE "
			. "  channel_guid = '%s'", //
			dbesc($import_data['channel']['channel_guid'])
	);
	if (!$chan_id) {
		logger("Failed to import encodings and names. Reason: No channel found for guid=" . $import_data['channel']['channel_guid']);
		return;
	}

	if (!array_key_exists('data', $import_data)) {
		return;
	}
	$data = $import_data['data'];

	if (array_key_exists('faces_person', $data)) {
		import_faces_names($data['faces_person'], $chan_id[0]['channel_id']);
	}

	if (array_key_exists('faces_encoding', $data)) {
		import_faces_encodings($data['faces_encoding'], $chan_id[0]['channel_id']);
	}

	if (array_key_exists('faces_permission', $data)) {
		import_faces_permission($data['faces_permission'], $chan_id[0]['channel_id']);
	}
}

function import_faces_permission($perm_objects, $chan_id) {

	logger("import permission view_faces / write_faces for channel_id = obj_channel=" . $chan_id);

	foreach ($perm_objects as $a) {

		logger(print_r($a, true), LOGGER_DEBUG);

		$r = q("SELECT * "
				. "FROM "
				. "  obj "
				. "WHERE "
				. "  obj_obj = '%s' ", //
				dbesc($a['obj_obj'])
		);
		if ($r) {
			logger("is permission update");

			$r = q("update "
					. "  obj "
					. "set "
					. "  obj_edited = '%s', "
					. "  allow_cid = '%s', "
					. "  allow_gid = '%s', "
					. "  deny_cid = '%s', "
					. "  deny_gid = '%s' "
					. "where "
					. "  obj_obj = '%s' ", //
					dbesc($a['obj_edited']), // edited
					dbesc($a['allow_cid']), //
					dbesc($a['allow_gid']), //
					dbesc($a['deny_cid']), //
					dbesc($a['deny_gid']), // 
					dbesc($a['obj_obj'])
			);

			if (!isset($r)) {
				logger("Failed to import (update) permission for obj_channel=" . $chan_id . ". with obj_obj=" . $a['obj_obj']);
			}
		} else {

			logger("is new permission");

			// the table item has different columns in ZAP and Hubzilla
			$r = q("INSERT INTO obj ( 	obj_obj, obj_channel, obj_term, obj_created, obj_edited, obj_quantity,  allow_cid, allow_gid, deny_cid, deny_gid )"
					. "VALUES (         '%s',    %d,          '%s',     '%s',        '%s',       %d,            '%s',     '%s',      '%s',     '%s') ", //
					dbesc($a['obj_obj']), //
					intval($chan_id), // uid
					dbesc($a['obj_term']), // obj_term = view_faces / write_faces
					dbesc($a['obj_created']), // created
					dbesc($a['obj_edited']), // edited
					intval($a['obj_quantity']), // uid
					dbesc($a['allow_cid']), //
					dbesc($a['allow_gid']), //
					dbesc($a['deny_cid']), //
					dbesc($a['deny_gid'])
			);

			if (!isset($r)) {
				logger("Failed to import (insert) permission for obj_channel=" . $chan_id . ". with obj_obj=" . $a['obj_obj']);
			}
		}
	}
}

function import_faces_encodings($a, $chan_id) {

	logger("Start to import face encodings");

	$newFileIDs = [];

	foreach ($a as $enc) {

		logger(print_r($enc, true), LOGGER_DEBUG);

		// Append the channel hash that is unique over channel clones.
		$r = q("SELECT "
				. "  * "
				. "FROM "
				. "  faces_encoding "
				. " WHERE "
				. "  encoding_hash = '%s'", //
				dbesc($enc['encoding_hash'])
		);
		if (!$r) {
			$file_id = import_faces_encoding_insert($enc, $chan_id, $newFileIDs);
			array_push($newFileIDs, $file_id);
		} else {
			import_faces_encoding_update($enc);
		}
	}
}

function import_faces_encoding_insert($enc, $chan_id, $newFileIDs) {

	$file_id = q("SELECT faces_encoding.encoding_id, faces_encoding.id, attach.hash FROM faces_encoding JOIN attach ON faces_encoding.id = attach.id WHERE attach.hash = '%s' ", dbesc($enc['file_hash']));

	if ($file_id) {

		if (!in_array($file_id[0]['id'], $newFileIDs)) {

			logger("Found existing encodings for channel id=" . $chan_id . " and file id=" . $file_id[0]['id'] . " Most probably this might happen if a) the user deleted all face encodings on the other clone, or b) a new face enconding was sent for this image");

			if (key_exists('overwrite', $enc)) {

				$r = q("delete from"
						. "  faces_encoding "
						. "where "
						. "  channel_id = %d AND "
						. "  id = %d", //
						intval($chan_id), //
						intval($file_id[0]['id'])
				);

				if (!$r) {
					return array('status' => false, 'errormsg' => 'Failed to remove existing face encodings for channel id=' . $chan_id . " and file id=" . $file_id[0]['id']);
				}

				logger("Deleted existing encodings for channel id=" . $chan_id . " and file id=" . $file_id[0]['id']);
			} else {

				logger("Did not delete existing encodings for channel id=" . $chan_id . " and file id=" . $file_id[0]['id']);
			}
		}
	}

	$file_id = NULL;

	//--------------------------------------------------------------------------
	// insert
	//--------------------------------------------------------------------------

	$file_id = q("SELECT "
			. "  id "
			. "FROM "
			. "  attach "
			. " WHERE "
			. "  hash = '%s'", //
			dbesc($enc['file_hash'])
	);
	if (!$file_id) {
		logger("You should never (or in very rare circumstances) see this in the logs. Failed to import (insert) name=" . $enc['name'] . " with hash=" . $enc['encoding_hash'] . " ! Reason: no image found with hash=" . $enc['file_hash'] . ". This could mean that an image was removed and the face detection has not run since then.");
		return;
	}

	$person_verified = getFaceId($enc);

	$r = q("INSERT "
			. "INTO faces_encoding (encoding_hash, finder, channel_id, id, updated, encoding, confidence, location, location_css, encoding_created, encoding_time, person_verified, verified_updated, distance, person_marked_unknown, marked_ignore, no_faces, error) "
			. "VALUES (             '%s',           %d,    %d,         %d, '%s',   '%s',     %d,         '%s',     '%s',         '%s',             %d,            %d,              '%s',             %d,       %d,                    %d,            %d,        %d   )", //
			dbesc($enc['encoding_hash']), //
			intval($enc['finder']), //
			intval($chan_id), //
			intval($file_id[0]['id']), //
			dbesc($enc['updated']), //
			dbesc($enc['encoding']), //
			floatval($enc['confidence']), //
			dbesc($enc['location']), //
			dbesc($enc['location_css']), //
			dbesc($enc['encoding_created']), //
			intval($enc['encoding_time']), //
			intval($person_verified), //
			dbesc($enc['verified_updated']), //
			floatval($enc['distance']), //			
			intval($enc['person_marked_unknown']), //
			intval($enc['marked_ignore']), //
			intval($enc['no_faces']), //
			intval($enc['error']) //
	);

	if (!isset($r)) {
		logger("Failed to import (insert) encoding with hash=" . $enc['encoding_hash'] . " for  (clone specific) file id=" . $file_id[0]['id']);
		return;
	}

	logger("Imported (insert) encoding with hash=" . $enc['encoding_hash'] . " for (clone specific) file id=" . $file_id[0]['id']);

	return $file_id[0]['id'];
}

function import_faces_encoding_update($enc) {

	$person_verified = getFaceId($enc);

	$r = q("update "
			. "  faces_encoding "
			. "set "
			. "  updated = '%s', "
			. "  encoding = '%s', "
			. "  confidence = %d, "
			. "  location = '%s', "
			. "  location_css = '%s', "
			. "  encoding_created = '%s', "
			. "  encoding_time = %d, "
			. "  person_verified = %d, "
			. "  verified_updated = '%s', "
			. "  distance = %d, "
			. "  person_marked_unknown = %d, "
			. "  marked_ignore = %d "
			. "where "
			. "  encoding_hash = '%s' ", // 			
			dbesc($enc['updated']), //			
			dbesc($enc['encoding']), //
			floatval($enc['confidence']), //
			dbesc($enc['location']), //
			dbesc($enc['location_css']), //
			dbesc($enc['encoding_created']), //
			intval($enc['encoding_time']), //			
			intval($person_verified), //
			dbesc($enc['verified_updated']), //
			floatval($enc['distance']), //
			intval($enc['person_marked_unknown']), //
			intval($enc['marked_ignore']), // 
			dbesc($enc['encoding_hash']) //
	);

	if (!isset($r)) {
		logger("Failed to import (update) encoding with hash=" . $enc['encoding_hash']);
		return;
	}

	logger("Imported (update) encoding with hash=" . $enc['encoding_hash'] . " and person verified id=" . $person_verified);

	removeDoubleEncodings($enc);
}

function removeDoubleEncodings($enc) {

	// Double or empty encodings appear only if
	// - there are clones and they have the addon installed
	// - the user (or) admin deletes all encodings and names on a clone
	// - the user starts the face detection at the same time on two clones
	// 
	// This method of deleting double and empty encondings is not perfect and
	// can take some ping pong of syncs between the clones.
	// 
	//
	//--------------------------------------------------------------------------
	// Remove double entries
	//--------------------------------------------------------------------------
	//
	// How can it happen that double entries for an encoding are created?
	// - One of the clones removed all face encodings and starts again to detect them.
	//   This will definitly happen in a real world scenaria. Also the admin
	//   can decide to remove all encodings (willingly or unwillingly).
	// - Parallel running detection on clones. The encodings would be the same
	//   (file, finder, location in image,...) but the encoding hash would be
	//   different.

	$encs = q("SELECT "
			. "  faces_encoding.location_css, "
			. "  faces_encoding.encoding_id, "
			. "  faces_encoding.id, "
			. "  faces_encoding.encoding_hash, "
			. "  attach.hash "
			. "FROM "
			. "  faces_encoding "
			. "JOIN "
			. "  attach "
			. "ON "
			. "  faces_encoding.id = attach.id "
			. "WHERE "
			. "  attach.hash = '%s' AND "
			. "  faces_encoding.location_css = '%s' ", //
			dbesc($enc['file_hash']), //
			dbesc($enc['location_css'])
	);
	if (!$encs) {
		logger("No double entries found for file with hash=" . $enc['file_hash'] . " and location css=" . enc['location_css'], LOGGER_DEBUG);
		return;
	}

	foreach ($encs as $doubleEnc) {
		if ($doubleEnc['encoding_hash'] != $enc['encoding_hash']) {
			// Assume the imported encoding is the one the user wants to keep.
			$r = q("DELETE FROM  "
					. "  faces_encoding "
					. "WHERE "
					. "  encoding_hash = '%s' ", //
					dbesc($doubleEnc['encoding_hash'])
			);
			if ($r) {
				logger("Deleted double encoding", LOGGER_DEBUG);
			} else {
				logger("Failed to deleted double encoding", LOGGER_DEBUG);
			}
			logger(print_r($doubleEnc, true), LOGGER_DEBUG);
		}
	}


	//--------------------------------------------------------------------------
	// Remove double entries
	//--------------------------------------------------------------------------
	//
	// In tests some encodings appeared that had no content (encoding, location in images,...)
	// This did happen if all encodings of one clone where deleted then where synched and so on.
	// This caused the sync of the empty encodings and this double face frames at the importing
	// clone.
	// An update means that at least on one of the clones the face detetion did run.
	// It is save to delete the empty encodings for a file if an import-update happens.
	$r = q("DELETE FROM  "
			. "  faces_encoding "
			. "WHERE "
			. "  id = %d AND " // all of them will have the same file id
			. "  error = 0 AND "
			. "  no_faces = 0 AND"
			. "  encoding = '' ", //
			dbesc($encs[0]['id']) // all of them will have the same file id
	);
	if ($r) {
		logger("Deleted  empty encoding(s) for file id=" . $encs[0]['id'] . " if any", LOGGER_DEBUG);
	} else {
		logger("Failed to empty encoding(s) for file id=" . $encs[0]['id'], LOGGER_DEBUG);
	}
}

function getFaceId($enc) {

	if (array_key_exists('name_hash', $enc)) {
		$name_id = q("SELECT "
				. "  id "
				. "FROM "
				. "  faces_person "
				. " WHERE "
				. "  hash = '%s'", //
				dbesc($enc['name_hash'])
		);

		if ($name_id && $name_id[0]) {
			$person_verified = $name_id[0]['id'];
			logger("Found name with id=" . $person_verified . " in table faces_person for hash=" . $enc['name_hash'], LOGGER_DEBUG);
			return $person_verified;
		} else {
			logger("Failed to find a name in table faces_person for hash=" . $enc['name_hash'], LOGGER_DEBUG);
			return 0;
		}
	}
}

function import_faces_names($a, $chan_id) {

	logger("Start to import face names");

	foreach ($a as $name) {

		logger(print_r($name, true), LOGGER_DEBUG);

		//----------------------------------------------------------------------
		// no real channels
		// Append the channel hash that is unique over channel clones.
		$r = q("SELECT "
				. "  * "
				. "FROM "
				. "  faces_person "
				. " WHERE "
				. "  hash = '%s' ", //
				dbesc($name['hash'])
		);
		if (!$r) {
			import_faces_name_insert($name, $chan_id);
		} else {
			import_faces_name_update($name);
		}
	}
}

function import_faces_name_real_channel_insert($name) {

	$r = q("INSERT "
			. "INTO faces_person ( hash, name, xchan_hash, updated ) "
			. "VALUES            ( '%s', '%s', '%s',       '%s'    )" //
			, dbesc($name['hash']), //
			dbesc($name['name']), //
			dbesc($name['xchan_hash']), //
			dbesc($name['updated']) // 
	);

	if (!isset($r)) {
		logger("Failed to import (insert) real channel with name=" . $name['name'] . " and hash=" . $name['hash']);
		return;
	}

	logger("Imported (insert) real channel with name=" . $name['name'] . " and hash=" . $name['hash']);
}

function import_faces_name_insert($name, $chan_id) {

	if ($name['xchan_hash'] != "") {
		// real channel
		import_faces_name_real_channel_insert($name);
		return;
	}

	$r = q("INSERT "
			. "INTO faces_person (hash, name, updated, channel_id, allow_cid, allow_gid, deny_cid, deny_gid) "
			. "VALUES (           '%s', '%s', '%s',    %d,         '%s',      '%s',      '%s',     '%s')" //
			, dbesc($name['hash']), //
			dbesc($name['name']), //
			dbesc($name['updated']), // 
			intval($chan_id), //
			dbesc($name['allow_cid']), //
			dbesc($name['allow_gid']), //
			dbesc($name['deny_cid']), //
			dbesc($name['deny_gid']) // 
	);

	if (!isset($r)) {
		logger("Failed to import (insert) name=" . $name['name'] . " with hash=" . $name['hash']);
		return;
	}

	logger("Imported (insert) name=" . $name['name'] . " with hash=" . $name['hash']);
}

function import_faces_name_update($name) {

	if ($name['xchan_hash'] != "") {
		// ignore updates of real channels
		logger("An update of a real channel as name is ignored (because the permissions are not used and therefor will not change");
		return;
	}

	$r = q("update "
			. "  faces_person "
			. "set "
			. "  name = '%s', "
			. "  updated = '%s', "
			. "  allow_cid = '%s', "
			. "  allow_gid = '%s', "
			. "  deny_cid = '%s', "
			. "  deny_gid = '%s' "
			. "where "
			. "  hash = '%s' ", // 
			dbesc($name['name']), // is null for "ignore" or "unknown". Null is converted to 0 what is intended here.
			dbesc($name['updated']), // 
			dbesc($name['allow_cid']), //
			dbesc($name['allow_gid']), //
			dbesc($name['deny_cid']), //
			dbesc($name['deny_gid']), // 
			dbesc($name['hash'])
	);

	if (!isset($r)) {
		logger("Failed to import (update) name=" . $name['name'] . " with hash=" . $name['hash']);
		return;
	}

	logger("Imported (update) name=" . $name['name'] . " with hash=" . $name['hash']);
}
