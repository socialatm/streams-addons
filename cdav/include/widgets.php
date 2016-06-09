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

	$o = '';

	$channel = \App::get_channel();

	$principalUri = 'principals/' . $channel['channel_address'];

	if(argc() == 2 && argv(1) === 'calendar') {

		$caldavBackend = new \Sabre\CalDAV\Backend\PDO($pdo);

		$sabrecals = $caldavBackend->getCalendarsForUser($principalUri);

		//print_r($calendars); killme();

		//TODO: we should probably also check for permission to send stream here
		$local_channels = q("SELECT * FROM channel LEFT JOIN abook ON abook_xchan = channel_hash WHERE channel_system = 0 AND channel_removed = 0 AND channel_hash != '%s' AND abook_channel = %d",
			dbesc($channel['channel_hash']),
			intval($channel['channel_id'])
		);

		$sharee_options .= '<option value="">' . t('Select Channel') . '</option>' . "\r\n";
		foreach($local_channels as $local_channel) {
			$sharee_options .= '<option value="' . $local_channel['channel_address'] . '">' . $local_channel['channel_name'] . '</option>' . "\r\n";
		}

		$access_options = '<option value="3">' . t('Read-write') . '</option>' . "\r\n";
		$access_options .= '<option value="2">' . t('Read-only') . '</option>' . "\r\n";
		$access_options .= '<option value="4">' . t('Revoke access') . '</option>' . "\r\n";

		//list calendars
		foreach($sabrecals as $sabrecal) {
			if($sabrecal['share-access'] == 1)
				$access = '';
			if($sabrecal['share-access'] == 2)
				$access = 'read';
			if($sabrecal['share-access'] == 3)
				$access = 'read-write';

			$invites = $caldavBackend->getInvites($sabrecal['id']);

			$sharees = array();

			foreach($invites as $invite) {
				if((strpos($invite->href, 'mailto:') !== false)) {
					$sharee = substr($invite->href, 7);
					if(!$access) //filter the owner
						$sharees[] = $sharee . (($invite->access == 3) ? ' (RW)' : ' (R)');
				}
			}

			$calendars[] = array(
				'displayname' => $sabrecal['{DAV:}displayname'],
				'calendarid' => $sabrecal['id'][0],
				'instanceid' => $sabrecal['id'][1],
				'access' => $access,
				'sharees' => $sharees
			);

		}

		$o .= replace_macros(get_markup_template('cdav_widget_calendar.tpl', 'addon/cdav'), array(
			'$my_calendars_label' => t('My Calendars'),
			'$create_label' => t('Create new calendar'),
			'$create_placeholder' => t('Calendar Name'),
			'$shared_calendars_label' => t('Shared Calendars'),
			'$calendars' => $calendars,
			'$sharee_options' => $sharee_options,
			'$access_options' => $access_options
		));

		return $o;

	}

}
