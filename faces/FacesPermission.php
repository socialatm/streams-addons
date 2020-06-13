<?php

/*
 * Take the "poor man's" approach to store the permissions in an obj per channel
 * (table obj).
 * 
 * Why?
 * - Comaptible between ZAP/Hubzilla
 * - Easy to understand for other devolopers.
 * - Follow the KISS principle.
 * - Store the permission in table obj instead of item to keep compatibility between ZAP and Hubzilla.
 * 
 * Here the comment of Mike Macgirvin on storing the permission this way:
 * 
 * "That's kind of a "poor man's" approach but it should work. 
 * You can store this anywhere.
 * 
 * You can also add the permission to the permissions system and federate it 
 * across other systems.  But I'll warn you that this is very hard and will be 
 * quite different between Hubzilla and Zap. It's *really* hard in Hubzilla.
 * 
 * So the "poor man's" approach isn't bad considering the options. If you'd like 
 * to look at extending permissions in Zap, I'd suggest going back through the 
 * zap-addons repository to a point before I deleted the wiki addon. This created
 * and managed new permissions through a plugin. If I recall the permissions part
 * involved 6-7 different hooks and the code for each one wasn't horrible 
 * (mostly just adding the new permission names to various lists) but altogether
 * it was a bit fiddly."
 * 
 * TODO: Change the approach Mike suggested (at least for ZAP).
 */

function check_faces_view_permission($a) {

	$uid = $a['channel_id']; // owner
	$observer_hash = $a['observer_hash'];

	$r = q("SELECT * "
			. "FROM "
			. "  obj "
			. "WHERE "
			. "  obj_channel = %d "
			. "  AND obj_term = '%s' "
			. "LIMIT 1", //
			intval($uid), //
			dbesc("view_faces")
	);
	
	if (!$r) {
		logger("No view permission obj found. You should never see this in the logs.");
		return 0;
	}

	$perms = permissions_sql($uid, $observer_hash); // channel_id of owner

	$r = q("SELECT * "
			. "FROM "
			. "  obj "
			. "WHERE "
			. "  obj_channel = %d "
			. "  AND obj_term = '%s' $perms "
			. "LIMIT 1", //
			intval($uid), //
			dbesc("view_faces")
	);
	
	if (!$r) {
		logger("Observer has no permissions to view faces for this channel. ");
		return 0;
	}
	return 1;
}

function check_faces_write_permission($a) {

	$uid = $a['channel_id']; // owner
	$observer_hash = $a['observer_hash'];

	$r = q("SELECT * "
			. "FROM "
			. "  obj "
			. "WHERE "
			. "  obj_channel = %d "
			. "  AND obj_term = '%s' "
			. "LIMIT 1", //
			intval($uid), //
			dbesc("write_faces")
	);
	
	if (!$r) {
		logger("No write permission obj found. You should never see this in the logs.");
		return 0;
	}

	$perms = permissions_sql($uid, $observer_hash); // channel_id of owner

	$r = q("SELECT * "
			. "FROM "
			. "  obj "
			. "WHERE "
			. "  obj_channel = %d "
			. "  AND obj_term = '%s' $perms "
			. "LIMIT 1", //
			intval($uid), //
			dbesc("write_faces")
	);
	
	if (!$r) {
		logger("Observer has no permissions to write faces for this channel. ");
		return 0;
	}
	return 1;
}
