<?php

// include Piwik
define('PIWIK_INCLUDE_PATH', realpath(dirname(__FILE__) . "/../../.."));
define('PIWIK_USER_PATH', PIWIK_INCLUDE_PATH);
define('PIWIK_ENABLE_DISPATCH', false);
define('PIWIK_ENABLE_ERROR_HANDLER', false);
define('PIWIK_ENABLE_SESSION_START', false);
define('PIWIK_MODE_ARCHIVE', true);
require_once PIWIK_INCLUDE_PATH . "/index.php";
require_once PIWIK_INCLUDE_PATH . "/core/API/Request.php";

// get segments parameter
$segmentsArgPrefix = '--segments=';

$segmentsArg = '[]';
foreach ($argv as $arg) {
    if (strpos($arg, $segmentsArgPrefix) === 0) {
        $segmentsArg = substr($arg, strlen($segmentsArgPrefix));
        break;
    }
}

if (strpos($segmentsArg, '"') === 0) {
    $segmentsArg = substr($arg, 1);
}
if (strrpos($segmentsArg, '"') === strlen($segmentsArg) - 1) {
    $segmentsArg = substr($arg, strlen($segmentsArg) - 1);
}

$segments = Piwik_Common::json_decode($segmentsArg);


// include archive.php, and let 'er rip
define('PIWIK_ARCHIVE_CRON_TEST_MODE', true);
require PIWIK_INCLUDE_PATH . '/misc/cron/archive.php';

