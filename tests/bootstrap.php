<?php
/** @license MIT
 * Copyright 2017 , Dustin Wilson, J. King et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace MensBeam\Foundation;
use MensBeam\Foundation\Catcher;

ini_set('memory_limit', '-1');
ini_set('zend.assertions', '1');
ini_set('assert.exception', 'true');
error_reporting(\E_ALL);

$cwd = dirname(__DIR__);
$docRoot = "$cwd/tests/docroot";
require_once "$cwd/vendor/autoload.php";

if (function_exists('xdebug_set_filter')) {
    if (defined('XDEBUG_PATH_INCLUDE')) {
        xdebug_set_filter(\XDEBUG_FILTER_CODE_COVERAGE, \XDEBUG_PATH_INCLUDE, [ "$cwd/lib/" ]);
    } else {
        xdebug_set_filter(\XDEBUG_FILTER_CODE_COVERAGE, \XDEBUG_PATH_WHITELIST, [ "$cwd/lib/" ]);
    }
}