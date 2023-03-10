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
    PlainTextHandler,
    ThrowableController
};


class TestThrowableController extends \PHPUnit\Framework\TestCase {
    /**
     * @covers \MensBeam\Catcher\ThrowableController::getErrorType
     * 
     * @covers \MensBeam\Catcher::__construct
     * @covers \MensBeam\Catcher::getLastThrowable
     * @covers \MensBeam\Catcher::handleError
     * @covers \MensBeam\Catcher::isErrorFatal
     * @covers \MensBeam\Catcher::handleThrowable
     * @covers \MensBeam\Catcher::pushHandler
     * @covers \MensBeam\Catcher::register
     * @covers \MensBeam\Catcher::unregister
     * @covers \MensBeam\Catcher\Error::__construct
     * @covers \MensBeam\Catcher\Handler::__construct
     * @covers \MensBeam\Catcher\Handler::dispatch
     * @covers \MensBeam\Catcher\Handler::handle
     * @covers \MensBeam\Catcher\Handler::print
     * @covers \MensBeam\Catcher\PlainTextHandler::dispatchCallback
     * @covers \MensBeam\Catcher\PlainTextHandler::handleCallback
     * @covers \MensBeam\Catcher\PlainTextHandler::serializeOutputThrowable
     * @covers \MensBeam\Catcher\ThrowableController::__construct
     * @covers \MensBeam\Catcher\ThrowableController::getPrevious
     * @covers \MensBeam\Catcher\ThrowableController::getThrowable
     */
    public function testMethod_getErrorType(): void {
        $c = new Catcher(new PlainTextHandler([ 'outputToStderr' => false ]));
        $c->preventExit = true;
        $c->throwErrors = false;
        ob_start();
        trigger_error('Ook!', \E_USER_DEPRECATED);
        ob_end_clean();
        $this->assertSame(\E_USER_DEPRECATED, $c->getLastThrowable()->getCode());
        $c->unregister();

        $c = new Catcher(new PlainTextHandler([ 'outputToStderr' => false ]));
        $c->preventExit = true;
        $c->throwErrors = false;
        ob_start();
        trigger_error('Ook!', \E_USER_WARNING);
        ob_end_clean();
        $this->assertSame(\E_USER_WARNING, $c->getLastThrowable()->getCode());
        $c->unregister();

        $c = new Catcher(new PlainTextHandler([ 'outputToStderr' => false ]));
        $c->preventExit = true;
        $c->throwErrors = false;
        ob_start();
        trigger_error('Ook!', \E_USER_NOTICE);
        ob_end_clean();
        $this->assertSame(\E_USER_NOTICE, $c->getLastThrowable()->getCode());
        $c->unregister();

        $c = new Catcher(new PlainTextHandler([ 'outputToStderr' => false ]));
        $c->preventExit = true;
        $c->throwErrors = false;
        ob_start();
        trigger_error('Ook!', \E_USER_ERROR);
        ob_end_clean();
        $this->assertSame(\E_USER_ERROR, $c->getLastThrowable()->getCode());
        $c->unregister();

        // These others will be tested by invoking the method directly
        $c = new ThrowableController(new Error('Ook!', \E_ERROR));
        $this->assertSame('PHP Fatal Error', $c->getErrorType());
        $c = new ThrowableController(new Error('Ook!', \E_WARNING));
        $this->assertSame('PHP Warning', $c->getErrorType());
        $c = new ThrowableController(new Error('Ook!', \E_PARSE));
        $this->assertSame('PHP Parsing Error', $c->getErrorType());
        $c = new ThrowableController(new Error('Ook!', \E_NOTICE));
        $this->assertSame('PHP Notice', $c->getErrorType());
        $c = new ThrowableController(new Error('Ook!', \E_DEPRECATED));
        $this->assertSame('Deprecated', $c->getErrorType());
        $c = new ThrowableController(new Error('Ook!', \E_CORE_ERROR));
        $this->assertSame('PHP Core Error', $c->getErrorType());
        $c = new ThrowableController(new Error('Ook!', \E_CORE_WARNING));
        $this->assertSame('PHP Core Warning', $c->getErrorType());
        $c = new ThrowableController(new Error('Ook!', \E_COMPILE_ERROR));
        $this->assertSame('Compile Error', $c->getErrorType());
        $c = new ThrowableController(new Error('Ook!', \E_COMPILE_WARNING));
        $this->assertSame('Compile Warning', $c->getErrorType());
        $c = new ThrowableController(new Error('Ook!', \E_STRICT));
        $this->assertSame('Runtime Notice', $c->getErrorType());
        $c = new ThrowableController(new Error('Ook!', \E_RECOVERABLE_ERROR));
        $this->assertSame('Recoverable Error', $c->getErrorType());
        $c = new ThrowableController(new Error('Ook!'));
        $this->assertNull($c->getErrorType());
        $c = new ThrowableController(new \Exception('Ook!'));
        $this->assertNull($c->getErrorType());

        // For code coverage purposes.
        $this->assertNull($c->getErrorType());
    }

    /**
     * @covers \MensBeam\Catcher\ThrowableController::getFrames
     * 
     * @covers \MensBeam\Catcher\ThrowableController::__construct
     * @covers \MensBeam\Catcher\ThrowableController::getErrorType
     * @covers \MensBeam\Catcher\ThrowableController::getPrevious
     */
    public function testMethod_getFrames(): void {
        $f = false;
        try {
            throw new \Exception('Ook!');
        } catch (\Throwable $t) {
            $c = new ThrowableController($t);
            $f = $c->getFrames();
        } finally {
            $this->assertSame(\Exception::class, $f[0]['class']);
        }

        $f = false;
        try {
            throw new Error('Ook!', \E_ERROR);
        } catch (\Throwable $t) {
            $c = new ThrowableController($t);
            $f = $c->getFrames();
        } finally {
            $this->assertSame(Error::class, $f[0]['class']);
        }

        $f = false;
        try {
            throw new \Exception(message: 'Ook!', previous: new Error(message: 'Ook!', code: \E_ERROR, previous: new \Exception('Ook!')));
        } catch (\Throwable $t) {
            $c = new ThrowableController($t);
            $f = $c->getFrames();
        } finally {
            $this->assertSame(\Exception::class, $f[0]['class']);
            $this->assertSame(Error::class, $f[count($f) - 2]['class']);
        }

        $f = false;
        try {
            call_user_func_array(function () {
                throw new \Exception('Ook!');
            }, []);
        } catch (\Throwable $t) {
            $c = new ThrowableController($t);
            $f = $c->getFrames();
        } finally {
            $this->assertSame(\Exception::class, $f[0]['class']);
            $this->assertArrayHasKey('file', $f[2]);
            $this->assertMatchesRegularExpression('/TestThrowableController\.php$/', $f[2]['file']);
            $this->assertSame('call_user_func_array', $f[2]['function']);
            $this->assertArrayHasKey('line', $f[2]);
            $this->assertNotSame(0, $f[2]['line']);
        }

        // This is mostly here for code coverage: to delete userland error handling from 
        // the backtrace
        $f = false;
        try {
            function ook() {}
            call_user_func('ook', []);
        } catch (\Throwable $t) {
            $c = new ThrowableController($t);
            $f = $c->getFrames();
        } finally {
            $this->assertSame(\TypeError::class, $f[0]['class']);
        }

        // For code coverage purposes; should use the cached value instead of calculating 
        // the frames over again.
        $f = $c->getFrames();

        // Lastly test for a RangeException
        $this->expectException(\RangeException::class);
        $c = new ThrowableController(new \Exception('Ook!'));
        $c->getFrames(-1);
    }
}