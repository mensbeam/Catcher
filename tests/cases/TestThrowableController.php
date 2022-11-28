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
    PlainTextHandler,
    ThrowableController
};


class TestThrowableController extends \PHPUnit\Framework\TestCase {
    /**
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getErrorType
     * 
     * @covers \MensBeam\Foundation\Catcher::__construct
     * @covers \MensBeam\Foundation\Catcher::getLastThrowable
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
     * @covers \MensBeam\Foundation\Catcher\Handler::print
     * @covers \MensBeam\Foundation\Catcher\HandlerOutput::__construct
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::dispatchCallback
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::handleCallback
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::serializeThrowable
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::__construct
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getPrevious
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getThrowable
     */
    public function testMethod_getErrorType(): void {
        $c = new Catcher(new PlainTextHandler([ 'outputToStderr' => false ]));
        ob_start();
        trigger_error('Ook!', \E_USER_DEPRECATED);
        ob_end_clean();
        $this->assertEquals(\E_USER_DEPRECATED, $c->getLastThrowable()->getCode());
        $c->unregister();

        $c = new Catcher(new PlainTextHandler([ 'outputToStderr' => false ]));
        ob_start();
        trigger_error('Ook!', \E_USER_WARNING);
        ob_end_clean();
        $this->assertEquals(\E_USER_WARNING, $c->getLastThrowable()->getCode());
        $c->unregister();

        // These others will be tested by invoking the method directly
        $c = new ThrowableController(new Error('Ook!', \E_ERROR));
        $this->assertEquals('PHP Fatal Error', $c->getErrorType());
        $c = new ThrowableController(new Error('Ook!', \E_WARNING));
        $this->assertEquals('PHP Warning', $c->getErrorType());
        $c = new ThrowableController(new Error('Ook!', \E_PARSE));
        $this->assertEquals('PHP Parsing Error', $c->getErrorType());
        $c = new ThrowableController(new Error('Ook!', \E_NOTICE));
        $this->assertEquals('PHP Notice', $c->getErrorType());
        $c = new ThrowableController(new Error('Ook!', \E_DEPRECATED));
        $this->assertEquals('Deprecated', $c->getErrorType());
        $c = new ThrowableController(new Error('Ook!', \E_CORE_ERROR));
        $this->assertEquals('PHP Core Error', $c->getErrorType());
        $c = new ThrowableController(new Error('Ook!', \E_CORE_WARNING));
        $this->assertEquals('PHP Core Warning', $c->getErrorType());
        $c = new ThrowableController(new Error('Ook!', \E_COMPILE_ERROR));
        $this->assertEquals('Compile Error', $c->getErrorType());
        $c = new ThrowableController(new Error('Ook!', \E_COMPILE_WARNING));
        $this->assertEquals('Compile Warning', $c->getErrorType());
        $c = new ThrowableController(new Error('Ook!', \E_STRICT));
        $this->assertEquals('Runtime Notice', $c->getErrorType());
        $c = new ThrowableController(new Error('Ook!', \E_RECOVERABLE_ERROR));
        $this->assertEquals('Recoverable Error', $c->getErrorType());
        $c = new ThrowableController(new Error('Ook!'));
        $this->assertNull($c->getErrorType());
        $c = new ThrowableController(new \Exception('Ook!'));
        $this->assertNull($c->getErrorType());
    }

    /**
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getFrames
     * 
     */
    public function testMethod_getFrames(): void {
        $f = false;
        try {
            throw new \Exception('Ook!');
        } catch (\Throwable $t) {
            $c = new ThrowableController($t);
            $f = $c->getFrames();
        } finally {
            $this->assertEquals('Exception', $f[0]['class']);
        }

        $f = false;
        try {
            throw new Error('Ook!', \E_ERROR);
        } catch (\Throwable $t) {
            $c = new ThrowableController($t);
            $f = $c->getFrames();
        } finally {
            $this->assertEquals(Error::class, $f[0]['class']);
        }
    }
}