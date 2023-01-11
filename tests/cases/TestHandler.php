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
    PlainTextHandler,
    JSONHandler,
    ThrowableController
};
use Eloquent\Phony\Phpunit\Phony;


class TestHandler extends \PHPUnit\Framework\TestCase {
    /**
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     */
    public function testMethod___construct__exception(): void {
        $this->expectException(\RangeException::class);
        new PlainTextHandler([ 'httpCode' => 42 ]);
    }

    /**
     * @covers \MensBeam\Foundation\Catcher\Handler::cleanOutputThrowable
     * 
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     * @covers \MensBeam\Foundation\Catcher\Handler::buildOutputArray
     * @covers \MensBeam\Foundation\Catcher\Handler::dispatch
     * @covers \MensBeam\Foundation\Catcher\Handler::handle
     * @covers \MensBeam\Foundation\Catcher\Handler::handleCallback
     * @covers \MensBeam\Foundation\Catcher\Handler::print
     * @covers \MensBeam\Foundation\Catcher\JSONHandler::dispatchCallback
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::__construct
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getErrorType
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getFrames
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getPrevious
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getThrowable
     */
    public function testMethod__cleanOutputThrowable(): void {
        // Just need to test coverage here; TestJSONHandler covers this one thoroughly.
        $c = new ThrowableController(new \Exception(message: 'Ook!', previous: new \Error('Eek!')));
        $h = new JSONHandler([ 
            'outputBacktrace' => true,
            'outputToStderr' => false
        ]);
        $o = $h->handle($c);

        ob_start();
        $h->dispatch();
        ob_end_clean();

        $this->assertTrue(isset($o['frames']));
    }

    /**
     * @covers \MensBeam\Foundation\Catcher\Handler::handle
     * 
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     * @covers \MensBeam\Foundation\Catcher\Handler::buildOutputArray
     * @covers \MensBeam\Foundation\Catcher\Handler::handleCallback
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::__construct
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getErrorType
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getFrames
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getPrevious
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getThrowable
     */
    public function testMethod__handle(): void {
        // Just need to test backtrace handling. The rest has already been covered by prior tests.
        $c = new ThrowableController(new \Exception(message: 'Ook!', previous: new \Error(message: 'Eek!', previous: new Error(message: 'Eek!', code: \E_USER_ERROR))));
        $h = new HTMLHandler([ 
            'outputBacktrace' => true,
            'outputToStderr' => true
        ]);
        $this->assertTrue(isset($h->handle($c)['frames']));
    }

    /**
     * @covers \MensBeam\Foundation\Catcher\Handler::getOption
     * 
     * @covers \MensBeam\Foundation\Catcher::__construct
     * @covers \MensBeam\Foundation\Catcher::handleError
     * @covers \MensBeam\Foundation\Catcher::handleThrowable
     * @covers \MensBeam\Foundation\Catcher::isErrorFatal
     * @covers \MensBeam\Foundation\Catcher::pushHandler
     * @covers \MensBeam\Foundation\Catcher::register
     * @covers \MensBeam\Foundation\Catcher::unregister
     * @covers \MensBeam\Foundation\Catcher\Error::__construct
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     * @covers \MensBeam\Foundation\Catcher\Handler::dispatch
     * @covers \MensBeam\Foundation\Catcher\Handler::handle
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::handleCallback
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

    /**
     * @covers \MensBeam\Foundation\Catcher\Handler::setOption
     * 
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     */
    public function testMethod__setOption(): void {
        $h = new PlainTextHandler([ 'forceExit' => true, 'silent' => true ]);
        $h->setOption('forceExit', false);
        $r = new \ReflectionProperty($h, '_forceExit');
        $r->setAccessible(true);
        $this->assertFalse($r->getValue($h));

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
     * @covers \MensBeam\Foundation\Catcher\Handler::print
     * 
     * @covers \MensBeam\Foundation\Catcher::__construct
     * @covers \MensBeam\Foundation\Catcher::handleError
     * @covers \MensBeam\Foundation\Catcher::isErrorFatal
     * @covers \MensBeam\Foundation\Catcher::handleThrowable
     * @covers \MensBeam\Foundation\Catcher::pushHandler
     * @covers \MensBeam\Foundation\Catcher::register
     * @covers \MensBeam\Foundation\Catcher::unregister
     * @covers \MensBeam\Foundation\Catcher\Error::__construct
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     * @covers \MensBeam\Foundation\Catcher\Handler::dispatch
     * @covers \MensBeam\Foundation\Catcher\Handler::handle
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::dispatchCallback
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::handleCallback
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::serializeOutputThrowable
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