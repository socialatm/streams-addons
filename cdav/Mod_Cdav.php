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

		if(argc() == 2 && argv(1) === 'calendar') {

			//create new calendar
			if($_REQUEST['{DAV:}displayname'] && $_REQUEST['create']) {
				do {
					$duplicate = false;
					$calendarUri = random_string(20);

					$r = q("SELECT uri FROM calendarinstances WHERE principaluri = '%s' AND uri = '%s' LIMIT 1",
						dbesc($principalUri),
						dbesc($calendarUri)
					);

					if (count($r))
						$duplicate = true;
				} while ($duplicate == true);

				$properties = array('{DAV:}displayname' => dbesc($_REQUEST['{DAV:}displayname']));

				$caldavBackend->createCalendar($principalUri, $calendarUri, $properties);
			}

			//share a calendar - this only works on local system (with channels on the same server)
			if($_REQUEST['sharee'] && $_REQUEST['share']) {
				$id = array(intval($_REQUEST['calendarid']), intval($_REQUEST['instanceid']));

				$hash = dbesc($_REQUEST['sharee']);

				$sharee_arr = channelx_by_hash($hash);

				$sharee = new \Sabre\DAV\Xml\Element\Sharee();

				$sharee->href = 'mailto:' . $hash;
				$sharee->principal = 'principals/' . $sharee_arr['channel_address'];
				$sharee->access = intval($_REQUEST['access']);

				$caldavBackend->updateInvites($id, array($sharee));
			}
		}
	}

	function get() {

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

		if(argc() == 2 && argv(1) === 'calendar') {
			//Display calendar(s) here
			return 'not implemented';
		}

		//delete calendar
		if(argc() > 3 && argv(2) === 'drop' && intval(argv(3))) {
			$id = argv(3);
			foreach($calendars as $calendar) {
				if($id == $calendar['id'][0]) {
					$caldavBackend->deleteCalendar($calendar['id']);
					info( t('Calendar deleted.') . EOL);
				}
			}
			goaway('/cdav/calendar');
		}


		//manage carddav stuff
		if((argc() == 2) && (argv(1) === 'addressbook')) {
			//Display Adressbook here
			return 'not implemented';
		}

	}
}
