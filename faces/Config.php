<?php

namespace Code\Module;

use Code\Storage\File;

class FaceConfiguration {

    private $available_detectors = "retinaface,mtcnn,ssd,opencv";
    private $available_models = "Facenet512,ArcFace,VGG-Face,Facenet,OpenFace,DeepFace,SFace";
    private $available_metrics = "cosine,euclidean_l2,euclidean";
    private $available_attributes = "Emotion,Age,Gender,Race";

    function read(\Code\Storage\File $file) {

        $JSONstream = $file->get();
        $contents = stream_get_contents($JSONstream);
        $config = json_decode($contents, true);
        fclose($JSONstream);

        if (!$config) {
            $config = $this->getDefaultConfig();
            $this->write($file, $config);
        } else {
            $config = $this->checkConfig($config);
        }


        return $config;
    }

    function write(\Code\Storage\File $file, $config) {
        $config = $this->checkConfig($config);

        $json = json_encode($config);

        $file->put($json);

        logger('wrote configuration file=' . $file->getName() . ", content=" . $json, LOGGER_DEBUG);
    }

    function checkConfig($config) {

        $experimental_allowed = get_config('faces', 'experimental_allowed') ? get_config('faces', 'experimental_allowed') : false;

        $config = $this->checkAllowed($config, 'detectors', $experimental_allowed);
        $config = $this->checkIsOneSwitchedON($config, 'detectors');

        $config = $this->checkAllowed($config, 'models', $experimental_allowed);
        $config = $this->checkIsOneSwitchedON($config, 'models');

        $config = $this->checkAllowed($config, 'distance_metrics', $experimental_allowed);
        $config = $this->checkIsOneSwitchedON($config, 'distance_metrics');

        $config = $this->checkAllowed($config, 'demography', $experimental_allowed);

        $config = $this->validate($config);

        return $config;
    }

    private function checkAllowed($config, $group, $experimental_allowed) {
        $allowed = explode(",", get_config('faces', $group));
        $found = false;
        for ($i = 0; $i < sizeof($config[$group]); $i++) {
            $element = $config[$group][$i];
            $name = $element[0];
            $value = $element[1];
            if (in_array($name, $allowed)) {
                if ($value && !$found) {
                    $found = true;
                } elseif ($value && $found && !$experimental_allowed && $group !== "demography") {
                    $config[$group][$i][1] = false;
                }
                $config[$group][$i][2] = false;
            } else {
                $config[$group][$i][1] = false;
                $config[$group][$i][2] = true;  // disabled
            }
        }

        return $config;
    }

    private function validate($config) {

        $experimental_allowed = get_config('faces', 'experimental_allowed') ? get_config('faces', 'experimental_allowed') : false;

        if (!$experimental_allowed) {
            $config["statistics"][0][1] = false;
            $config["statistics"][0][2] = true;
            
            $config["history"][0][1] = false;
            $config["history"][0][2] = true;

            $config["enforce"][0][1] = false;
            $config["enforce"][0][2] = true;

            $config["faces_experimental"][0][1] = false;
            $config["faces_experimental"][0][2] = true;

            $config["faces_defaults"][0][1] = false;
            $config["faces_defaults"][0][2] = false;
        } else {
            $config["statistics"][0][2] = false;
            
            $config["history"][0][2] = false;

            $config["enforce"][0][2] = false;

            $config["faces_experimental"][0][1] = false;
            $config["faces_experimental"][0][2] = false;

            $config["faces_defaults"][0][1] = false;
            $config["faces_defaults"][0][2] = false;
        }


        for ($i = 0; $i < sizeof($config["min_face_width"]); $i++) {
            $element = $config["min_face_width"][$i];
            $name = $element[0];
            $number = $element[1];

            $min = 30;
            $max = 10000;
            $default = 50;

            if ($name === "percent") {
                $min = 1;
                $max = 99;
                $default = 2;
            }

            if (!is_numeric($number)) {
                $number = $default;
            } else {
                $number = round($number);
                if ($number > $max) {
                    $number = $max;
                } elseif ($number < $min) {
                    $number = $min;
                }
            }

            $config["min_face_width"][$i][1] = $number;
        }

        return $config;
    }

    private function checkIsOneSwitchedON($config, $group) {

        for ($i = 0; $i < sizeof($config[$group]); $i++) {
            $element = $config[$group][$i];
            $value = $element[1];
            if ($value) {
                return $config;
            }
        }

        $config[$group][0][1] = true;
        $config[$group][0][2] = false;

        return $config;
    }

    function getDefaultConfig() {
        $config = [];
        $config = $this->addConfigElement("detectors", $this->available_detectors, $config, true);
        $config = $this->addConfigElement("models", $this->available_models, $config, true);
        $config = $this->addConfigElement("distance_metrics", $this->available_metrics, $config, true);
        $config = $this->addConfigElement("demography", $this->available_attributes, $config, false);

        $config = $this->addConfigElement("statistics", "statistics", $config, false);
        $config = $this->addConfigElement("history", "history", $config, false);
        $config = $this->addConfigElement("enforce", "enforce", $config, false);
        $config = $this->addConfigElement("faces_defaults", "reset", $config, false);
        $config = $this->addConfigElement("faces_experimental", "experimental", $config, false);
        $config["min_face_width"] = [["percent", 5], ["pixel", 50]];

        $config = $this->checkConfig($config);

        return $config;
    }

    private function addConfigElement($group, $list, $config, $isON) {
        $group_names = explode(",", $list);
        $group_config = [];
        foreach ($group_names as $name) {
            array_push($group_config, array($name, $isON, false));
            //$group_config[$name] = $isON;
        }
        $config[$group] = $group_config;
        return $config;
    }

}
