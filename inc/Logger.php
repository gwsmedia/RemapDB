<?php

class Logger {
    static function log_to_file($var, $logFile, $wipe = false, $die = false) {
        $contents = $wipe || !is_file($logFile) ? '' : file_get_contents($logFile);
        if(!empty($contents)) $contents .= str_repeat(PHP_EOL, 2)."-------------".str_repeat(PHP_EOL, 3);

        $contents .= '[' . date('Y-m-d H:i:s') . ']' . PHP_EOL . print_r($var, true);
        file_put_contents($logFile, $contents);
        if($die) die();
    }

    static function dev_log($var, $wipe = false, $die = false) {
        self::log_to_file($var, dirname(__FILE__) . '/../log/dev.log', $wipe, $die);
    }

    static function vdp($var, $die = true) {
        echo "<pre>";
        var_dump($var);
        echo "</pre>";
        if($die) die();
    }
}
