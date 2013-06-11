<?php

// include archive.php, and let 'er rip
define('PIWIK_ARCHIVE_CRON_TEST_MODE', true);
require realpath(dirname(__FILE__)) . "/../../../misc/cron/archive.php";

