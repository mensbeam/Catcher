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
    HTMLHandler,
    ThrowableController
};


class TestHTMLHandler extends \PHPUnit\Framework\TestCase {
    /**
     * @covers \MensBeam\Catcher\HTMLHandler::__construct
     * 
     * @covers \MensBeam\Catcher\Handler::__construct
     */
    public function testMethod___construct__exception(): void {
        $this->expectException(\InvalidArgumentException::class);
        new HTMLHandler([ 'errorPath' => '/html/body/fail' ]);
    }

    /**
     * @covers \MensBeam\Catcher\HTMLHandler::buildOutputThrowable
     * 
     * @covers \MensBeam\Catcher\Error::__construct
     * @covers \MensBeam\Catcher\Handler::__construct
     * @covers \MensBeam\Catcher\Handler::buildOutputArray
     * @covers \MensBeam\Catcher\Handler::handle
     * @covers \MensBeam\Catcher\HTMLHandler::__construct
     * @covers \MensBeam\Catcher\HTMLHandler::dispatchCallback
     * @covers \MensBeam\Catcher\HTMLHandler::handleCallback
     * @covers \MensBeam\Catcher\HTMLHandler::serializeDocument
     * @covers \MensBeam\Catcher\ThrowableController::__construct
     * @covers \MensBeam\Catcher\ThrowableController::getErrorType
     * @covers \MensBeam\Catcher\ThrowableController::getFrames
     * @covers \MensBeam\Catcher\ThrowableController::getPrevious
     * @covers \MensBeam\Catcher\ThrowableController::getThrowable
     */
    public function testMethod_buildOutputThrowable(): void {
        $c = new ThrowableController(new \Exception(message: 'Ook!', previous: new Error(message: 'Eek!', code: \E_USER_ERROR, previous: new Error(message: 'Ack!'))));
        $h = new HTMLHandler([
            'backtraceArgFrameLimit' => 1,
            'outputBacktrace' => true,
            'outputToStderr' => false
        ]);
        $o = $h->handle($c);
        $this->assertSame(Handler::CONTINUE, $o['controlCode']);

        ob_start();
        $h->dispatch();
        ob_end_clean();
    }

    /**
     * @covers \MensBeam\Catcher\HTMLHandler::dispatchCallback
     * 
     * @covers \MensBeam\Catcher\Handler::__construct
     * @covers \MensBeam\Catcher\Handler::buildOutputArray
     * @covers \MensBeam\Catcher\Handler::dispatch
     * @covers \MensBeam\Catcher\Handler::handle
     * @covers \MensBeam\Catcher\Handler::handleCallback
     * @covers \MensBeam\Catcher\HTMLHandler::__construct
     * @covers \MensBeam\Catcher\ThrowableController::__construct
     * @covers \MensBeam\Catcher\ThrowableController::getPrevious
     * @covers \MensBeam\Catcher\ThrowableController::getThrowable
     */
    public function testMethod_dispatchCallback(): void {
        $c = new ThrowableController(new \Exception(message: 'Ook!'));
        $h = new HTMLHandler([
            'backtraceArgFrameLimit' => 1,
            'outputToStderr' => false,
            'silent' => true
        ]);
        $h->handle($c);

        ob_start();
        $h->dispatch();
        $o = ob_get_clean();
        $this->assertEmpty($o);
    }
}