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
    HTMLHandler,
    JSONHandler,
    PlainTextHandler
};
use Eloquent\Phony\Phpunit\Phony;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class TestHandler extends \PHPUnit\Framework\TestCase {
    /**
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     */
    public function testMethod___construct__exception(): void {
        $this->expectException(\InvalidArgumentException::class);
        new PlainTextHandler([ 'httpCode' => 42 ]);
    }

    /**
     * @covers \MensBeam\Foundation\Catcher\Handler::getControlCode
     * 
     * @covers \MensBeam\Foundation\Catcher::__construct
     * @covers \MensBeam\Foundation\Catcher::handleError
     * @covers \MensBeam\Foundation\Catcher::handleThrowable
     * @covers \MensBeam\Foundation\Catcher::pushHandler
     * @covers \MensBeam\Foundation\Catcher::register
     * @covers \MensBeam\Foundation\Catcher::unregister
     * @covers \MensBeam\Foundation\Catcher\Error::__construct
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     * @covers \MensBeam\Foundation\Catcher\Handler::dispatch
     * @covers \MensBeam\Foundation\Catcher\Handler::handle
     * @covers \MensBeam\Foundation\Catcher\Handler::getOutputCode
     * @covers \MensBeam\Foundation\Catcher\HandlerOutput::__construct
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::dispatchCallback
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::handleCallback
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::serializeThrowable
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::__construct
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getErrorType
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getPrevious
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getThrowable
     */
    public function testMethod__getControlCode(): void {
        // Just need to test forceExit for coverage purposes
        $c = new Catcher(new PlainTextHandler([ 'forceExit' => true, 'silent' => true ]));
        $c->preventExit = true;
        $c->throwErrors = false;
        trigger_error('Ook!', \E_USER_ERROR);
        $this->assertSame(\E_USER_ERROR, $c->getLastThrowable()->getCode());
        $c->unregister();
    }

    /**
     * @covers \MensBeam\Foundation\Catcher\Handler::getOption
     * 
     * @covers \MensBeam\Foundation\Catcher::__construct
     * @covers \MensBeam\Foundation\Catcher::handleError
     * @covers \MensBeam\Foundation\Catcher::handleThrowable
     * @covers \MensBeam\Foundation\Catcher::pushHandler
     * @covers \MensBeam\Foundation\Catcher::register
     * @covers \MensBeam\Foundation\Catcher::unregister
     * @covers \MensBeam\Foundation\Catcher\Error::__construct
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     * @covers \MensBeam\Foundation\Catcher\Handler::dispatch
     * @covers \MensBeam\Foundation\Catcher\Handler::getControlCode
     * @covers \MensBeam\Foundation\Catcher\Handler::getOutputCode
     * @covers \MensBeam\Foundation\Catcher\Handler::handle
     * @covers \MensBeam\Foundation\Catcher\HandlerOutput::__construct
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::dispatchCallback
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::handleCallback
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::serializeThrowable
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::__construct
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getErrorType
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getPrevious
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getThrowable
     */
    public function testMethod__getOption(): void {
        $h = new PlainTextHandler([ 'forceExit' => true, 'silent' => true ]);
        $this->assertTrue($h->getOption('forceExit'));

        $c = new Catcher($h);
        $c->preventExit = true;
        $c->throwErrors = false;
        $this->assertNull($h->getOption('ook'));
        $c->unregister();
    }

    public function testMethod__setOption(): void {
        $h = new PlainTextHandler([ 'forceExit' => true, 'silent' => true ]);
        $h->setOption('forceExit', false);
        $r = new \ReflectionProperty($h, '_forceExit');
        $r->setAccessible(true);
        $this->assertFalse($r->getValue($h));

        //$h = Phony::partialMock(PlainTextHandler::class, [ [ 'silent' => true ] ]);
        $m = Phony::partialMock(Catcher::class, [ 
            $h
        ]);
        $c = $m->get();
        $c->preventExit = true;
        $c->throwErrors = false;

        $h->setOption('ook', 'FAIL');
        $m->handleError->called();

        $c->unregister();
    }

    /**
     * @covers \MensBeam\Foundation\Catcher\Handler::getOutputCode
     * 
     * @covers \MensBeam\Foundation\Catcher::__construct
     * @covers \MensBeam\Foundation\Catcher::handleError
     * @covers \MensBeam\Foundation\Catcher::handleThrowable
     * @covers \MensBeam\Foundation\Catcher::pushHandler
     * @covers \MensBeam\Foundation\Catcher::register
     * @covers \MensBeam\Foundation\Catcher::unregister
     * @covers \MensBeam\Foundation\Catcher\Error::__construct
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     * @covers \MensBeam\Foundation\Catcher\Handler::dispatch
     * @covers \MensBeam\Foundation\Catcher\Handler::getControlCode
     * @covers \MensBeam\Foundation\Catcher\Handler::handle
     * @covers \MensBeam\Foundation\Catcher\HandlerOutput::__construct
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::dispatchCallback
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::handleCallback
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::serializeThrowable
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::__construct
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getErrorType
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getPrevious
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getThrowable
     */
    public function testMethod__getOutputCode(): void {
        // Just need to test forceOutputNow for coverage purposes
        $c = new Catcher(new PlainTextHandler([ 'forceOutputNow' => true, 'silent' => true ]));
        $c->preventExit = true;
        $c->throwErrors = false;
        trigger_error('Ook!', \E_USER_ERROR);
        $this->assertSame(\E_USER_ERROR, $c->getLastThrowable()->getCode());
        $c->unregister();
    }

    /**
     * @covers \MensBeam\Foundation\Catcher\Handler::print
     * 
     * @covers \MensBeam\Foundation\Catcher::__construct
     * @covers \MensBeam\Foundation\Catcher::handleError
     * @covers \MensBeam\Foundation\Catcher::handleThrowable
     * @covers \MensBeam\Foundation\Catcher::pushHandler
     * @covers \MensBeam\Foundation\Catcher::register
     * @covers \MensBeam\Foundation\Catcher::unregister
     * @covers \MensBeam\Foundation\Catcher\Error::__construct
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     * @covers \MensBeam\Foundation\Catcher\Handler::dispatch
     * @covers \MensBeam\Foundation\Catcher\Handler::getControlCode
     * @covers \MensBeam\Foundation\Catcher\Handler::getOutputCode
     * @covers \MensBeam\Foundation\Catcher\Handler::handle
     * @covers \MensBeam\Foundation\Catcher\HandlerOutput::__construct
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::dispatchCallback
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::handleCallback
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::serializeThrowable
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::__construct
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getErrorType
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getPrevious
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getThrowable
     */
    public function testMethod__print(): void {
        // Just need to test forceOutputNow for coverage purposes
        $c = new Catcher(new PlainTextHandler([ 'forceOutputNow' => true, 'outputToStderr' => false ]));
        $c->preventExit = true;
        $c->throwErrors = false;
        ob_start();
        trigger_error('Ook!', \E_USER_NOTICE);
        ob_end_clean();
        $this->assertSame(\E_USER_NOTICE, $c->getLastThrowable()->getCode());
        $c->unregister();
    }
}