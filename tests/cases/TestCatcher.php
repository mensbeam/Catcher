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


class TestCatcher extends \PHPUnit\Framework\TestCase {
    /**
     * @covers \MensBeam\Foundation\Catcher::__construct
     * 
     * @covers \MensBeam\Foundation\Catcher::getHandlers
     * @covers \MensBeam\Foundation\Catcher::pushHandler
     * @covers \MensBeam\Foundation\Catcher::register
     * @covers \MensBeam\Foundation\Catcher::unregister
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     * @covers \MensBeam\Foundation\Catcher\HTMLHandler::__construct
     */
    public function testMethod___construct(): void {
        $c = new Catcher();
        $this->assertSame('MensBeam\Foundation\Catcher', $c::class);
        $this->assertEquals(1, count($c->getHandlers()));
        $this->assertSame(PlainTextHandler::class, $c->getHandlers()[0]::class);
        $c->unregister();

        $c = new Catcher(
            new PlainTextHandler(),
            new HTMLHandler(),
            new JSONHandler()
        );
        $this->assertSame('MensBeam\Foundation\Catcher', $c::class);
        $this->assertEquals(3, count($c->getHandlers()));
        $h = $c->getHandlers();
        $this->assertSame(PlainTextHandler::class, $h[0]::class);
        $this->assertSame(HTMLHandler::class, $h[1]::class);
        $this->assertSame(JSONHandler::class, $h[2]::class);
        $c->unregister();
    }

    /**
     * @covers \MensBeam\Foundation\Catcher::getLastThrowable
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
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getPrevious
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getThrowable
     */
    public function testMethod_getLastThrowable(): void {
        $c = new Catcher(new PlainTextHandler([ 'silent' => true ]));
        trigger_error('Ook!', \E_USER_WARNING);
        $this->assertEquals(\E_USER_WARNING, $c->getLastThrowable()->getCode());
        $c->unregister();
    }

    /**
     * @covers \MensBeam\Foundation\Catcher::pushHandler
     * 
     * @covers \MensBeam\Foundation\Catcher::__construct
     * @covers \MensBeam\Foundation\Catcher::register
     * @covers \MensBeam\Foundation\Catcher::unregister
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     */
    public function testMethod_pushHandler(): void {
        $e = null;
        set_error_handler(function($errno) use (&$e) {
            $e = $errno;
        });

        $h = new PlainTextHandler();
        $c = new Catcher($h, $h);
        $c->unregister();
        $this->assertEquals(\E_USER_WARNING, $e);
        $e = null;

        $c = new Catcher();
        $c->unregister();
        $c->pushHandler($h, $h);
        $this->assertEquals(\E_USER_WARNING, $e);

        restore_error_handler();

        $c = new Catcher();
        $c->unregister();

        $e = null;
        try {
            $c->pushHandler();
        } catch (\Throwable $t) {
            $e = $t::class;
        } finally {
            $this->assertSame(\ArgumentCountError::class, $e);
        }
    }

    /**
     * @covers \MensBeam\Foundation\Catcher::popHandler
     * 
     * @covers \MensBeam\Foundation\Catcher::__construct
     * @covers \MensBeam\Foundation\Catcher::pushHandler
     * @covers \MensBeam\Foundation\Catcher::register
     * @covers \MensBeam\Foundation\Catcher::unregister
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     * @covers \MensBeam\Foundation\Catcher\HTMLHandler::__construct
     */
    public function testMethod_popHandler(): void {
        $h = [
            new HTMLHandler(),
            new PlainTextHandler(),
            new JSONHandler()
        ];
        $c = new Catcher(...$h);
        $hh = $c->popHandler();
        $this->assertSame($h[2], $hh);
        $hh = $c->popHandler();
        $this->assertSame($h[1], $hh);

        $e = null;
        try {
            $c->popHandler();
        } catch (\Throwable $t) {
            $e = $t::class;
        } finally {
            $c->unregister();
            $this->assertSame(\Exception::class, $e);
        }
    }

    /**
     * @covers \MensBeam\Foundation\Catcher::isRegistered
     * 
     * @covers \MensBeam\Foundation\Catcher::__construct
     * @covers \MensBeam\Foundation\Catcher::pushHandler
     * @covers \MensBeam\Foundation\Catcher::register
     * @covers \MensBeam\Foundation\Catcher::unregister
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     */
    public function testMethod_register(): void {
        $c = new Catcher();
        $this->assertTrue($c->isRegistered());
        $this->assertFalse($c->register());
        $c->unregister();
        $this->assertFalse($c->isRegistered());
    }

    /**
     * @covers \MensBeam\Foundation\Catcher::setHandlers
     * 
     * @covers \MensBeam\Foundation\Catcher::__construct
     * @covers \MensBeam\Foundation\Catcher::getHandlers
     * @covers \MensBeam\Foundation\Catcher::pushHandler
     * @covers \MensBeam\Foundation\Catcher::register
     * @covers \MensBeam\Foundation\Catcher::unregister
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     */
    public function testMethod_setHandlers(): void {
        $c = new Catcher();
        $c->setHandlers(new PlainTextHandler());
        $h = $c->getHandlers();
        $this->assertEquals(1, count($h));
        $this->assertSame(PlainTextHandler::class, $h[0]::class);
        $c->unregister();
    }

    /**
     * @covers \MensBeam\Foundation\Catcher::shiftHandler
     * 
     * @covers \MensBeam\Foundation\Catcher::__construct
     * @covers \MensBeam\Foundation\Catcher::pushHandler
     * @covers \MensBeam\Foundation\Catcher::register
     * @covers \MensBeam\Foundation\Catcher::unregister
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     * @covers \MensBeam\Foundation\Catcher\HTMLHandler::__construct
     */
    public function testMethod_shiftHandler(): void {
        $h = [
            new HTMLHandler(),
            new PlainTextHandler(),
            new JSONHandler()
        ];
        $c = new Catcher(...$h);
        $c->unregister();
        $hh = $c->shiftHandler();
        $this->assertSame($h[0], $hh);
        $hh = $c->shiftHandler();
        $this->assertSame($h[1], $hh);

        $e = null;
        try {
            $c->shiftHandler();
        } catch (\Throwable $t) {
            $e = $t::class;
        } finally {
            $this->assertSame(\Exception::class, $e);
        }
    }

    /**
     * @covers \MensBeam\Foundation\Catcher::unregister
     * 
     * @covers \MensBeam\Foundation\Catcher::__construct
     * @covers \MensBeam\Foundation\Catcher::pushHandler
     * @covers \MensBeam\Foundation\Catcher::register
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     */
    public function testMethod_unregister(): void {
        $c = new Catcher();
        $c->unregister();
        $this->assertFalse($c->unregister());
    }

    /**
     * @covers \MensBeam\Foundation\Catcher::unshiftHandler
     * 
     * @covers \MensBeam\Foundation\Catcher::__construct
     * @covers \MensBeam\Foundation\Catcher::getHandlers
     * @covers \MensBeam\Foundation\Catcher::pushHandler
     * @covers \MensBeam\Foundation\Catcher::register
     * @covers \MensBeam\Foundation\Catcher::unregister
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     * @covers \MensBeam\Foundation\Catcher\HTMLHandler::__construct
     */
    public function testMethod_unshiftHandler(): void {
        $c = new Catcher(new PlainTextHandler());
        $c->unshiftHandler(new JSONHandler(), new HTMLHandler(), new PlainTextHandler());
        $h = $c->getHandlers();
        $this->assertEquals(4, count($h));
        $this->assertSame(JSONHandler::class, $h[0]::class);
        $this->assertSame(HTMLHandler::class, $h[1]::class);
        $this->assertSame(PlainTextHandler::class, $h[2]::class);
        $this->assertSame(PlainTextHandler::class, $h[3]::class);

        $e = null;
        set_error_handler(function($errno) use (&$e) {
            $e = $errno;
        });

        $c->unshiftHandler($h[0]);
        $this->assertEquals(\E_USER_WARNING, $e);
        $e = null;
        $h = new PlainTextHandler();
        $c->unshiftHandler($h, $h);
        $this->assertEquals(\E_USER_WARNING, $e);

        restore_error_handler();
        $c->unregister();

        $c = new Catcher();
        $c->unregister();

        $e = null;
        try {
            $c->unshiftHandler();
        } catch (\Throwable $t) {
            $e = $t::class;
        } finally {
            $this->assertSame(\ArgumentCountError::class, $e);
        }
    }

    /**
     * @covers \MensBeam\Foundation\Catcher::handleError
     * 
     * @covers \MensBeam\Foundation\Catcher::__construct
     * @covers \MensBeam\Foundation\Catcher::getLastThrowable
     * @covers \MensBeam\Foundation\Catcher::exit
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
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getPrevious
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getThrowable
     */
    public function testMethod_handleError(): void {
        $c = new Catcher(new PlainTextHandler([ 'silent' => true ]));

        trigger_error('Ook!', \E_USER_NOTICE);
        $t = $c->getLastThrowable();
        $this->assertSame(Error::class, $t::class);
        $this->assertSame(\E_USER_NOTICE, $t->getCode());

        trigger_error('Ook!', \E_USER_DEPRECATED);
        $t = $c->getLastThrowable();
        $this->assertSame(Error::class, $t::class);
        $this->assertSame(\E_USER_DEPRECATED, $t->getCode());

        trigger_error('Ook!', \E_USER_WARNING);
        $t = $c->getLastThrowable();
        $this->assertSame(Error::class, $t::class);
        $this->assertSame(\E_USER_WARNING, $t->getCode());

        trigger_error('Ook!', \E_USER_ERROR);
        $t = $c->getLastThrowable();
        $this->assertSame(Error::class, $t::class);
        $this->assertSame(\E_USER_ERROR, $t->getCode());

        $er = error_reporting();
        error_reporting(0);
        trigger_error('Ook!', \E_USER_ERROR);
        error_reporting($er);

        $c->unregister();

        $h1 = Phony::partialMock(PlainTextHandler::class, [ [ 'silent' => true ] ]);
        $h2 = Phony::partialMock(HTMLHandler::class, [ [ 'silent' => true ] ]);
        $h3 = Phony::partialMock(JSONHandler::class, [ [ 'silent' => true ] ]);

        $h = Phony::partialMock(Catcher::class, [ 
            $h1->get(),
            $h2->get(),
            $h3->get()
        ]);
        $c = $h->get();

        trigger_error('Ook!', \E_USER_ERROR);

        $h1->dispatch->called();
        $h2->dispatch->called();
        $h3->dispatch->called();

        $c->unregister();
    }

    /**
     * @covers \MensBeam\Foundation\Catcher::handleThrowable
     * 
     * @covers \MensBeam\Foundation\Catcher::__construct
     * @covers \MensBeam\Foundation\Catcher::getLastThrowable
     * @covers \MensBeam\Foundation\Catcher::handleError
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
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getPrevious
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getThrowable
     */
    public function testMethod_handleThrowable(): void {
        $c = new Catcher(new PlainTextHandler([ 'silent' => true, 'forceBreak' => true ]));
        trigger_error('Ook!', \E_USER_ERROR);
        $t = $c->getLastThrowable();
        $this->assertSame(Error::class, $t::class);
        $this->assertSame(\E_USER_ERROR, $t->getCode());
        $c->unregister();

        Catcher::$preventExit = false;
        $h = Phony::partialMock(Catcher::class, [ new PlainTextHandler([ 'silent' => true ]) ]);
        $h->exit->returns();
        $c = $h->get();
        trigger_error('Ook!', \E_USER_ERROR);
        $t = $c->getLastThrowable();
        $this->assertSame(Error::class, $t::class);
        $this->assertSame(\E_USER_ERROR, $t->getCode());
        $c->unregister();
        Catcher::$preventExit = true;
    }

    /**
     * @covers \MensBeam\Foundation\Catcher::handleShutdown
     * 
     * @covers \MensBeam\Foundation\Catcher::__construct
     * @covers \MensBeam\Foundation\Catcher::getLastError
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
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getPrevious
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getThrowable
     */
    public function testMethod_handleShutdown(): void {
        $c = new Catcher();
        $c->handleShutdown();
        $p = new \ReflectionProperty($c, 'isShuttingDown');
        $p->setAccessible(true);
        $this->assertTrue($p->getValue($c));
        $c->unregister();

        $c = new Catcher();
        $c->unregister();
        $c->handleShutdown();
        $p = new \ReflectionProperty($c, 'isShuttingDown');
        $p->setAccessible(true);
        $this->assertFalse($p->getValue($c));

        $h = Phony::partialMock(Catcher::class, [ new PlainTextHandler([ 'silent' => true ]) ]);
        $h->getLastError->returns([
            'type' => \E_ERROR,
            'message' => 'Ook!',
            'file' => '/dev/null',
            'line' => 2112
        ]);
        $c = $h->get();
        $c->handleShutdown();
        $h->handleError->called();
        $h->handleThrowable->called();
        $c->unregister();
    }
}