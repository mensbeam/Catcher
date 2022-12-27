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
    Handler,
    HTMLHandler,
    ThrowableController
};


class TestHTMLHandler extends \PHPUnit\Framework\TestCase {
    /**
     * @covers \MensBeam\Foundation\Catcher\HTMLHandler::__construct
     * 
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     */
    public function testMethod___construct__exception(): void {
        $this->expectException(\InvalidArgumentException::class);
        new HTMLHandler([ 'errorPath' => '/html/body/fail' ]);
    }

    /**
     * @covers \MensBeam\Foundation\Catcher\HTMLHandler::buildOutputThrowable
     * 
     * @covers \MensBeam\Foundation\Catcher\Error::__construct
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     * @covers \MensBeam\Foundation\Catcher\Handler::buildOutputArray
     * @covers \MensBeam\Foundation\Catcher\Handler::handle
     * @covers \MensBeam\Foundation\Catcher\HTMLHandler::__construct
     * @covers \MensBeam\Foundation\Catcher\HTMLHandler::dispatchCallback
     * @covers \MensBeam\Foundation\Catcher\HTMLHandler::handleCallback
     * @covers \MensBeam\Foundation\Catcher\HTMLHandler::serializeDocument
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::__construct
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getErrorType
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getFrames
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getPrevious
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getThrowable
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
     * @covers \MensBeam\Foundation\Catcher\HTMLHandler::dispatchCallback
     * 
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     * @covers \MensBeam\Foundation\Catcher\Handler::buildOutputArray
     * @covers \MensBeam\Foundation\Catcher\Handler::dispatch
     * @covers \MensBeam\Foundation\Catcher\Handler::handle
     * @covers \MensBeam\Foundation\Catcher\Handler::handleCallback
     * @covers \MensBeam\Foundation\Catcher\HTMLHandler::__construct
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::__construct
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getPrevious
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getThrowable
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