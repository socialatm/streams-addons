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

	//print_r($calendars); killme();

	//list calendars
	if(argc() == 3 && argv(2) === 'caldav') {

		foreach($calendars as $calendar) {
			$perms = '(OWNER)';
			if($calendar['share-access'] == 2)
				$perms = '(R)';
			if($calendar['share-access'] == 3)
				$perms = '(RW)';

			$list .= $calendar['{DAV:}displayname'] . ' ' . $perms . ' <a href="/cdav/display/caldav/drop/' . $calendar['id'][0] . '">Delete</a><br>';

		}

		$form = '<form method="post" action="">';
		$form .= '<input name="{DAV:}displayname" type="text"><br>';
		$form .= '<input value="Create" type="submit" name="create"><br><br>';
		$form .= '</form>';

		return $list . $form;
	}
}
