<?php

namespace library\util;

class  Utils {

    public static function sort($a, $b){
        if ($a == $b) {
            return 0;
        }
        return ($a < $b) ? 1 : -1;
    }

    public static function commandLog($message){
        echo date('Y-m-d H:i:s').": $message \n";
    }


    public static function parseScore($score) {
        $arrScore = explode(':', $score);
        if (count($arrScore) != 2) {
            return false;
        }
        if ($arrScore[0] > $arrScore[1]) {
            return 0;
        } else if ($arrScore[0] == $arrScore[1]) {
            return 1;
        } else {
            return 2;
        }
    }

}

