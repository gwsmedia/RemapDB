<?php

class ErrorHandler {
    function __construct() {
        require_once(dirname(__FILE__) . '/constants.php');
        require_once(dirname(__FILE__) . '/Logger.php');
    }

    function throw_error($error, $echo = true) {
        $errorOutput = "[ERROR: {$error['error_code']}] {$error['error_text']}";
        $logText = $errorOutput . PHP_EOL;
        Logger::log_to_file($logText, ERROR_LOG_LOCATION);

        if($echo) {
          http_response_code($error['http_code']);
          echo "<div class='message error'>$errorOutput</div>";
          die();
        }
    }
}
