<?php

namespace Code\Module;

use App;
use Code\Lib\Apps;
use Code\Web\Controller;
use Code\Lib\Channel;
use Code\Lib\Head;
use Code\Render\Theme;
use Code\Storage\BasicAuth;
use Code\Storage\Directory;
use Code\Storage\File;
use Code\Lib\Libsync;

class Faces extends Controller {

    private $is_owner;
    private $can_write;
    private $owner;
    private $observer;
    private $addonDirName = "faces";
    private $probeDirName = "probe";
    private $fileNameEmbeddings = "faces.gzip";
    private $fileNameFaces = "faces.json";
    private $fileNameNames = "names.json";
    private $fileNameFacesStatistic = "face_statistics.csv";
    private $fileNameModelsStatistic = "model_statistics.csv";
    private $fileNameConfig = "config.json";
    private $fileNameThresholds = "thresholds.json";
    private $fileNameProbe = "probe.csv";
    private $files_faces = [];
    private $files_names = [];

    function init() {

        $this->observer = \App::get_observer();
        $this->checkOwner();
    }

    function get() {

        //----------------------------------------------------------------------
        // permisson checks
        //----------------------------------------------------------------------
        if (!$this->owner) {
            if (local_channel()) { // if no channel name was provided, assume the current logged in channel
                $channel = \App::get_channel();
                logger('No nick but local channel - channel = ' . $channel, LOGGER_DEBUG);
                if ($channel && $channel['channel_address']) {
                    $nick = $channel['channel_address'];
                    goaway(z_root() . '/faces/' . $nick);
                }
            }
        }

        if (!$this->owner) {
            logger('No nick and no local channel', LOGGER_DEBUG);
            notice(t('Profile Unavailable.') . EOL);
            goaway(z_root());
        }

        if (is_null($this->observer)) {
            logger('observer unkown', LOGGER_DEBUG);
            goaway(z_root());
        }

        $status = $this->permChecks();
        if (!$status['status']) {
            logger('observer prohibited', LOGGER_DEBUG);
            notice($status['message'] . EOL);
            goaway(z_root());
        }

        $ret['status'] = true;
        $ret['message'] = "";

        $channel = \App::get_channel();

        // tell the browser about the log level
        $loglevel = -1;
        $logEnabled = get_config('system', 'debugging');
        if ($logEnabled) {
            $loglevel = (get_config('system', 'loglevel') ? get_config('system', 'loglevel') : LOGGER_NORMAL);
        }

        if (argc() > 2) {
            switch (argv(2)) {
                case 'settings':
                    // API: /faces/nick/settings
                    $o = $this->showSettingsPage($loglevel);
                    return $o;
                case 'thresholds':
                    // API: /faces/nick/thresholds
                    $o = $this->showThresholdsPage($loglevel);
                    return $o;
                case 'probe':
                    // API: /faces/nick/probe
                    $o = $this->showProbePage($loglevel);
                    return $o;
                case 'remove':
                    // API: /faces/nick/remove
                    $o = $this->showRemovePage($loglevel);
                    return $o;
                case 'help':
                    // API: /faces/nick/help
                    $o = $this->showHelpPage();
                    return $o;
                default:
                    break;
            }
        }

        //----------------------------------------------------------------------
        // fill some elements in the
        //----------------------------------------------------------------------


        $version = $this->getAppVersion();
        logger("App version is " . $version);

        Head::add_css('/addon/faces/view/css/faces.css');
        $o = replace_macros(Theme::get_template('faces.tpl', 'addon/faces'), array(
            '$version' => $version,
            '$status' => $ret['status'],
            '$message' => $ret['message'],
            '$can_write' => $this->can_write ? 'true' : 'false',
            '$is_owner' => $this->is_owner ? 'true' : 'false',
            '$log_level' => $loglevel,
            '$submit' => t('Submit'),
        ));

        return $o;
    }

    function post() {
        $status = $this->permChecks();

        if (!$status['status']) {
            notice('permission check failed' . EOL);
            logger('sending status=false, permission check failed', LOGGER_NORMAL);
            json_return_and_die(array('status' => false, 'message' => 'permission check failed'));
        }
        if (!$this->observer) {
            logger('sending status=false, Unknown observer. Please login.', LOGGER_NORMAL);
            json_return_and_die(array('status' => false, 'message' => 'Unknown observer. Please login.'));
        }

        if (argc() > 2) {
            $api = argv(2);
            logger('api = ' . $api, LOGGER_DEBUG);
            if ($api === 'start') {
                // API: /faces/nick/start
                $this->startDetection('start', false);
            } elseif ($api === 'recognize') {
                // API: /faces/nick/recognize
                $this->startRecognition();
            } elseif ($api === 'results') {
                // API: /faces/nick/results
                $this->startDetection('results', false);
            } elseif ($api === 'status') {
                // API: /faces/nick/status
                $this->getStatus();
            } elseif ($api === 'name') {
                // API: /faces/nick/name
                $this->setName();
            } elseif ($api === 'config') {
                // API: /faces/nick/config
                $this->sendConfig();
            } elseif ($api === 'settings') {
                // API: /faces/nick/settings
                $this->setConfig();
            } elseif ($api === 'remove') {
                // API: /faces/nick/remove
                $this->remove();
            } elseif ($api === 'rthresholds') {
                // API: /faces/nick/rthresholds
                $this->sendThresholds();
            } elseif ($api === 'thresholds') {
                // API: /faces/nick/thresholds
                $this->setThresholds();
            } elseif ($api === 'probe') {
                // API: /faces/nick/probe
                $this->startProbe();
            }
        }
    }

    private function checkOwner() {
        // Determine which channel's faces to display to the observer
        $nick = null;
        if (argc() > 1) {
            $nick = argv(1); // if the channel name is in the URL, use that
        }
        logger('nick = ' . $nick, LOGGER_DEBUG);

        $this->owner = Channel::from_username($nick);
    }

    private function permChecks() {

        $owner_uid = $this->owner['channel_id'];

        if (!$owner_uid) {
            logger('Stop: No owner profil', LOGGER_DEBUG);
            return array('status' => false, 'message' => 'No owner profil');
        }

        $this->is_owner = ($this->observer['xchan_hash'] && $this->observer['xchan_hash'] == $this->owner['xchan_hash']);
        if ($this->is_owner) {
            logger('observer = owner', LOGGER_DEBUG);
        } else {
            logger('observer != owner', LOGGER_DEBUG);
        }

        if (!Apps::addon_app_installed($owner_uid, 'faces')) {
            logger('Stop: Owner profil has not addon installed', LOGGER_DEBUG);
            return array('status' => false, 'message' => 'Owner profil has not addon installed');
        }

        // Leave this check because the observer needs permissions to view photos too
        if (!perm_is_allowed($owner_uid, get_observer_hash(), 'view_storage')) {
            logger('Stop: Permission view storage denied', LOGGER_DEBUG);
            return array('status' => false, 'message' => 'Permission view storage denied');
        }

        $this->can_write = perm_is_allowed($owner_uid, get_observer_hash(), 'write_storage');
        logger('observer can write: ' . $this->can_write, LOGGER_DEBUG);

        return array('status' => true);
    }

    function getAppVersion() {
        $r = q("SELECT app.app_version FROM app WHERE app.app_name = 'Faces' and app.app_channel = %d", $this->owner['channel_id']);
        if (!$r) {
            return "";
        }
        return $r[0]["app_version"];
    }

    private function startDetection($action, $rm_params) {

        require_once('FaceRecognition.php');
        $fr = new FaceRecognition();

        if (!$rm_params) {
            if ($fr->isScriptRunning() && $action !== 'start') {
                notice('Face detection is still busy' . EOL);
                logger('sending status=true, face detection is still busy', LOGGER_NORMAL);
                json_return_and_die(array('status' => true, 'message' => 'Face detection is still busy'));
            }
        }


        $is_touch = false;
        if ($action === 'results') {
            $is_touch = true;
        }

        $block = (get_config('faces', 'block_python') ? get_config('faces', 'block_python') : false);

        $this->prepareFiles($is_touch);
        $config = $this->getConfig();
        $immediatly = $config["immediatly"][0][1] ? $config["immediatly"][0][1] : false;
        $sort_exif = $config["exif"][0][1] ? $config["exif"][0][1] : false;
        $sort_ascending = $config["ascending"][0][1] ? $config["ascending"][0][1] : false;
        $zoom = $config["zoom"][0][1] ? $config["zoom"][0][1] : 2;

        if ($fr->isScriptRunning() && $action === 'start') {
            // Show the images if the page is reloaded
            logger("sending status=true, faces: " . json_encode($this->files_faces), LOGGER_NORMAL);
            json_return_and_die(array(
                'status' => true,
                'names' => $this->files_faces,
                'names_waiting' => $this->files_names,
                'immediatly' => $immediatly,
                'sort_exif' => $sort_exif,
                'sort_ascending' => $sort_ascending,
                'zoom' => $zoom,
                'python_blocked' => $block,
                'message' => "ok"));
        }

        if (!$block) {
            if ($action === 'start') {
                $storeDirectory = $this->getStoreDir();
                $recognize = false;
                $channel_id = $this->owner['channel_id'];
                $fr->start($storeDirectory, $channel_id, $recognize, $rm_params, "");
            }
            if ($rm_params) {
                return;
            }
        }

        logger("sending status=true, faces: " . json_encode($this->files_faces), LOGGER_NORMAL);

        json_return_and_die(array(
            'status' => true,
            'names' => $this->files_faces,
            'names_waiting' => $this->files_names,
            'immediatly' => $immediatly,
            'sort_exif' => $sort_exif,
            'sort_ascending' => $sort_ascending,
            'zoom' => $zoom,
            'python_blocked' => $block,
            'message' => "ok"));
    }

    private function startRecognition() {
        $block = (get_config('faces', 'block_python') ? get_config('faces', 'block_python') : false);
        if ($block) {
            logger("sending status=ok, python is blocked, face recogntion not started", LOGGER_NORMAL);
            json_return_and_die(array('status' => true, 'message' => "ok, python is blocked, face recogntion not started"));
        }

        $immediatly = (get_config('faces', 'immediatly') ? get_config('faces', 'immediatly') : false);
        if (!$immediatly) {
            logger("sending status=ok, immediate search not activated, face recogntion not started", LOGGER_NORMAL);
            json_return_and_die(array('status' => true, 'message' => "ok, immediate search not activated, face recogntion not started"));
        }

        require_once('FaceRecognition.php');
        $fr = new FaceRecognition();

        $channel_id = $this->owner['channel_id'];
        if ($fr->isScriptRunning($channel_id)) {
            logger("sending status=ok, recognition is still running for this user", LOGGER_NORMAL);
            json_return_and_die(array('status' => true, 'message' => "ok, recognition is still running for this user"));
        }

        $storeDirectory = $this->getStoreDir();
        $recognize = true;
        $rm_params = "";
        $fr->start($storeDirectory, $channel_id, $recognize, $rm_params, "");

        logger("sending status=ok, face recognition started", LOGGER_NORMAL);
        json_return_and_die(array('status' => true, 'message' => "face recognition started"));
    }

    private function startProbe() {
        $block = (get_config('faces', 'block_python') ? get_config('faces', 'block_python') : false);
        if ($block) {
            notice('face recognition (python) is blocked on this server' . EOL);
            return;
        }
        $this->getAddonDir();  // create probe.json (results) if it does not exist

        require_once('FaceRecognition.php');
        $fr = new FaceRecognition();

        $channel_id = $this->owner['channel_id'];
        logger("sending status=ok, face recognition is still busy", LOGGER_NORMAL);
        if ($fr->isScriptRunning($channel_id)) {
            json_return_and_die(array(
                'status' => true,
                'message' => "another face recognition is still busy"));
        }

        $storeDirectory = $this->getStoreDir();
        $recognize = true;
        $rm_params = "";
        $fr->start($storeDirectory, $channel_id, $recognize, $rm_params, "--probe on ");

        logger("probe distance metrics was started", LOGGER_DEBUG);

        notice('probe started' . EOL);
    }

    private function getStoreDir() {
        $storeDirectory = getcwd() . "/store/" . $this->owner['channel_address'];
        return $storeDirectory;
    }

    private function prepareFiles($is_touch = false) {
        $userDir = $this->getUserDir();
        $this->getAddonDir($is_touch);
        $this->checkDataFiles($userDir, $userDir->getName(), $is_touch);
    }

    private function checkDataFiles(Directory $dir, String $path, $is_touch = false) {
        // The pyhton script is allowed to write to existing files only. It will
        // ignore images in directories where the data files are missing.
        $children = $dir->getChildren();
        $check = true;
        foreach ($children as $child) {
            if ($child instanceof File && $check) {
                if ($child->getContentType() === strtolower('image/jpeg') || $child->getContentType() === strtolower('image/png')) {
                    if (!$dir->childExists($this->fileNameEmbeddings)) {
                        $dir->createFile($this->fileNameEmbeddings);
                    } else {
                        if ($is_touch) {
                            $this->touch($dir->getChild($this->fileNameEmbeddings), $path);
                        }
                    }
                    if (!$dir->childExists($this->fileNameFaces)) {
                        $dir->createFile($this->fileNameFaces);
                    } else {
                        if ($is_touch) {
                            $this->touch($dir->getChild($this->fileNameFaces), $path);
                        }
                        $this->files_faces[] = $path . "/" . $this->fileNameFaces;
                    }
                    if ($dir->childExists($this->fileNameNames)) {
//                        $f = $dir->getChild($this->fileNameNames);
//                        $stream = $f->get();
//                        $contents = stream_get_contents($stream);
//                        if ($contents == "") {
//                            //$f->delete();
//                        } else {
//                            $this->files_names[] = $path . "/" . $this->fileNameNames;
//                        }
                        $this->files_names[] = $path . "/" . $this->fileNameNames;
                        if ($is_touch) {
                            $this->touch($dir->getChild($this->fileNameNames), $path);
                        }
                    }
                    $check = false;
                }
            } else if ($child instanceof Directory) {
                $p = $path . "/" . $child->getName();
                $this->checkDataFiles($child, $p, $is_touch);
            }
        }
    }

    private function touch(File $file, $path) {
        $fName = $file->getName();
        $pos = strpos($path, "/");
        if ($pos) {
            $path = substr($path, $pos + 1);
        }
        $displaypath = $path . "/" . $fName;
        $r = q("SELECT id, hash, os_path FROM attach WHERE display_path = '%s' AND uid = %d LIMIT 1",
                dbesc($displaypath),
                intval($this->owner['channel_id'])
        );
        $f = $r[0];
        if (!$f) {
            return;
        }
        $storeDir = $this->getStoreDir();
        $path_fs = $storeDir . DIRECTORY_SEPARATOR . $f["os_path"];
        date_default_timezone_set("UTC");
        $modified_fs = filemtime($path_fs);
        $modified_db = $file->getLastModified();
        // inside Code\Storage\File put(...) the edited time in the db is set
        // after the file is written to the file system. So it should be save
        // to compare the times here.
        if ($modified_fs <= $modified_db) {
            return;
        }
        // assume the file was written by the python scripts. Otherwise
        // the last modified time in the file system should not be greater
        // than the time in the database
        logger($displaypath . " was written by python. Set file size and last modified in database and synchronize to clones...", LOGGER_DEBUG);

        $edited = date("Y-m-d H:i:s", $modified_fs);
        $size = filesize($path_fs);

        $d = q("UPDATE attach SET filesize = '%s', edited = '%s' WHERE hash = '%s' AND uid = %d",
                dbesc($size),
                dbesc($edited),
                dbesc($f['hash']),
                intval($this->owner['channel_id'])
        );

        $channel = \App::get_channel();

        $sync = attach_export_data($channel, $f['hash']);

        if ($sync) {
            Libsync::build_sync_packet($channel['channel_id'], array('file' => array($sync)));
        }
    }

    private function getUserDir() {
        $rootDir = $this->getRootDir();
        $channelAddress = $this->owner['channel_address'];
        $userDir = $rootDir->getChild($channelAddress);
        if (!$userDir) {
            json_return_and_die(array('message' => 'No cloud directory for channel ' . $channelAddress, 'success' => false));
        }

        if (!$userDir->childExists($this->addonDirName)) {
            $userDir->createDirectory($this->addonDirName);
        }

        return $userDir;
    }

    private function getAddonDir($is_touch = false) {

        $channelAddress = $this->owner['channel_address'];
        $addonDir = new Directory('/' . $channelAddress . '/' . $this->addonDirName, $this->getAuth());

        $path = $channelAddress . DIRECTORY_SEPARATOR . $this->addonDirName;

        if (!$addonDir->childExists($this->fileNameFacesStatistic)) {
            $addonDir->createFile($this->fileNameFacesStatistic);
        } else {
            if ($is_touch) {
                $this->touch($addonDir->getChild($this->fileNameFacesStatistic), $path);
            }
        }

        if (!$addonDir->childExists($this->fileNameModelsStatistic)) {
            $addonDir->createFile($this->fileNameModelsStatistic);
        } else {
            if ($is_touch) {
                $this->touch($addonDir->getChild($this->fileNameModelsStatistic), $path);
            }
        }
        if (!$addonDir->childExists($this->fileNameProbe)) {
            !$addonDir->createFile($this->fileNameProbe);
        } else {
            $this->touch($addonDir->getChild($this->fileNameProbe), $path);
        }

        if (!$addonDir->childExists($this->fileNameConfig)) {
            $addonDir->createFile($this->fileNameConfig);
        }

        if (!$addonDir->childExists($this->fileNameThresholds)) {
            $addonDir->createFile($this->fileNameThresholds);
        }

        return $addonDir;
    }

    private function prepareProbeDirs() {

        $channelAddress = $this->owner['channel_address'];
        $addonDir = new Directory('/' . $channelAddress . '/' . $this->addonDirName, $this->getAuth());

        $path = $channelAddress . DIRECTORY_SEPARATOR . $this->addonDirName . DIRECTORY_SEPARATOR . $this->probeDirName;

        if (!$addonDir->childExists($this->probeDirName)) {
            $addonDir->createDirectory($this->probeDirName);
        }
        if (!$addonDir->getChild($this->probeDirName)->childExists("known")) {
            !$addonDir->getChild($this->probeDirName)->createDirectory("known");
        }
        if (!$addonDir->getChild($this->probeDirName)->childExists("unknown")) {
            !$addonDir->getChild($this->probeDirName)->createDirectory("unknown");
        }
        if (!$addonDir->getChild($this->probeDirName)->childExists("Jane")) {
            !$addonDir->getChild($this->probeDirName)->createDirectory("Jane");
        }
        if (!$addonDir->getChild($this->probeDirName)->childExists("Bob")) {
            !$addonDir->getChild($this->probeDirName)->createDirectory("Bob");
        }
    }

    private function getConfigFile() {
        $addonDir = $this->getAddonDir();
        $confFile = null;
        if ($addonDir) {
            $confFile = $addonDir->getChild($this->fileNameConfig);
        }
        return $confFile;
    }

    private function getRootDir() {
        $rootDirectory = new Directory('/', $this->getAuth());
        $channelAddress = $this->owner['channel_address'];
        if (!$rootDirectory->childExists($channelAddress)) {
            logger("sending status=false, no cloud directory", LOGGER_NORMAL);
            json_return_and_die(array('message' => 'No cloud directory.', 'success' => false));
        }
        return $rootDirectory;
    }

    private function getAuth() {
        $auth = new BasicAuth();

        $ob_hash = get_observer_hash();

        if ($ob_hash) {
            if (local_channel()) {
                $channel = \App::get_channel();
                $auth->setCurrentUser($channel['channel_address']);
                $auth->channel_id = $channel['channel_id'];
                $auth->channel_hash = $channel['channel_hash'];
                $auth->channel_account_id = $channel['channel_account_id'];
                if ($channel['channel_timezone']) {
                    $auth->setTimezone($channel['channel_timezone']);
                }
            }
            $auth->observer = $ob_hash;
        }

        return $auth;
    }

    private function getStatus() {
        $txt = get_config("faces", "status");
        if (!$txt) {
            logger('sending running=false , the python script was never started befor', LOGGER_NORMAL);
            json_return_and_die(array('running' => false));
        }
        logger('status face recognition: ' . $txt, LOGGER_DEBUG);
        $a = explode(' ', $txt);
        $status = $a[0];

        if (sizeof($a) < 5) {
            logger("sending running=false, status face recognition: not the expected format. Content='" . trim($txt) . "' . Size of array not 4 if splitted by a space. Assuming that the python script is not running.", LOGGER_NORMAL);
            json_return_and_die(array('message' => 'wrong format in database', 'running' => false));
        }

        $values["status"] = $status;
        $running = (strtolower($status) == "finished") ? false : true;

        $updated = $a[1] . " " . $a[2];
        $elapsed = strtotime(datetime_convert()) - strtotime($updated); // both UTC
        // UTC in ISO data"2015-03-25T12:00:00Z", T... seperator, Z... UTC
        $values["utc"] = $a[1] . "T" . $a[2] . "Z";
        $values["elapsed"] = $elapsed;

        $values["procid"] = $a[4];

        logger('sending running=.' . $running . ', values: ' . json_encode($values), LOGGER_NORMAL);
        json_return_and_die(array('running' => $running, 'status' => $values));
    }

    private function setName() {
        $face = $_POST["face"];
        if ($face) {
            logger('Received face ' . json_encode($face), LOGGER_DEBUG);
        } else {
            logger('sending status=false, parameter face was not received', LOGGER_NORMAL);
            json_return_and_die(array('status' => false, 'message' => "Parameter face was not received"));
        }


        $file = $face['file'];
        if (!$face['position']) {
            $msg = "sending status=false, received no face position to write a name. Received: " . json_encode($face);
            logger($msg, LOGGER_NORMAL);
            json_return_and_die(array('status' => false, 'message' => $msg));
        } elseif (!$file) {
            $msg = "received no file to write a name. Received: " . json_encode($face);
            logger('sending status=false, ' . $msg, LOGGER_NORMAL);
            json_return_and_die(array('status' => false, 'message' => $msg));
        } else {
            $i = strpos($file, "/", strlen("cloud"));
            $file = substr($file, $i);
            $dirname = pathinfo($file, PATHINFO_DIRNAME);
            $imgDir = new Directory($dirname, $this->getAuth());
            if (!$imgDir->childExists($this->fileNameNames)) {
                $imgDir->createFile($this->fileNameNames);
            }
            $names_file = $imgDir->getChild($this->fileNameNames);
            $chan_addr = $this->owner['channel_address'];
            $i = strpos($file, "/", strlen($chan_addr));
            $image = substr($file, $i + 1);
            ////////////
            require_once('Name.php');
            $writer = new Name();
            $success = $writer->write($names_file, $image, $face['name'], $face['id'], $face['position']);
            ////////////
            if (!$success) {
                $msg = "Failed to write name='" . $face['name'] . "' with id='" . $face['id'] . " for image='" . $image;
                logger('sending status=false, ' . $msg, LOGGER_NORMAL);
                json_return_and_die(array('status' => false, 'message' => $msg));
            }
            logger('sending status=true, name was written for face=' . json_encode($face), LOGGER_NORMAL);
            json_return_and_die(array('status' => true, 'message' => "name was written", 'face' => $face));
        }
    }

    private function showSettingsPage($loglevel) {

        $o = replace_macros(Theme::get_template('settings.tpl', 'addon/faces'), array(
            '$version' => $this->getAppVersion(),
            '$loglevel' => $loglevel,
        ));

        return $o;
    }

    private function showThresholdsPage($loglevel) {

        $o = replace_macros(Theme::get_template('thresholds.tpl', 'addon/faces'), array(
            '$version' => $this->getAppVersion(),
            '$loglevel' => $loglevel,
        ));

        return $o;
    }

    private function showProbePage($loglevel) {

        $this->getAddonDir(); // create probe.json (results) if it does not exist
        $this->prepareProbeDirs();

        $o = replace_macros(Theme::get_template('probe.tpl', 'addon/faces'), array(
            '$version' => $this->getAppVersion(),
            '$loglevel' => $loglevel,
        ));

        return $o;
    }

    private function sendConfig() {
        $this->prepareFiles();
        $config = $this->getConfig();
        logger("Sending configuration... " . json_encode($config), LOGGER_DATA);
        json_return_and_die(array('config' => $config));
    }

    private function setConfig() {
        $this->prepareFiles();
        $config = $this->getConfig();

        $exclude = ["reset", "experimental"];
        $isText = ["percent", "pixel", "training", "result", "zoom"];
        foreach ($config as $name => $values) {
            for ($i = 0;
                    $i < sizeof($values);
                    $i++) {
                $elName = $values[$i][0];
                if (!$elName) {
                    continue;  // not every config value is configured in the frontend
                }
                if (in_array($elName, $exclude)) {
                    continue;
                }
                $received = $_POST[$elName];
                if ($received && !in_array($elName, $isText)) {
                    $config[$name][$i][1] = true;
                } elseif (in_array($elName, $isText)) {
                    $config[$name][$i][1] = $received;
                } else {
                    $config[$name][$i][1] = false;
                }
            }
        }
        require_once('FaceRecognition.php');
        $fr = new FaceRecognition();
        $fr->stop();

        $configFile = $this->getConfigFile();
        require_once('Config.php');
        $fc = new FaceConfiguration();
        $fc->write($configFile, $config);

        $fr->finished();
    }

    private function getThresholds() {
        require_once('Thresholds.php');
        $th = new FaceThresholds();

        $thresholds = [];

        $file = $this->getThresholdsFile();
        if (!$file) {
            $thresholds = $th->getDefaults();
            logger("using default thresholds because failed to read file", LOGGER_DEBUG);
        } else {
            $thresholds = $th->read($file);
        }
        logger("did read thresholds " . json_encode($thresholds), LOGGER_DEBUG);
        return $thresholds;
    }

    private function sendThresholds() {
        $this->prepareFiles();
        $thresholds = $this->getThresholds();
        require_once('Thresholds.php');
        $th = new FaceThresholds();
        $defaults = $th->getDefaults();
        logger("Sending tresholds... " . json_encode($thresholds), LOGGER_NORMAL);
        json_return_and_die(array(
            'thresholds' => $thresholds,
            'defaults' => $defaults));
    }

    private function getThresholdsFile() {
        $addonDir = $this->getAddonDir();
        $thresholdsFile = null;
        if ($addonDir) {
            $thresholdsFile = $addonDir->getChild($this->fileNameThresholds);
        }
        return $thresholdsFile;
    }

    private function setThresholds() {
        $this->prepareFiles();
        //$thresholds = $this->getThresholds();

        require_once('Thresholds.php');
        $th = new FaceThresholds();
        $defaults = $th->getDefaults();
        foreach ($defaults as $model => $model_metrics) {
            foreach ($model_metrics as $metric => $value) {
                $received = $_POST[$model . "_" . $metric];
                if (is_numeric($received)) {
                    $defaults[$model][$metric] = $received;
                }
            }
        }
        $file = $this->getThresholdsFile();
        $th->write($file, $defaults, true);
    }

    private function getConfig() {
        require_once('Config.php');
        $fc = new FaceConfiguration();

        $config = [];

        $configFile = $this->getConfigFile();
        if (!$configFile) {
            $config = $fc->getDefaultConfig();
            logger("using default configuration because failed to read config file", LOGGER_DEBUG);
        } else {
            $config = $fc->read($configFile);
        }
        logger("did read configuration " . json_encode($config), LOGGER_DATA);
        return $config;
    }

    private function showRemovePage($loglevel) {

        $block = (get_config('faces', 'block_python') ? get_config('faces', 'block_python') : false);
        if ($block) {
            $o = "Not available. Why? The Python scripts for face detection and recognition are blocked on this server.";
            return $o;
        }

        $o = replace_macros(Theme::get_template('remove.tpl', 'addon/faces'), array(
            '$version' => $this->getAppVersion(),
            '$loglevel' => $loglevel,
        ));

        return $o;
    }

    private function remove() {

        $config = $this->getConfig();
        $params = "";
        $types = ["detectors", "models"];
        foreach ($types as $type) {
            $elements = $config[$type];
            $param = "";
            foreach ($elements as $element) {
                $name = $element[0];
                $received = $_POST[$name];
                if ($received) {
                    if ($param !== "") {
                        $param .= ",";
                    }
                    $param .= $name;
                }
            }
            if ($param !== "") {
                $params .= " --rm_" . $type . " " . $param;
            }
        }
        $rm_names = $_POST["names"];
        if ($rm_names) {
            $params .= " --rm_names on";
        }
        if ($params !== "") {
            $this->startDetection("start", $params);
        }
    }

    private function showHelpPage() {
        Head::add_css('/addon/faces/view/css/faces.css');
        $o = replace_macros(Theme::get_template('help.tpl', 'addon/faces'), array());
        return $o;
    }

}
