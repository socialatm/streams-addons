<?php

use Zotlabs\Extend\Route;
use Zotlabs\Extend\Hook;
use Zotlabs\Lib\Channel;
    
require_once('addon/faces/FacesPortability.php');
require_once('addon/faces/FacesPermission.php');
require_once('addon/faces/FacesStatistics.php');

/**
 * Name: Faces
 * Description: Detect faces in images and make a guess who it is.
 * Version: 1.16 beta
 * Author: Tom Wiedenhöft ( channel: https://z.digitalesparadies.de/channel/faces )
 * Maintainer: Tom Wiedenhöft ( channel: https://z.digitalesparadies.de/channel/faces )
 *
 */
function faces_load() {
	Route::register('addon/faces/Mod_Faces.php', 'faces');
	Hook::register('identity_basic_export', 'addon/faces/faces.php', 'export');
	Hook::register('import_channel', 'addon/faces/faces.php', 'import');
	Hook::register('process_channel_sync_delivery', 'addon/faces/faces.php', 'import');
	Hook::register('perm_is_allowed', 'addon/faces/faces.php', 'faces_perm_is_allowed');
	faces_create_database_tables();
}

function faces_unload() {
	Route::unregister('addon/faces/Mod_Faces.php', 'faces');
	Hook::unregister('identity_basic_export', 'addon/faces/faces.php', 'export');
	Hook::unregister('import_channel', 'addon/faces/faces.php', 'import');
	Hook::unregister('process_channel_sync_delivery', 'addon/faces/faces.php', 'import');
	Hook::unregister('perm_is_allowed', 'addon/faces/faces.php', 'faces_perm_is_allowed');
}

function faces_perm_is_allowed(&$a) {
	if ($a['permission'] === 'view_faces') {
		$a['result'] = check_faces_view_permission($a);
	}
	if ($a['permission'] === 'write_faces') {
		$a['result'] = check_faces_write_permission($a);
	}
}

function export(&$a) {
	$encs = Zotlabs\Module\attach_face_encodings_export_data($a['channel_id']);
	if ($encs) {
		$a['data']['faces_encoding'] = $encs;
	}
	$names = [];
	$names = \Zotlabs\Module\attach_face_names_export_data($a['channel_id']);
	if ($names) {
		$a['data']['faces_person'] = $names;
	}
}

function import(&$a) {
	Zotlabs\Module\import_faces_all($a);
}

function faces_plugin_admin(&$a, &$o) {

	$pythoncheckmsg = "";
	$finder1msg = "";
	$finder1checked = 0;
	$finder2msg = "";
	$finder2checked = 0;
	$exiftoolmsg = "";
	$exiftoolchecked = 0;

	$ret = testPythonVersion();
	if (!$ret['status']) {
		$pythoncheckmsg = "<p style=\"color:red;\">" . $ret['message'] . "</p>";
	} else {
		$pythoncheckmsg = "<p style=\"color:green;\">" . $ret['message'] . "</p>";

		$finder1checked = get_config('faces', 'finder1');
		$ret = testPythonVersionCV2();
		if (!$ret['status']) {
			$finder1checked = 0;
			$finder1msg = "<p style=\"color:red;\">" . $ret['message'] . "</p>";
		} else {
			$finder1msg = "<p style=\"color:green;\">" . $ret['message'] . "</p>";
		}

		$finder2checked = get_config('faces', 'finder2');
		$ret = testPythonVersionFaceRecognition();
		if (!$ret['status']) {
			$finder2checked = 0;
			$finder2msg = "<p style=\"color:red;\">" . $ret['message'] . "</p>";
		} else {
			$finder2msg = "<p style=\"color:green;\">" . $ret['message'] . "</p>";
		}
	}

	$exiftoolchecked = get_config('faces', 'exiftool');
	$ret = testExiftool();
	if (!$ret['status']) {
		$exiftoolchecked = 0;
		$exiftoolmsg = "<p style=\"color:red;\">" . $ret['message'] . "</p>";
	} else {
		$exiftoolmsg = "<p style=\"color:green;\">" . $ret['message'] . "</p>";
	}


	$t = get_markup_template("admin.tpl", "addon/faces/");

	$limit = get_config('faces', 'limit');
	if (!$limit) {
		$limit = 100;
	}

	$zoom = get_config('faces', 'zoom');
	if (!$zoom) {
		$zoom = 3;
	}

	$maximages = get_config('faces', 'maximages');
	if (!$maximages) {
		$maximages = 6;
	}
	
	$stats = \Zotlabs\Module\getStatisticsAsHTML();

	$o = replace_macros($t, array(
		'$submit' => t('Submit'),
		'$limit' => array('limit', 'Number of Images to dectect per Loop (Face Detection Scripts)', $limit, 'Number of images per detection loop (default = 100)'),
		'$finder1' => array('finder1', "Finder 1", $finder1checked, "opencv , dnn, sklearn"),
		'$finder1msg' => $finder1msg,
		'$finder1config' => array('finder1config', "Finder 1 - Configuration", get_config('faces', 'finder1config'), "Leave empty or overwrite the defaults"),
		'$finder2' => array('finder2', "Finder 2", $finder2checked, "face_recognition"),
		'$finder2msg' => $finder2msg,
		'$finder2config' => array('finder2config', "Finder 2 - Configuration", get_config('faces', 'finder2config'), "Leave empty or overwrite the defaults"),
		'$exiftool' => array('exiftool', "Write Names into Images", $exiftoolchecked, "Exiftool"),
		'$exiftoolmsg' => $exiftoolmsg,
		'$pythoncheckmsg' => $pythoncheckmsg,
		'$deletetables' => array('deletetables', "Delete Faces and Names of all Users", false, "Delete all rows in db tables this addon created"),
		'$zoom' => array('zoom', 'Zoom - Start Value', $zoom, 'Number of Images displayed in a Row (allowed values 1 - 6)'),
		'$maximages' => array('maximages', 'Number of Images the Browser loads at once (autoload)', $maximages, 'Allowed values: 2 - 20, default = 6)'),
		'$facesstatistics' => $stats,
	));
}

function faces_plugin_admin_post(&$a) {
	$limit = ((x($_POST, 'limit')) ? intval(trim($_POST['limit'])) : 100);
	$finder1 = ((x($_POST, 'finder1')) ? true : false);
	$finder1config = ((x($_POST, 'finder1config')) ? notags(trim($_POST['finder1config'])) : '');
	$finder2 = ((x($_POST, 'finder2')) ? true : false);
	$finder2config = ((x($_POST, 'finder2config')) ? notags(trim($_POST['finder2config'])) : '');
	$exiftool = ((x($_POST, 'exiftool')) ? true : false);
	$deletetables = ((x($_POST, 'deletetables')) ? true : false);
	$zoom = ((x($_POST, 'zoom')) ? intval(trim($_POST['zoom'])) : 3);
	$maximages = ((x($_POST, 'maximages')) ? intval(trim($_POST['maximages'])) : 6);


	$ret = testPythonVersion();
	if (!$ret['status']) {
		$finder1 = 0;
		$finder2 = 0;
	} else {
		// check finders just in case the admin is not knowing what he is doing
		$ret = testPythonVersionCV2();
		if (!$ret['status']) {
			$finder1 = 0;
		}
		$ret = testPythonVersionFaceRecognition();
		if (!$ret['status']) {
			$finder2 = 0;
		}
	}
	$ret = testExiftool();
	if (!$ret['status']) {
		$exiftool = 0;
	}


	if ($limit < 10) {
		$limit = 100;
	}
	if ($limit > 10000) {
		$limit = 10000;
	}

	if ($zoom > 6) {
		$zoom = 6;
	} else if ($zoom < 1) {
		$zoom = 1;
	}

	if ($maximages > 20) {
		$maximages = 20;
	} else if ($maximages < 2) {
		$maximages = 2;
	}

	set_config('faces', 'limit', $limit);
	set_config('faces', 'finder1', $finder1);
	set_config('faces', 'finder1config', preg_replace('/\s+/', '', $finder1config));
	set_config('faces', 'finder2', $finder2);
	set_config('faces', 'finder2config', preg_replace('/\s+/', '', $finder2config));
	set_config('faces', 'exiftool', $exiftool);
	set_config('faces', 'zoom', $zoom);
	set_config('faces', 'maximages', $maximages);

	if ($deletetables) {
		faces_drop_database_tables();
	}

	info(t('Settings updated.') . EOL);
}

function faces_create_database_tables() {
	$str = file_get_contents('addon/faces/faces_schema_mysql.sql');
	$arr = explode(';', $str);
	$errors = false;
	foreach ($arr as $a) {
		if (strlen(trim($a))) {
			$r = q(trim($a));
			if (!$r) {
				$errors .= t('Errors encountered creating database tables.') . $a . EOL;
			}
		}
	}
	if ($errors) {
		notice('Error creating the database tables');
		logger('Error creating the database tables: ' . $errors, LOGGER_DEBUG);
	} else {
		info('Installation successful');
		logger('Database tables installed successfully', LOGGER_NORMAL);
	}
}

function faces_drop_database_tables() {
	$errors = false;
	foreach (array('faces_encoding', 'faces_person', 'faces_proc') as $table) {
		$r = q("delete from %s;", dbesc($table));
		if (!$r) {
			$errors .= t('Errors encountered deleting all rows of database table ' . $table . '.') . EOL;
		}
	}
	if ($errors) {
		notice('Errors encountered deleting faces database tables.');
		logger('Errors encountered deleting faces database tables: ' . $errors, LOGGER_DEBUG);
	} else {
		info('Database tables deleted for addon faces.');
		logger('Database tables deleted for addon faces.', LOGGER_NORMAL);
	}
}

function testPythonVersionCV2() {
	$cmd = 'python3 -c "import cv2; print(cv2.__version__)"';
	exec($cmd, $o);
	$ret_string = "";
	if ($o[0]) {
		$ret_string = trim($o[0]);
		logger("CV2 version: " . $ret_string, LOGGER_DEBUG);
		$main_revision = substr($ret_string, 0, 1);
		$main_revision_number = intval($main_revision);
		if ($main_revision_number < 4) {
			return array('status' => false, 'message' => 'Failed: CV2 version < 4 (found' . $ret_string . ')', LOGGER_DEBUG);
		}
	} else {
		return array('status' => false, 'message' => 'Failed: Could not load python module cv2', LOGGER_DEBUG);
	}
	$o = [];
	$cmd = 'python3 -c "import sklearn; print(sklearn.__version__)"';
	exec($cmd, $o);
	if (!$o[0]) {
		logger("python module sklearn was not found", LOGGER_DEBUG);
		return array('status' => false, 'message' => 'Failed: Could not load python module sklearn');
	} else {
		logger("sklearn version=" . $o[0]);
	}
	// test if the files are available
	$o = [];
	$cmd = "python3 " . getcwd() . "/addon/faces/py/availability.py";
	exec($cmd, $o);
	$line = "";
	foreach ($o as $line) {
		logger($line, LOGGER_DEBUG);
	}
	if (trim($line) != "ok") {
		return array('status' => false, 'message' => 'Failed: Finder 1 could not find the ressources ', LOGGER_DEBUG);
	}
	return array('status' => true, 'message' => 'Result self check: found version = ' . $ret_string . LOGGER_NORMAL);
}

function testPythonVersionFaceRecognition() {
	$cmd = 'python3 -c "import face_recognition; print(face_recognition.__version__)"';
	exec($cmd, $o);
	if (!$o[0]) {
		return array('status' => false, 'message' => 'Failed: Could not load python module face_recognition');
	}
	logger("face_recognition version: " . $o[0], LOGGER_NORMAL);
	return array('status' => true, 'message' => 'Result self check: found version = ' . $o[0]);
}

function testPythonVersion() {
	$cmd = 'python3 -c "import platform;print(platform.python_version())"';
	exec($cmd, $o);
	if ($o[0]) {
		$ret_string = trim($o[0]);
		logger("python version: " . $ret_string, LOGGER_DEBUG);
		$version = substr($ret_string, 0, 3);
		$version_number = (float) $version;
		if ($version_number < 3.4) {
			return array('status' => false, 'message' => 'Failed: python version < 3.4 (found ' . $ret_string . ')', LOGGER_DEBUG);
		}
	} else {
		return array('status' => false, 'message' => 'Failed: python3 not found', LOGGER_DEBUG);
	}
	return array('status' => true, 'message' => 'Result self check: found  python version = ' . $o[0], LOGGER_NORMAL);
}

function testExiftool() {
	$cmd = 'exiftool -ver';
	exec($cmd, $o);
	if (!$o[0]) {
		return array('status' => false, 'message' => 'Failed: Exiftool not found', LOGGER_DEBUG);
	}
	logger("Exiftool version: " . $o[0], LOGGER_DEBUG);
	return array('status' => true, 'message' => 'Result self check: found  exiftool version = ' . $o[0], LOGGER_NORMAL);
}

function getFacesStatisticsAsHtml() {
	$html = \Zotlabs\Module\getStatisticsAsHTML();
}
