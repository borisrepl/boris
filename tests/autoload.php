<?php
/*
 * Run individual unit tests via:
 *
 * phpunit --bootstrap tests/autoload.php --coverage-html ./reports tests/path/to/MyTest
 *
 * Run them all via:
 *
 * phpunit --bootstrap tests/autoload.php -c tests.xml --coverage-html ./reports
 *
 * (You can leave off "--coverage-html ./reports" off of these commands if you don't
 * have the xdebug extension installed.)
 *
 */

// Register all the classes
require_once(__DIR__.'/../lib/autoload.php');

// Set timezone for date functions
date_default_timezone_set('America/Chicago');

require (__DIR__.'/test_credentials.php');
