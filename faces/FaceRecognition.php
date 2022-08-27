<?php

namespace Code\Module;

class FaceRecognition {

    function start($storeDirectory, $channel_id, $config, $rm_params) {
        if (!$rm_params) {
            $isRunning = $this->isScriptRunning();
            if ($isRunning) {
                return;
            }
        } else {
            logger("Start the python script despite another one is still running. Set a new procid. This will stopp a running python script ", LOGGER_DEBUG);
        }
        $procid = random_string(10);
        set_config("faces", "status", "started " . datetime_convert() . " pid " . $procid);

        $logfile = get_config('logrot', 'logrotpath') . '/faces.log';
        $logfileparam = " --logfile " . $logfile;
        if (!$logfile) {
            $logfileparam = "";
        } else {
            if (!is_writable($logfile)) {
                logger("PLEASE CHECK PATH OR PERMISSIONS! Can not write log file " . $logfile, LOGGER_DEBUG);
                $logfileparam = "";
            }
        }
        $logEnabled = get_config('system', 'debugging');
        if (!$logEnabled) {
            $logfile = '';
        }
        $loglevel = (get_config('system', 'loglevel') ? get_config('system', 'loglevel') : LOGGER_NORMAL);
        $max_ram = (get_config('faces', 'max_ram') ? get_config('faces', 'max_ram') : 80);

        $detectorsConfig = $this->getParamString($config, "detectors");
        $modelsConfig = $this->getParamString($config, "models");
        $distanceMetricsConfig = $this->getParamString($config, "distance_metrics");
        $demographyConfig = $this->getParamString($config, "demography");
        $first_resultConfig = $this->getParamStringDirectly($config, "first_result", "enforce");
        $statisticsConfig = $this->getParamStringDirectly($config, "statistics", "statistics");
        $history_modeConfig = $this->getParamStringDirectly($config, "history", "history");
        $minFaceWidthDetectionConfig = $this->getParamStringTextField($config, "min_face_width_detection");
        $minFaceWidthRecognitionConfig = $this->getParamStringTextField($config, "min_face_width_recognition");

        @include('.htconfig.php');
        $cmd = escapeshellcmd("python3 " . getcwd() . "/addon/faces/py/faces.py"
                . " --host " . $db_host . " --user " . $db_user . " --pass " . $db_pass . " --db " . $db_data
                . " --imagespath " . $storeDirectory . " --channelid " . $channel_id
                . " --procid " . $procid
                . $first_resultConfig
                . $minFaceWidthDetectionConfig
                . $minFaceWidthRecognitionConfig
                . $statisticsConfig
                . $history_modeConfig
                . $rm_params
                . " --ram " . $max_ram
                . " --loglevel " . $loglevel . $logfileparam
                . $detectorsConfig . $modelsConfig . $distanceMetricsConfig . $demographyConfig);

        logger('The pyhton script will be executed using the following command ...', LOGGER_DEBUG);
        // overwrite password
        $a = explode(" ", $cmd);
        $key = array_search("--pass", $a);
        $a[$key + 1] = "xxx";
        logger(implode(" ", $a), LOGGER_DEBUG);

        // python3 /var/www/mywebsite/addon/faces/py/faces.py --host 127.0.0.1 --user mywebsite --pass xxx --db mywebsite --imagespath /var/www/mywebsite/store/oj --channelid 0 --procid aeeed6d862 --first_result on --percent 2 --pixel 50 --training 224 --result 50 --statistics on --history on --loglevel 2 --logfile /var/www/log/faces.log --detectors retinaface,mtcnn,ssd,opencv,mediapipe --models Facenet512,ArcFace,VGG-Face,Facenet,OpenFace,DeepFace,SFace --distance_metrics euclidean,cosine,euclidean_l2 --demography Emotion,Age,Gender,Race
        //exec($cmd . ' > /dev/null 2>/dev/null &');
    }

    private function getParamString($config, $name) {
        $param = "";
        $values = $config[$name];
        for ($i = 0; $i < sizeof($values); $i++) {
            $elName = $values[$i][0];
            $value = $values[$i][1];
            if ($value) {
                if (strlen($param) > 0) {
                    $param .= ",";
                }
                $param .= $elName;
            }
        }
        if (strlen($param) > 0) {
            $param = " --" . $name . " " . $param;
        }
        return $param;
    }

    private function getParamStringDirectly($config, $name, $configName) {
        $param = "";
        $values = $config[$configName];
        $value = $values[0][1];
        if ($value) {
            $param = "on";
        } else {
            $param = "off";
        }
        $param = " --" . $name . " " . $param;
        return $param;
    }

    private function getParamStringTextField($config, $name) {
        $param = "";
        $values = $config[$name];
        for ($i = 0; $i < sizeof($values); $i++) {
            $elName = $values[$i][0];
            $value = $values[$i][1];
            $param .= " --" . $elName . " " . $value;
        }
        return $param;
    }

    function isScriptRunning() {
        $txt = get_config("faces", "status");
        if (!$txt) {
            logger('The python script was never started befor', LOGGER_DEBUG);
            return false;
        }
        logger('status face recognition: ' . $txt, LOGGER_DEBUG);
        $a = explode(' ', $txt);
        $status = $a[0];

        if (sizeof($a) < 5) {
            logger("Status face recognition: not the expected format. Content='" . trim($txt) . "' . Size of array not 4 if splitted by a space. Assuming that the python script is not running.", LOGGER_DEBUG);
            return false;
        }

        if (strtolower($status) == "finished") {
            logger("Status face recognition: finished", LOGGER_DEBUG);
            return false;
        }

        $updated = $a[1] . " " . $a[2];
        $elapsed = strtotime(datetime_convert()) - strtotime($updated); // both UTC
        if ($elapsed > 60 * 10) {
            // Th Python script writes a timestamp "updated" every 10 seconds to indicate it is still running.
            // Now we are 10 minutes away from the last "updated" written by the script.
            // It might be that the python script hangs or was stopped.
            $msg = 'The script did not finish yet. Please watch this condition. Why? It might be that the python script hangs, run into errors or was stopped externally. The last update by the script was at ' . $updated . '. This is more then 10 minutes ago. This is unusual because the script writes a time stamp every 10 seconds to indicate that it is still running.';
            logger($msg, LOGGER_DEBUG);
            $this->finished();
            return false;
        }
        logger('The python script is still running. Last update: ' . $updated, LOGGER_DEBUG);
        return true;
    }

    function stop() {
        $procid = random_string(10);
        set_config("faces", "status", "stopped " . datetime_convert() . " pid " . $procid);
    }

    function finished() {
        $procid = random_string(10);
        set_config("faces", "status", "finished " . datetime_convert() . " pid " . $procid);
    }

}
