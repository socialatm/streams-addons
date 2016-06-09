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

		//TODO: we should probably also check for permission to send stream here
		$local_channels = q("SELECT * FROM channel LEFT JOIN abook ON abook_xchan = channel_hash WHERE channel_system = 0 AND channel_removed = 0 AND channel_hash != '%s' AND abook_channel = %d",
			dbesc($channel['channel_hash']),
			intval($channel['channel_id'])
		);

		$sharee_options .= '<option value="">' . t('Select Channel') . '</option>' . "\r\n";
		foreach($local_channels as $local_channel) {
			$sharee_options .= '<option value="' . $local_channel['channel_hash'] . '">' . $local_channel['channel_name'] . '</option>' . "\r\n";
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
			$share_displayname = array();
			foreach($invites as $invite) {
				if(strpos($invite->href, 'mailto:') !== false) {
					$sharee = channelx_by_hash(substr($invite->href, 7));
					$sharees[] = $sharee['channel_name'] . (($invite->access == 3) ? ' (RW)' : ' (R)');
					$share_displayname[] = $invite->properties['{DAV:}displayname'];
				}
			}
			if(!$access) {
				$my_calendars[] = array(
					'displayname' => $sabrecal['{DAV:}displayname'],
					'calendarid' => $sabrecal['id'][0],
					'instanceid' => $sabrecal['id'][1],
					'sharees' => $sharees
				);
			}
			else {
				$shared_calendars[] = array(
					'share_displayname' => $share_displayname[0],
					'calendarid' => $sabrecal['id'][0],
					'instanceid' => $sabrecal['id'][1],
					'access' => $access

				);
			}
		}

		$o .= replace_macros(get_markup_template('cdav_widget_calendar.tpl', 'addon/cdav'), array(
			'$my_calendars_label' => t('My Calendars'),
			'$my_calendars' => $my_calendars,
			'$shared_calendars_label' => t('Shared Calendars'),
			'$shared_calendars' => $shared_calendars,
			'$sharee_options' => $sharee_options,
			'$access_options' => $access_options,
			'$create_label' => t('Create new calendar'),
			'$create_placeholder' => t('Calendar Name')
		));

		return $o;

	}

}
