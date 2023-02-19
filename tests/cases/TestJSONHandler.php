<?php
/** 
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details 
 */

declare(strict_types=1);
namespace MensBeam\Catcher\Test;
use MensBeam\Catcher;
use MensBeam\Catcher\{
    Error,
    Handler,
    JSONHandler,
    ThrowableController
};


class TestJSONHandler extends \PHPUnit\Framework\TestCase {
    /**
     * @covers \MensBeam\Catcher\JSONHandler::dispatchCallback
     * 
     * @covers \MensBeam\Catcher\Error::__construct
     * @covers \MensBeam\Catcher\Handler::__construct
     * @covers \MensBeam\Catcher\Handler::buildOutputArray
     * @covers \MensBeam\Catcher\Handler::dispatch
     * @covers \MensBeam\Catcher\Handler::handle
     * @covers \MensBeam\Catcher\Handler::handleCallback
     * @covers \MensBeam\Catcher\ThrowableController::__construct
     * @covers \MensBeam\Catcher\ThrowableController::getErrorType
     * @covers \MensBeam\Catcher\ThrowableController::getPrevious
     * @covers \MensBeam\Catcher\ThrowableController::getThrowable
     */
    public function testMethod_dispatchCallback(): void {
        // Not much left to cover; just need to test silent output
        $c = new ThrowableController(new \Exception(message: 'Ook!', previous: new Error(message: 'Eek!', code: \E_USER_ERROR, previous: new Error(message: 'Ack!'))));
        $h = new JSONHandler([
            'silent' => true,
            'outputToStderr' => false
        ]);
        $o = $h->handle($c);
        
        ob_start();
        $h->dispatch();
        $o = ob_get_clean();
        $this->assertEmpty($o);
    }
}