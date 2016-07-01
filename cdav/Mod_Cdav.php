<?php

namespace Zotlabs\Module;

class Cdav extends \Zotlabs\Web\Controller {

	function init() {
	
		if((argv(1) !== 'calendar') && (argv(1) !== 'addressbook')) {

			if(\DBA::$dba && \DBA::$dba->connected)
				$pdovars = \DBA::$dba->pdo_get();
			else
				killme();

			// workaround for HTTP-auth in CGI mode
			if (x($_SERVER, 'REDIRECT_REMOTE_USER')) {
				$userpass = base64_decode(substr($_SERVER["REDIRECT_REMOTE_USER"], 6)) ;
				if(strlen($userpass)) {
					list($name, $password) = explode(':', $userpass);
					$_SERVER['PHP_AUTH_USER'] = $name;
					$_SERVER['PHP_AUTH_PW'] = $password;
				}
			}

			if (x($_SERVER, 'HTTP_AUTHORIZATION')) {
				$userpass = base64_decode(substr($_SERVER["HTTP_AUTHORIZATION"], 6)) ;
				if(strlen($userpass)) {
					list($name, $password) = explode(':', $userpass);
					$_SERVER['PHP_AUTH_USER'] = $name;
					$_SERVER['PHP_AUTH_PW'] = $password;
				}
			}

			/**
			 * This server combines both CardDAV and CalDAV functionality into a single
			 * server. It is assumed that the server runs at the root of a HTTP domain (be
			 * that a domainname-based vhost or a specific TCP port.
			 *
			 * This example also assumes that you're using SQLite and the database has
			 * already been setup (along with the database tables).
			 *
			 * You may choose to use MySQL instead, just change the PDO connection
			 * statement.
			 */

			/**
			 * UTC or GMT is easy to work with, and usually recommended for any
			 * application.
			 */
			date_default_timezone_set('UTC');

			/**
			 * Make sure this setting is turned on and reflect the root url for your WebDAV
			 * server.
			 *
			 * This can be for example the root / or a complete path to your server script.
			 */

			$baseUri = '/cdav/';

			/**
			 * Database
			 *
			 */

			$pdo = new \PDO($pdovars[0],$pdovars[1],$pdovars[2]);
			$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);


			// Autoloader
			require_once 'vendor/autoload.php';

			/**
			 * The backends. Yes we do really need all of them.
			 *
			 * This allows any developer to subclass just any of them and hook into their
			 * own backend systems.
			 */

			$auth = new \Zotlabs\Storage\BasicAuth();
			$auth->setRealm(ucfirst(\Zotlabs\Lib\System::get_platform_name()) . 'CalDAV/CardDAV');

		//	$ob_hash = get_observer_hash();

		//	if ($ob_hash) {
				if (local_channel()) {
					logger('loggedin');
					$channel = \App::get_channel();
					$auth->setCurrentUser($channel['channel_address']);
					$auth->channel_id = $channel['channel_id'];
					$auth->channel_hash = $channel['channel_hash'];
					$auth->channel_account_id = $channel['channel_account_id'];
					if($channel['channel_timezone'])
						$auth->setTimezone($channel['channel_timezone']);
					$auth->observer = $channel['channel_hash'];
				}
		//		$auth->observer = $ob_hash;
		//	}

			//$authBackend      = new \Sabre\DAV\Auth\Backend\PDO($pdo);
			$principalBackend = new \Sabre\DAVACL\PrincipalBackend\PDO($pdo);
			$carddavBackend   = new \Sabre\CardDAV\Backend\PDO($pdo);
			$caldavBackend    = new \Sabre\CalDAV\Backend\PDO($pdo);

			/**
			 * The directory tree
			 *
			 * Basically this is an array which contains the 'top-level' directories in the
			 * WebDAV server.
			 */

			$nodes = [
				// /principals
				new \Sabre\CalDAV\Principal\Collection($principalBackend),
				// /calendars
				new \Sabre\CalDAV\CalendarRoot($principalBackend, $caldavBackend),
				// /addressbook
				new \Sabre\CardDAV\AddressBookRoot($principalBackend, $carddavBackend),
			];

			// The object tree needs in turn to be passed to the server class

			$server = new \Sabre\DAV\Server($nodes);

			if(isset($baseUri))
				$server->setBaseUri($baseUri);

			// Plugins
			$server->addPlugin(new \Sabre\DAV\Auth\Plugin($auth));
			$server->addPlugin(new \Sabre\DAV\Browser\Plugin());
			$server->addPlugin(new \Sabre\DAV\Sync\Plugin());
			$server->addPlugin(new \Sabre\DAV\Sharing\Plugin());
			$server->addPlugin(new \Sabre\DAVACL\Plugin());

			// CalDAV plugins
			$server->addPlugin(new \Sabre\CalDAV\Plugin());
			$server->addPlugin(new \Sabre\CalDAV\SharingPlugin());
			//$server->addPlugin(new \Sabre\CalDAV\Schedule\Plugin());
			$server->addPlugin(new \Sabre\CalDAV\ICSExportPlugin());

			// CardDAV plugins
			$server->addPlugin(new \Sabre\CardDAV\Plugin());
			$server->addPlugin(new \Sabre\CardDAV\VCFExportPlugin());

			// And off we go!
			$server->exec();

			killme();

		}

	}

	function post() {
		if(!local_channel() || get_pconfig(local_channel(),'cdav','enabled') != 1)
			return;


		$channel = \App::get_channel();
		$principalUri = 'principals/' . $channel['channel_address'];

		if(!cdav_principal($principalUri))
			return;

		if(\DBA::$dba && \DBA::$dba->connected)
			$pdovars = \DBA::$dba->pdo_get();
		else
			killme();

		$pdo = new \PDO($pdovars[0],$pdovars[1],$pdovars[2]);
		$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

		require_once 'vendor/autoload.php';

		if(argc() == 2 && argv(1) === 'calendar') {

			$caldavBackend = new \Sabre\CalDAV\Backend\PDO($pdo);

			//create new calendar
			if($_REQUEST['{DAV:}displayname'] && $_REQUEST['create']) {
				do {
					$duplicate = false;
					$calendarUri = random_string(40);

					$r = q("SELECT uri FROM calendarinstances WHERE principaluri = '%s' AND uri = '%s' LIMIT 1",
						dbesc($principalUri),
						dbesc($calendarUri)
					);

					if (count($r))
						$duplicate = true;
				} while ($duplicate == true);

				$properties = [
					'{DAV:}displayname' => dbesc($_REQUEST['{DAV:}displayname']),
					'{http://apple.com/ns/ical/}calendar-color' => dbesc($_REQUEST['color']),
				];

				$id = $caldavBackend->createCalendar($principalUri, $calendarUri, $properties);

				// set new calendar to be visible
				set_pconfig(local_channel(), 'cdav_calendar' , $id[0], 1);
			}

			//edit calendar
			if($_REQUEST['{DAV:}displayname'] && $_REQUEST['edit'] && $_REQUEST['id']) {

				$id = explode(':', $_REQUEST['id']);

				$mutations = [
					'{DAV:}displayname' => dbesc($_REQUEST['{DAV:}displayname']),
					'{http://apple.com/ns/ical/}calendar-color' => dbesc($_REQUEST['color'])
				];

				$patch = new \Sabre\DAV\PropPatch($mutations);

				$caldavBackend->updateCalendar($id, $patch);

				$patch->commit();

			}

			//share a calendar - this only works on local system (with channels on the same server)
			if($_REQUEST['sharee'] && $_REQUEST['share']) {

				$id = [intval($_REQUEST['calendarid']), intval($_REQUEST['instanceid'])];

				$hash = $_REQUEST['sharee'];

				$sharee_arr = channelx_by_hash($hash);

				$sharee = new \Sabre\DAV\Xml\Element\Sharee();

				$sharee->href = 'mailto:' . $sharee_arr['channel_hash'];
				$sharee->principal = 'principals/' . $sharee_arr['channel_address'];
				$sharee->access = intval($_REQUEST['access']);
				if($_REQUEST['{DAV:}displayname'])
					$sharee->properties = ['{DAV:}displayname' => dbesc($_REQUEST['{DAV:}displayname']) . ' (' . $channel['channel_name'] . ')'];

				$caldavBackend->updateInvites($id, [$sharee]);
			}
		}

		if(argc() >= 2 && argv(1) === 'addressbook') {

			$carddavBackend = new \Sabre\CardDAV\Backend\PDO($pdo);

			//create new addressbook
			if($_REQUEST['{DAV:}displayname'] && $_REQUEST['create']) {
				do {
					$duplicate = false;
					$addressbookUri = random_string(20);

					$r = q("SELECT uri FROM addressbooks WHERE principaluri = '%s' AND uri = '%s' LIMIT 1",
						dbesc($principalUri),
						dbesc($addressbookUri)
					);

					if (count($r))
						$duplicate = true;
				} while ($duplicate == true);

				$properties = ['{DAV:}displayname' => dbesc($_REQUEST['{DAV:}displayname'])];

				$carddavBackend->createAddressBook($principalUri, $addressbookUri, $properties);
			}

			//edit addressbook
			if($_REQUEST['{DAV:}displayname'] && $_REQUEST['edit'] && intval($_REQUEST['id'])) {

				$id = $_REQUEST['id'];

				$mutations = [
					'{DAV:}displayname' => dbesc($_REQUEST['{DAV:}displayname'])
				];

				$patch = new \Sabre\DAV\PropPatch($mutations);

				$carddavBackend->updateAddressBook($id, $patch);

				$patch->commit();

			}
		}

		//Import calendar or addressbook
		if(($_FILES) && array_key_exists('userfile',$_FILES) && intval($_FILES['userfile']['size']) && $_REQUEST['target']) {

			$src = @file_get_contents($_FILES['userfile']['tmp_name']);

			if($src) {

				if($_REQUEST['c_upload']) {
					$id = explode(':', $_REQUEST['target']);
					$ext = 'ics';
					$table = 'calendarobjects';
					$column = 'calendarid';
					$objects = new \Sabre\VObject\Splitter\ICalendar($src);
					$profile = \Sabre\VObject\Node::PROFILE_CALDAV;
					$backend = new \Sabre\CalDAV\Backend\PDO($pdo);
				}

				if($_REQUEST['a_upload']) {
					$id[] = intval($_REQUEST['target']);
					$ext = 'vcf';
					$table = 'cards';
					$column = 'addressbookid';
					$objects = new \Sabre\VObject\Splitter\VCard($src);
					$profile = \Sabre\VObject\Node::PROFILE_CARDDAV;
					$backend = new \Sabre\CardDAV\Backend\PDO($pdo);
				}

				while ($object = $objects->getNext()) {
					$ret = $object->validate($profile & \Sabre\VObject\Node::REPAIR);

					//level 3 Means that the document is invalid,
					//level 2 means a warning. A warning means it's valid but it could cause interopability issues,
					//level 1 means that there was a problem earlier, but the problem was automatically repaired.

					if($ret[0]['level'] < 3) {
						do {
							$duplicate = false;
							$objectUri = random_string(40) . '.' . $extV ;

							$r = q("SELECT uri FROM $table WHERE $column = %d AND uri = '%s' LIMIT 1",
								dbesc($id[0]),
								dbesc($objectUri)
							);

							if (count($r))
								$duplicate = true;
						} while ($duplicate == true);

						if($_REQUEST['c_upload']) {
							$backend->createCalendarObject($id, $objectUri, $object->serialize());
						}

						if($_REQUEST['a_upload']) {
							$backend->createCard($id[0], $objectUri, $object->serialize());
						}
					}
					else {
						if($_REQUEST['c_upload']) {
							notice( '<strong>' . t('INVALID EVENT DISMISSED!') . '</strong>' . EOL .
								'<strong>' . t('Summary: ') . '</strong>' . (($object->VEVENT->SUMMARY) ? $object->VEVENT->SUMMARY : t('Unknown')) . EOL .
								'<strong>' . t('Date: ') . '</strong>' . (($object->VEVENT->DTSTART) ? $object->VEVENT->DTSTART : t('Unknown')) . EOL .
								'<strong>' . t('Reason: ') . '</strong>' . $ret[0]['message'] . EOL
							);
						}

						if($_REQUEST['a_upload']) {
							notice( '<strong>' . t('INVALID CARD DISMISSED!') . '</strong>' . EOL .
								'<strong>' . t('Name: ') . '</strong>' . (($object->FN) ? $object->FN : t('Unknown')) . EOL .
								'<strong>' . t('Reason: ') . '</strong>' . $ret[0]['message'] . EOL
							);
						}
					}
				}
			}
			@unlink($src);
		}
	}

	function get() {

		if(!local_channel() || get_pconfig(local_channel(),'cdav','enabled') != 1)
			return;

		$channel = \App::get_channel();
		$principalUri = 'principals/' . $channel['channel_address'];

		if(!cdav_principal($principalUri))
			return;

		if(\DBA::$dba && \DBA::$dba->connected)
			$pdovars = \DBA::$dba->pdo_get();
		else
			killme();

		$pdo = new \PDO($pdovars[0],$pdovars[1],$pdovars[2]);
		$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

		require_once 'vendor/autoload.php';

		head_add_css('addon/cdav/view/css/cdav.css');

		if(argv(1) === 'calendar') {
			$caldavBackend = new \Sabre\CalDAV\Backend\PDO($pdo);
			$calendars = $caldavBackend->getCalendarsForUser($principalUri);
		}

		//Display calendar(s) here
		if(argc() == 2 && argv(1) === 'calendar') {

			head_add_css('library/fullcalendar/fullcalendar.css');
			head_add_css('addon/cdav/view/css/cdav_calendar.css');

			//TODO: issue #411 https://github.com/redmatrix/hubzilla/issues/411 js is included in template for now...
			//head_add_js('library/moment/moment.min.js');
			//head_add_js('library/fullcalendar/fullcalendar.min.js');
			//head_add_js('library/fullcalendar/lang-all.js');

			foreach($calendars as $calendar) {
				$color = (($calendar['{http://apple.com/ns/ical/}calendar-color']) ? $calendar['{http://apple.com/ns/ical/}calendar-color'] : '#3a87ad');
				$switch = get_pconfig(local_channel(), 'cdav_calendar', $calendar['id'][0]);
				if($switch) {
					$sources .= '{ url: \'/cdav/calendar/json/' . $calendar['id'][0] . '/' . $calendar['id'][1] . '\', color: \'' . $color . '\' }, ';
				}
			}

			$sources = rtrim($sources, ', ');

			$first_day = get_pconfig(local_channel(),'system','cal_first_day');
			$first_day = (($first_day) ? $first_day : 0);

			$o .= replace_macros(get_markup_template('cdav_calendar.tpl', 'addon/cdav'), [
				'$sources' => $sources,
				'$color' => $color,
				'$lang' => \App::$language,
				'$first_day' => $first_day,
				'$prev'	=> t('Previous'),
				'$next'	=> t('Next'),
				'$today' => t('Today')
			]);

			return $o;

		}

		//Provide json data for calendar
		if(argc() == 5 && argv(1) === 'calendar' && argv(2) === 'json'  && intval(argv(3)) && intval(argv(4))) {

			$id = [argv(3), argv(4)];

			if (x($_GET,'start'))
				$start = $_GET['start'];
			if (x($_GET,'end'))
				$end = $_GET['end'];

			$filters['name'] = 'VCALENDAR';
			$filters['prop-filters'][0]['name'] = 'VEVENT';
			$filters['comp-filters'][0]['name'] = 'VEVENT';
			$filters['comp-filters'][0]['time-range']['start'] = date_create($start);
			$filters['comp-filters'][0]['time-range']['end'] = date_create($end);

			foreach($calendars as $calendar) {
				if($id[0] == $calendar['id'][0]) {
					$uris = $caldavBackend->calendarQuery($id, $filters);
					if($uris) {

						$objects = $caldavBackend->getMultipleCalendarObjects($id, $uris);

						foreach($objects as $object) {

							$vcalendar = \Sabre\VObject\Reader::read($object['calendardata']);

							$events[] = [
								'title' => (string)$vcalendar->VEVENT->SUMMARY,
								'start' => (string)$vcalendar->VEVENT->DTSTART,
								'end' => (string)$vcalendar->VEVENT->DTEND,
								'allDay' => ((strpos((string)$vcalendar->VEVENT->DTSTART, 'T') && strpos((string)$vcalendar->VEVENT->DTEND, 'T')) ? false : true)
							];
						}

						json_return_and_die($events);
					}
					else {

						killme();
					}
				}
			}

		}

		//enable/disable calendars
		if(argc() == 5 && argv(1) === 'calendar' && argv(2) === 'switch'  && intval(argv(3)) && (argv(4) == 1 || argv(4) == 0)) {
			$id = argv(3);
			foreach($calendars as $calendar) {
				if($id == $calendar['id'][0]) {
					set_pconfig(local_channel(), 'cdav_calendar' , argv(3), argv(4));
					killme();
				}
			}
		}

		//drop calendar
		if(argc() == 4 && argv(1) === 'calendar' && argv(2) === 'drop' && intval(argv(3))) {
			$id = argv(3);
			foreach($calendars as $calendar) {
				if($id == $calendar['id'][0]) {
					$caldavBackend->deleteCalendar($calendar['id']);
					killme();
				}
			}
		}

		//drop sharee
		if(argc() == 6 && argv(1) === 'calendar' && argv(2) === 'dropsharee'  && intval(argv(3)) && intval(argv(4))) {

			$id = [argv(3), argv(4)];
			$hash = argv(5);

			foreach($calendars as $calendar) {
				if($id[0] == $calendar['id'][0]) {
					$sharee_arr = channelx_by_hash($hash);

					$sharee = new \Sabre\DAV\Xml\Element\Sharee();

					$sharee->href = 'mailto:' . $sharee_arr['channel_hash'];
					$sharee->principal = 'principals/' . $sharee_arr['channel_address'];
					$sharee->access = 4;
					$caldavBackend->updateInvites($id, [$sharee]);

					killme();
				}
			}
		}


		if(argv(1) === 'addressbook') {
			$carddavBackend = new \Sabre\CardDAV\Backend\PDO($pdo);
			$addressbooks = $carddavBackend->getAddressBooksForUser($principalUri);
		}

		//Display Adressbook here
		if(argc() == 3 && argv(1) === 'addressbook' && intval(argv(2))) {

			$o = '';
			$id = argv(2);

			foreach($addressbooks as $addressbook) {
				if($id == $addressbook['id']) {
					$displayname = $addressbook['{DAV:}displayname'];
					$sabrecards = $carddavBackend->getCards($addressbook['id']);
					foreach($sabrecards as $sabrecard) {
						$uris[] = $sabrecard['uri'];
					}
				}
			}

			if($uris) {
				$objects = $carddavBackend->getMultipleCards($id, $uris);

				foreach($objects as $object) {
					$vcard = \Sabre\VObject\Reader::read($object['carddata']);

					$tels = [];
					$emails = [];

					if($vcard->TEL) {
						foreach($vcard->TEL as $tel) {
							$type = (($vcard->TEL['TYPE']) ? translate_type((string)$vcard->TEL['TYPE']) : '');
							$tels[] = [
								'type' => $type,
								'nr' => (string)$tel
							];
						}
					}

					if($vcard->EMAIL) {
						foreach($vcard->EMAIL as $email) {
							$type = (($vcard->EMAIL['TYPE']) ? translate_type((string)$vcard->EMAIL['TYPE']) : '');
							$emails[] = [
								'type' => $type,
								'address' => (string)$email
							];
						}
					}

					if($vcard->ADR) {
						foreach($vcard->ADR as $adr) {
							$adr = $adr->getParts();
						}
					}

					$cards[] = [
						'fn' => (string)$vcard->FN,
						'tels' => $tels,
						'emails' => $emails,
						'adr' => $adr
					];
				}

				usort($cards, function($a, $b) { return strcmp($a['fn'], $b['fn']); });

				$o .= replace_macros(get_markup_template('cdav_addressbook.tpl', 'addon/cdav'), [
					'$cards' => $cards,
					'$displayname' => $displayname
				]);

			}

			return $o;
		}

		//delete addressbook
		if(argc() > 3 && argv(1) === 'addressbook' && argv(2) === 'drop' && intval(argv(3))) {
			$id = argv(3);
			foreach($addressbooks as $addressbook) {
				if($id == $addressbook['id']) {
					$carddavBackend->deleteAddressBook($addressbook['id']);
					killme();
				}
			}
		}

	}

}
