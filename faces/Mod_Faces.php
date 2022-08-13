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

class Faces extends Controller {

    private $is_owner;
    private $can_write;
    private $owner;
    private $observer;
    private $addonDirName = "faces";
    private $fileNameEmbeddings = "faces.pkl";
    private $fileNameNames = "faces.csv";
    private $fileNameAttributes = "facial_attributes.csv";
    private $fileNameFacesStatistic = "faces_statistics.csv";
    private $fileNameModelsStatistic = "models_statistics.csv";
    private $fileNameConfig = "config.json";
    private $files_names = [];
    private $files_attributes = [];

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
                case 'remove':
                    // API: /faces/nick/remove
                    $o = $this->showRemovePage($loglevel);
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

        $zoom = get_config('faces', 'zoom');
        if (!$zoom) {
            $zoom = 3;
        }

        Head::add_css('/addon/faces/view/css/faces.css');
        $o = replace_macros(Theme::get_template('faces.tpl', 'addon/faces'), array(
            '$version' => $version,
            '$status' => $ret['status'],
            '$message' => $ret['message'],
            '$can_write' => $this->can_write ? 'true' : 'false',
            '$is_owner' => $this->is_owner ? 'true' : 'false',
            '$log_level' => $loglevel,
            '$faces_zoom' => $zoom,
            '$submit' => t('Submit'),
        ));

        return $o;
    }

    function post() {

        $status = $this->permChecks();

        if (!$status['status']) {
            notice($status['message'] . EOL);
            json_return_and_die(array('status' => false, 'message' => $status['message']));
        }
        if (!$this->observer) {
            json_return_and_die(array('status' => false, 'message' => 'Unknown observer. Please login.'));
        }

        if (argc() > 2) {
            $api = argv(2);
            if ($api === 'start') {
                // API: /faces/nick/start
                $this->startFaceRecognition('start', false);
            } elseif ($api === 'results') {
                // API: /faces/nick/results
                $this->startFaceRecognition('results', false);
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

    private function startFaceRecognition($action, $rm_params) {
        require_once('FaceRecognition.php');
        $fr = new FaceRecognition();
        if (!$rm_params) {
            if ($fr->isScriptRunning()) {
                notice('Face detection is still busy' . EOL);
                json_return_and_die(array('status' => true, 'message' => 'Face detection is still busy'));
            }
        }
        $this->prepareFiles();
        $block = (get_config('faces', 'block_python') ? get_config('faces', 'block_python') : false);
        if (!$block) {
            if ($action === 'start') {
                $storeDirectory = getcwd() . "/store/" . $this->owner['channel_address'];
                $channel_id = 0; // run the face recognition for every channel
                if (isset($_POST["recognize"]) && $_POST["recognize"] == 1) {
                    $channel_id = $this->owner['channel_id']; // run the face recognition for owner channel only
                }
                $config = $this->getConfig();
                $fr->start($storeDirectory, $channel_id, $config, $rm_params);
            }
            if ($rm_params) {
                return;
            }
        }
        json_return_and_die(array(
            'status' => true,
            'names' => $this->files_names,
            'attributes' => $this->files_attributes,
            'message' => "ok"));
    }

    private function prepareFiles() {
        $userDir = $this->getUserDir();
        $this->getAddonDir();
        $this->checkDataFiles($userDir, $userDir->getName());
    }

    private function checkDataFiles(Directory $dir, String $path) {
        // The pyhton script is allowed to write to existing files only. It will
        // ignore images in directories where the data files are missing.
        $children = $dir->getChildren();
        $check = true;
        foreach ($children as $child) {
            if ($child instanceof File && $check) {
                if ($child->getContentType() === strtolower('image/jpeg') || $child->getContentType() === strtolower('image/png')) {
                    if (!$dir->childExists($this->fileNameEmbeddings)) {
                        $dir->createFile($this->fileNameEmbeddings);
                    }
                    if (!$dir->childExists($this->fileNameNames)) {
                        $dir->createFile($this->fileNameNames);
                    } else {
                        $this->files_names[] = $path . "/" . $this->fileNameNames;
                    }
                    if (!$dir->childExists($this->fileNameAttributes)) {
                        $dir->createFile($this->fileNameAttributes);
                    } else {
                        $this->files_attributes[] = $path . "/" . $this->fileNameAttributes;
                    }
                    $check = false;
                }
            } else if ($child instanceof Directory) {
                $p = $path . "/" . $child->getName();
                $this->checkDataFiles($child, $p);
            }
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

    private function getAddonDir() {

        $channelAddress = $this->owner['channel_address'];
        $addonDir = new Directory('/' . $channelAddress . '/' . $this->addonDirName, $this->getAuth());

        if (!$addonDir->childExists($this->fileNameFacesStatistic)) {
            $addonDir->createFile($this->fileNameFacesStatistic);
        }

        if (!$addonDir->childExists($this->fileNameModelsStatistic)) {
            $addonDir->createFile($this->fileNameModelsStatistic);
        }

        if (!$addonDir->childExists($this->fileNameConfig)) {
            $addonDir->createFile($this->fileNameConfig);
        }

        return $addonDir;
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
            logger('The python script was never started befor', LOGGER_DEBUG);
            json_return_and_die(array('running' => false));
        }
        logger('status face recognition: ' . $txt, LOGGER_DEBUG);
        $a = explode(' ', $txt);
        $status = $a[0];

        if (sizeof($a) != 5) {
            logger("Status face recognition: not the expected format. Content='" . trim($txt) . "' . Size of array not 4 if splitted by a space. Assuming that the python script is not running.", LOGGER_DEBUG);
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

        logger('Sending status: ' . json_encode($values), LOGGER_DEBUG);
        json_return_and_die(array('running' => $running, 'status' => $values));
    }

    private function setName() {
        $face = $_POST["face"];
        if ($face) {
            logger('Received face ' . json_encode($face), LOGGER_DEBUG);
        } else {
            logger('Parameter face was not received', LOGGER_DEBUG);
            json_return_and_die(array('status' => false, 'message' => "Parameter face was not received"));
        }


        $file = $face['file'];
        if (!$face['position']) {
            $msg = "received no face position to write a name. Received: " . json_encode($face);
            logger($msg, LOGGER_NORMAL);
            json_return_and_die(array('status' => false, 'message' => $msg));
        } elseif (!$file) {
            $msg = "received no file to write a name. Received: " . json_encode($face);
            logger($msg, LOGGER_NORMAL);
            json_return_and_die(array('status' => false, 'message' => $msg));
        } else {
            $i = strpos($file, "/", strlen("cloud"));
            $file = substr($file, $i);
            $dirname = pathinfo($file, PATHINFO_DIRNAME);
            $imgDir = new Directory($dirname, $this->getAuth());
            $csv_file = $imgDir->getChild($this->fileNameNames);
            $chan_addr = $this->owner['channel_address'];
            $i = strpos($file, "/", strlen($chan_addr));
            $image = substr($file, $i + 1);
            ////////////
            require_once('Name.php');
            $writer = new Name();
            $success = $writer->write($csv_file, $image, $face['name'], $face['position']);
            ////////////
            if (!$success) {
                $msg = "Failed to write name='" . $face['name'] . "' at position='" . implode(",", $face['position']) . "' of image='" . $image;
                logger($msg, LOGGER_NORMAL);
                json_return_and_die(array('status' => false, 'message' => $msg));
            }
            json_return_and_die(array('status' => true, 'face' => $face, 'message' => "ok"));
        }
    }

    private function showSettingsPage($loglevel) {

        $o = replace_macros(Theme::get_template('settings.tpl', 'addon/faces'), array(
            '$version' => $this->getAppVersion(),
            '$loglevel' => $loglevel,
        ));

        return $o;
    }

    private function sendConfig() {
        $config = $this->getConfig();
        logger("Sending configuration... " . json_encode($config), LOGGER_DEBUG);
        json_return_and_die(array('config' => $config));
    }

    private function setConfig() {
        $configFile = $this->getConfigFile();
        $config = $this->getConfig();

        $exclude = ["reset", "experimental"];
        $isText = ["percent", "pixel"];
        foreach ($config as $name => $values) {
            for ($i = 0; $i < sizeof($values); $i++) {
                $elName = $values[$i][0];
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

        require_once('Config.php');
        $fc = new FaceConfiguration();
        $fc->write($configFile, $config);
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
        logger("did read configuration " . json_encode($config), LOGGER_DEBUG);
        return $config;
    }

    private function showRemovePage($loglevel) {
        
        $block = (get_config('faces', 'block_python') ? get_config('faces', 'block_python') : false);
        if($block) {
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
        if ($params !== "") {
            $this->startFaceRecognition("start", $params);
        }
    }

}
