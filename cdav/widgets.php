<?php

function widget_cdav() {
	if(!local_channel())
		return;

	if(\DBA::$dba && \DBA::$dba->connected)
		$pdovars = \DBA::$dba->pdo_get();
	else
		killme();

	$pdo = new \PDO($pdovars[0],$pdovars[1],$pdovars[2]);
	$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

	require_once 'vendor/autoload.php';

	$channel = \App::get_channel();

	$principalUri = 'principals/' . $channel['channel_address'];

	$caldavBackend = new \Sabre\CalDAV\Backend\PDO($pdo);

	$calendars = $caldavBackend->getCalendarsForUser($principalUri);

	//TODO: should probably also check for permission to send stream here
	$local_channels = q("SELECT * FROM channel LEFT JOIN abook ON abook_xchan = channel_hash WHERE channel_system = 0 AND channel_removed = 0 AND channel_hash != '%s' AND abook_channel = %d",
		dbesc($channel['channel_hash']),
		intval($channel['channel_id'])
	);

	$options .= '<option value="">' . t('Select Channel') . '</option>' . "\r\n";
	foreach($local_channels as $local_channel)
		$options .= '<option value="' . $local_channel['channel_address'] . '">' . $local_channel['channel_name'] . '</option>' . "\r\n";

	if(argc() == 2 && argv(1) === 'calendar') {

		//list calendars
		foreach($calendars as $calendar) {
			$perms = '(OWNER)';
			if($calendar['share-access'] == 2)
				$perms = '(R)';
			if($calendar['share-access'] == 3)
				$perms = '(RW)';

			$invites = $caldavBackend->getInvites($calendar['id']);

			$sharees = '';

			foreach($invites as $invite) {
				if((strpos($invite->href, 'mailto:') !== false)) {
					$sharee = substr($invite->href, 7);
					if($sharee != $channel['xchan_addr'])
						$sharees .= $sharee . (($invite->access == 3) ? ' (RW)' : ' (R)') . '<br>';
				}
			}

			$list .= '<strong>' . $calendar['{DAV:}displayname'] . '</strong> ' . $perms . ' <a href="/cdav/display/caldav/drop/' . $calendar['id'][0] . '">Delete</a><br>';
			if($calendar['share-access'] == 1) {
				$list .= $sharees;
				$list .= '<form method="post" action="">';
				$list .= '<input name="calendarid" type="hidden" value="' . $calendar['id'][0] . '">';
				$list .= '<input name="instanceid" type="hidden" value="' . $calendar['id'][1] . '">';
				$list .= '<select name="sharee">';
				$list .= $options;
				$list .= '</select><br>';
				$list .= '<select name="access">';
				$list .= '<option value="3">Read-write</option>';
				$list .= '<option value="2">Read-only</option>';
				$list .= '<option value="4">Revoke access</option>';
				$list .= '</select>';
				$list .= '<input value="Share" type="submit" name="share"><br><br>';
				$list .= '</form>';
			}

		}

		//create calendar
		$form = '<form method="post" action="">';
		$form .= '<input name="{DAV:}displayname" type="text"><br>';
		$form .= '<input value="Create" type="submit" name="create"><br><br>';
		$form .= '</form>';

		return $list . $form;
	}
}
