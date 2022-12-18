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

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
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
     * @covers \MensBeam\Foundation\Catcher\HTMLHandler::buildThrowable
     * 
     * @covers \MensBeam\Foundation\Catcher\Error::__construct
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     * @covers \MensBeam\Foundation\Catcher\Handler::getControlCode
     * @covers \MensBeam\Foundation\Catcher\Handler::getOutputCode
     * @covers \MensBeam\Foundation\Catcher\Handler::handle
     * @covers \MensBeam\Foundation\Catcher\HandlerOutput::__construct
     * @covers \MensBeam\Foundation\Catcher\HTMLHandler::__construct
     * @covers \MensBeam\Foundation\Catcher\HTMLHandler::handleCallback
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::__construct
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getErrorType
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getFrames
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getPrevious
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getThrowable
     */
    public function testMethod_buildThrowable(): void {
        $c = new ThrowableController(new \Exception(message: 'Ook!', previous: new Error(message: 'Eek!', code: \E_USER_ERROR, previous: new Error(message: 'Ack!'))));
        $h = new HTMLHandler([
            'outputBacktrace' => true,
            'outputTime' => false
        ]);
        $o = $h->handle($c);
        $this->assertSame(Handler::CONTINUE, $o->controlCode);
        $this->assertInstanceOf(\DOMDocumentFragment::class, $o->output);
    }

    /**
     * @covers \MensBeam\Foundation\Catcher\HTMLHandler::dispatchCallback
     * 
     * @covers \MensBeam\Foundation\Catcher\Error::__construct
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     * @covers \MensBeam\Foundation\Catcher\Handler::dispatch
     * @covers \MensBeam\Foundation\Catcher\Handler::getControlCode
     * @covers \MensBeam\Foundation\Catcher\Handler::getOutputCode
     * @covers \MensBeam\Foundation\Catcher\Handler::handle
     * @covers \MensBeam\Foundation\Catcher\Handler::print
     * @covers \MensBeam\Foundation\Catcher\Handler::setOption
     * @covers \MensBeam\Foundation\Catcher\HandlerOutput::__construct
     * @covers \MensBeam\Foundation\Catcher\HTMLHandler::__construct
     * @covers \MensBeam\Foundation\Catcher\HTMLHandler::buildThrowable
     * @covers \MensBeam\Foundation\Catcher\HTMLHandler::handleCallback
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::__construct
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getErrorType
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getPrevious
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getThrowable
     */
    public function testMethod_dispatchCallback(): void {
        $c = new ThrowableController(new \Exception(message: 'Ook!', previous: new Error(message: 'Eek!', code: \E_USER_ERROR, previous: new \Error(message: 'Ack!'))));
        $h = new HTMLHandler([
            'outputToStderr' => false
        ]);
        $h->handle($c);

        ob_start();
        $h->dispatch();
        $o = ob_get_clean();
        $this->assertNotNull($o);

        $h->setOption('silent', true);
        $h->handle($c);
        $h->dispatch();
    }
}