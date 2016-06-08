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

	if(argc() == 3 && argv(2) === 'caldav') {

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
						$sharees .= $sharee . (($invite->access == 3) ? ' (RW)' : ' (R)');
				}
			}

			$list .= $calendar['{DAV:}displayname'] . ' ' . $perms . ' <a href="/cdav/display/caldav/drop/' . $calendar['id'][0] . '">Delete</a><br>';
			$list .= $sharees;
			$list .= '<form method="post" action="">';
			$list .= '<input name="calendarid" type="hidden" value="' . $calendar['id'][0] . '">';
			$list .= '<input name="instanceid" type="hidden" value="' . $calendar['id'][1] . '">';
			$list .= '<input name="sharee" type="text"><br>';
			$list .= '<select name="access">';
			$list .= '<option value="3">Read-write</option>';
			$list .= '<option value="2">Read-only</option>';
			$list .= '<option value="4">Revoke access</option>';
			$list .= '</select>';
			$list .= '<input value="Share" type="submit" name="share"><br><br>';
			$list .= '</form>';

		}

		//create calendar
		$form = '<form method="post" action="">';
		$form .= '<input name="{DAV:}displayname" type="text"><br>';
		$form .= '<input value="Create" type="submit" name="create"><br><br>';
		$form .= '</form>';

		return $list . $form;
	}
}
