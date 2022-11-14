<?php
/** 
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details 
 */

declare(strict_types=1);
namespace MensBeam\Foundation\Catcher\Test;
use MensBeam\Foundation\Catcher;
use MensBeam\Foundation\Catcher\{
    Error,
    PlainTextHandler
};
require_once('../../vendor/autoload.php');

Catcher::$preventExit = true;
$c = new Catcher(new PlainTextHandler([ 'httpCode' => 200 ]));
trigger_error('Ook!', \E_USER_WARNING);