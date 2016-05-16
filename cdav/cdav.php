<?php

/**
 * Name: CalDAV,CardDAV server
 * Description: CalDAV and CardDAV sync server (experimental, unsupported)
 * Version: 1.0
 * Author: Mike Macgirvin <mike@macgirvin.com>
 * 
 */

function cdav_install() {
	if(ACTIVE_DBTYPE === DBTYPE_POSTGRES) {
		$type='postgres';
	}
	else {
		$type = 'mysql';
	}

	$str = file_get_contents('addon/cdav/' . $type . '.sql');
    $arr = explode(';',$str);
    foreach($arr as $a) {
        if(strlen(trim($a))) {
            $r = @$db->q(trim($a));
            if(! $r) {
                $errors .=  t('Errors encountered creating database tables.') . $a . EOL;
            }
        }
    }
}


function cdav_uninstall() {

}


function cdav_module() {}

function cdav_init(&$a) {
	global $db;
	if($db && $db->connected)
		$pdovars = $db->pdo_get();
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
$baseUri = '/cdav';

/**
 * Database
 *
 * Feel free to switch this to MySQL, it will definitely be better for higher
 * concurrency.
 */
$pdo = new \PDO($pdovars[0],$pdovars[1],$pdovars[2]);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/**
 * Mapping PHP errors to exceptions.
 *
 * While this is not strictly needed, it makes a lot of sense to do so. If an
 * E_NOTICE or anything appears in your code, this allows SabreDAV to intercept
 * the issue and send a proper response back to the client (HTTP/1.1 500).
 */
function exception_error_handler($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}
set_error_handler("exception_error_handler");

// Autoloader
require_once 'vendor/autoload.php';

/**
 * The backends. Yes we do really need all of them.
 *
 * This allows any developer to subclass just any of them and hook into their
 * own backend systems.
 */

$auth = new \Zotlabs\Storage\BasicAuth();

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
if (isset($baseUri)) $server->setBaseUri($baseUri);

// Plugins
$server->addPlugin(new \Sabre\DAV\Auth\Plugin($auth));
$server->addPlugin(new \Sabre\DAV\Browser\Plugin());
$server->addPlugin(new \Sabre\CalDAV\Plugin());
$server->addPlugin(new \Sabre\CardDAV\Plugin());
$server->addPlugin(new \Sabre\DAVACL\Plugin());
$server->addPlugin(new \Sabre\DAV\Sync\Plugin());

// And off we go!
$server->exec();









}