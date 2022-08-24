<?php

namespace Code\Module;

class Name {

    public function write(\Code\Storage\File $file, string $image, string $name, $position) {
        logger("Try to write name '" . $name . "' for face at position='" . implode(",", $position) . "' in image=" . $image, LOGGER_DEBUG);

        $stream = $file->get();
        $contents = stream_get_contents($stream);
        $faces = json_decode($contents, true);

        $faces_replaced = $this->replace($faces, $image, $name, $position);
        if (!$faces_replaced) {
            return false;
        }

        logger("about to write name='" . $name . "' for face at position='" . implode(",", $position) . "'", LOGGER_DEBUG);
        $json = json_encode($faces_replaced);
        $file->put($json);
        logger("wrote name='" . $name . "' for face at position='" . implode(",", $position) . "' in image=" . $image, LOGGER_NORMAL);

        return true;
    }

    private function replace($faces, string $image, string $name, $position) {
        logger("replacing name='" . $name . "' for face at position='" . implode(",", $position) . "'", LOGGER_DEBUG);
        $i = 0;
        while ($faces["file"][$i]) {
            $file = $faces["file"][$i];
            if ($file == $image) {
                $pos = $faces["position"][$i];
                if ($this->isSameFace($pos, $position)) {
                    // $objDateTime = new DateTime('NOW'); 
                    // $dateTimeString = $objDateTime->format(DateTime::W3C);
                    date_default_timezone_set("UTC");
                    $dateTimeString = date("Y-m-d\TH:i:sP");  // same as W3C format but always ...T+00:00
                    $faces["time_named"][$i] = $dateTimeString;
                    $faces["name"][$i] = $name;
                    logger("Replaced name with new name='" . $name . "' for face at position='" . implode(",", $position) . "' in image='" . $image, LOGGER_DEBUG);
                    return $faces;
                }
            }
            $i++;
        }
        logger("No match for image='" . $image . "' with face at position=" . implode(",", $position) . ". Replacement with new name=" . $name . " was not successfull.", LOGGER_NORMAL);
        return false;
    }

    private function isSameFace($face_a, $face_b) {
        // margins left, right, top, bottom in percent
        $middle_of_face_x = (int) ($face_a[0]) + ( 100 - ( (int) ($face_a[1]) + (int) ($face_a[0] ) )) / 2;
        $middle_of_face_y = (int) ($face_a[2]) + ( 100 - ( (int) ($face_a[2]) + (int) ($face_a[3] ) )) / 2;
        $end_of_face_b_x = 100 - $face_b[1];
        $end_of_face_b_y = 100 - $face_b[3];
        // is middle of face_a inside $face_b position?
        if (($face_b[0] < $middle_of_face_x) && ($middle_of_face_x < $end_of_face_b_x)) {
            if (($face_b[2] < $middle_of_face_y) && ($middle_of_face_y < $end_of_face_b_y)) {
                $middle_of_face_b_x = (int) ($face_b[0]) + ( 100 - ( (int) ($face_b[1]) + (int) ($face_b[0] ) )) / 2;
                $middle_of_face_b_y = (int) ($face_b[2]) + ( 100 - ( (int) ($face_b[2]) + (int) ($face_b[3] ) )) / 2;
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

}
