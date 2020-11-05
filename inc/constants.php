<?php

// This defines where the locations of the logs are. dirname(__FILE__) is the current directory.
// Please make sure the file exists and has write permissions.
define('ERROR_LOG_LOCATION', dirname(__FILE__) . '/../log/errors.log');
define('SQL_LOG_LOCATION', dirname(__FILE__) . '/../log/sql.log');

// Errors
const ERROR_VIEW_NOT_FOUND = array(
    'http_code' => '500',
    'error_code' => 'API_ERROR',
    'error_text' => 'The file to display the requested view was not found.'
);
const ERROR_INCORRECT_DATE_FORMAT = array(
    'http_code' => '500',
    'error_code' => 'API_ERROR',
    'error_text' => 'You have set the wrong date format, or the date was malformed.'
);
const ERROR_INCORRECT_DB_DETAILS = array(
    'http_code' => '500',
    'error_code' => 'INCORRECT_DB_DETAILS',
    'error_text' => 'Could not connect to the database with the given credentials. Please check config.php.'
);
