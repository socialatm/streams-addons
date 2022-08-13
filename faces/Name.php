<?php

namespace Code\Module;

class Name {

    public function write(\Code\Storage\File $file, string $image, string $name, $position)
    {
        logger("Try to write name '".$name."' for face at position='".implode(",",$position)."' in image=" . $image, LOGGER_DEBUG);
        
        $stream = $file->get();
        //$csv = file_get_contents($file);
        $csv = stream_get_contents($stream);
        fclose($stream);

        $csv = $this->replace($csv, $image, $name, $position);
        if (!$csv) {
            return false;
        }

        logger("about to write name='" . $name . "' for face at position='" . implode(",",$position) . "'", LOGGER_DEBUG);
        $file->put($csv);
        logger("wrote name='" . $name . "' for face at position='".implode(",",$position)."' in image=" . $image, LOGGER_NORMAL);

        return true;
    }

    private function replace(string $csv, string $image, string $name, $position)
    {
        logger("replacing name='" . $name . "' for face at position='".implode(",",$position)."'", LOGGER_DEBUG);
        $s = "";
        $sep = ",";
        $nameIndex = -1;
        $timeNamedIndex = -1;
        $fileIndex = -1;
        $facePositionIndex = -1;
        $headerLength = 8;
        $lines = explode(PHP_EOL, $csv);
        $hasMatch = false;
        foreach ($lines as $line) {
            $line = trim($line);
            $columns = explode($sep, $line);
            if (sizeof($columns) === 1) {
                logger("skip (empty) line without seperator", LOGGER_DEBUG);
                continue;
            }
            if (strpos($line, "id,file,") !== false) {
                // in case the order of the column will change
                $i = 0;
                foreach ($columns as $column) {
                    if ($column === "name") {
                        $nameIndex = $i + 3;  // for the position array
                    } elseif ($column === "time_named") {
                        $timeNamedIndex = $i + 3;
                    } elseif ($column === "file") {
                        $fileIndex = $i;
                    } elseif ($column === "position") {
                        $facePositionIndex = $i;
                    }
                    $i++;
                }
                if ($nameIndex == -1 || $timeNamedIndex == -1 || $fileIndex == -1 || $facePositionIndex == -1) {
                    logger("something wrong with csv format (columns) in line='" . $line . "'", LOGGER_NORMAL);
                    return false;
                }
                $s = $line;
                $headerLength = sizeof($columns) + 3;
                logger("header length='" . $headerLength . "'", LOGGER_DEBUG);
            } else {
                if (sizeof($columns) < $headerLength) {
                    logger("Wrong with csv format. Count of columns='" . sizeof($columns) . "' is less than ".$headerLength.", line='".$line."'. This might happen if the python scripts write the file just in the moment of PHP reads it.", LOGGER_NORMAL);
                    logger($columns, LOGGER_DEBUG);
                    return false;
                }
                if (sizeof($columns) > $headerLength) {
                    logger("Wrong with csv format. Count of columns='" . sizeof($columns) . "' is greater than ".$headerLength.", line='".$line."'. This might happen if the python scripts write the file just in the moment of PHP reads it.", LOGGER_NORMAL);
                    logger($columns, LOGGER_DEBUG);
                    return false;
                }
                $col_file = $columns[$fileIndex];
                if ($col_file == $image) {
                    $left = $columns[$facePositionIndex];
                    $left = str_replace('[', '', $left);
                    $left = (int) str_replace('"', '', $left);
                    $right = (int) $columns[$facePositionIndex + 1];
                    $top = (int) $columns[$facePositionIndex + 2];
                    $bottom = $columns[$facePositionIndex + 3];
                    $bottom = str_replace('[', '', $bottom);
                    $bottom = (int) str_replace('"', '', $bottom);
                    $col_position = [$left, $right, $top, $bottom];
                    if ($this->isSameFace($col_position, $position)) {
                        $columns[$nameIndex] = $name;
                        // $objDateTime = new DateTime('NOW'); 
                        // $dateTimeString = $objDateTime->format(DateTime::W3C);
                        date_default_timezone_set("UTC");
                        $dateTimeString = date("Y-m-d\TH:i:sP");  // same as W3C format but always ...T+00:00
                        $columns[$timeNamedIndex] = $dateTimeString;
                        $line = implode($sep, $columns);
                        logger("Replaced name with new name='".$name."' for face at position='". implode(",",$position)."' in image='" . $image, LOGGER_DEBUG) ;
                        $hasMatch = true;
                    }
                }
                $s = $s . PHP_EOL . $line;
            }
                
        }
        if ($hasMatch) {
            return $s;
        }
        logger("No match for image='" . $image . "' with face at position=" . implode(",",$position) . ". Replacement with new name=".$name." was not successfull.", LOGGER_NORMAL);
        return false;
    }

    private function isSameFace($face_a, $face_b) {
        // margins left, right, top, bottom in percent
        $middle_of_face_x = (int)($face_a[0]) + ( 100 - ( (int)($face_a[1]) + (int)($face_a[0] ) )) / 2;
        $middle_of_face_y = (int)($face_a[2]) + ( 100 - ( (int)($face_a[2]) + (int)($face_a[3] ) )) / 2;
        $end_of_face_b_x = 100 - $face_b[1];
        $end_of_face_b_y = 100 - $face_b[3];
        // is middle of face_a inside $face_b position?
        if ( ($face_b[0] < $middle_of_face_x) && ($middle_of_face_x < $end_of_face_b_x) ) {
            if ( ($face_b[2] < $middle_of_face_y) && ($middle_of_face_y < $end_of_face_b_y) ) {
                $middle_of_face_b_x =  (int)($face_b[0]) + ( 100 - ( (int)($face_b[1]) + (int)($face_b[0] ) )) / 2;
                $middle_of_face_b_y = (int)($face_b[2]) + ( 100 - ( (int)($face_b[2]) + (int)($face_b[3] ) )) / 2;
                $end_of_face_x = 100 - $face_a[1];
                $end_of_face_y = 100 - $face_a[3];
                // is middle of face_b position inside face_a ?
                if (($face_a [0] < $middle_of_face_b_x) && ($middle_of_face_b_x < $end_of_face_x)) {
                    if (($face_a[2] < $middle_of_face_b_y) && ($middle_of_face_b_y < $end_of_face_y)) {
                        return True;
                    }
                }
            }
        }
    }

    private function startsWith($haystack, $needle)
    {
        $length = strlen($needle);
        return substr($haystack, 0, $length) === $needle;
    }

}
