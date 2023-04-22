<?php
/**
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details
 */

declare(strict_types=1);
namespace MensBeam\Catcher\Test;
use MensBeam\Catcher,
    Phake,
    Phake\IMock;
use MensBeam\Catcher\{
    ArgumentCountError,
    Error,
    PlainTextHandler,
    UnderflowException
};


/** @covers \MensBeam\Catcher */
class TestCatcher extends \PHPUnit\Framework\TestCase {
    protected ?Catcher $catcher = null;


    public function setUp(): void {
        if ($this->catcher !== null) {
            $this->catcher->unregister();
        }
        $this->catcher = new Catcher();
        $this->catcher->preventExit = true;

        // Do this instead of specifying the option in the constructor for coverage
        // purposes...
        $handlers = $this->catcher->getHandlers();
        $handlers[0]->setOption('silent', true);
    }

    public function tearDown(): void {
        $this->catcher->unregister();
        $this->catcher = null;
        error_reporting(\E_ALL);
    }


    public function testConstructor(): void {
        $h = $this->catcher->getHandlers();
        $this->assertEquals(1, count($h));
        $this->assertInstanceOf(PlainTextHandler::class, $h[0]);
    }

    /** @dataProvider provideErrorHandlingTests */
    public function testErrorHandling(int $code): void {
        $t = null;
        try {
            trigger_error('Ook!', $code);
        } catch (\Throwable $t) {} finally {
            $t = ($t === null) ? $this->catcher->getLastThrowable() : $t;
            $this->assertSame(Error::class, $t::class);
            $this->assertSame($code, $t->getCode());
            $this->assertSame($t, $this->catcher->getLastThrowable());
        }
    }

    public function testExit(): void {
        $this->catcher->unregister();
        $h = Phake::partialMock(TestingHandler::class);
        $this->catcher = $m = Phake::partialMock(Catcher::class, $h);
        $m->errorHandlingMethod = Catcher::THROW_NO_ERRORS;
        Phake::when($m)->exit->thenReturn(null);
        Phake::when($m)->handleShutdown()->thenReturn(null);

        trigger_error('Ook!', \E_USER_ERROR);

        Phake::verify($h, Phake::times(1))->invokeCallback();
    }

    public function testHandlerBubbling(): void {
        $this->catcher->unregister();

        $h1 = Phake::partialMock(TestingHandler::class, [ 'bubbles' => false ]);
        $h2 = Phake::partialMock(TestingHandler::class);
        $this->catcher = $m = Phake::partialMock(Catcher::class, $h1, $h2);
        $m->errorHandlingMethod = Catcher::THROW_NO_ERRORS;
        $m->preventExit = true;

        trigger_error('Ook!', \E_USER_ERROR);
        Phake::verify($m)->handleError(\E_USER_ERROR, 'Ook!', __FILE__, __LINE__ - 1);
        Phake::verify($m)->handleThrowable($m->getLastThrowable());
        Phake::verify($h1)->invokeCallback();
        Phake::verify($h2, Phake::never())->invokeCallback();
    }

    public function testHandlerForceExiting(): void {
        $this->catcher->setHandlers(new TestingHandler([ 'forceExit' => true ]));
        $this->catcher->errorHandlingMethod = Catcher::THROW_NO_ERRORS;
        $this->catcher->preventExit = true;

        trigger_error('Ook', \E_USER_ERROR);
        $this->assertSame(Error::class, $this->catcher->getLastThrowable()::class);
    }

    public function testRegistration(): void {
        $this->assertTrue($this->catcher->isRegistered());
        $this->assertFalse($this->catcher->register());
        $this->assertTrue($this->catcher->unregister());
        $this->assertFalse($this->catcher->unregister());
        $this->assertFalse($this->catcher->isRegistered());
    }

    /** @dataProvider provideShutdownTests */
    public function testShutdownHandling(\Closure $closure): void {
        $this->catcher->unregister();

        $h1 = Phake::partialMock(TestingHandler::class);
        $this->catcher = $m = Phake::partialMock(Catcher::class, $h1);
        $closure($m, $h1);
    }

    public function testStackManipulation(): void {
        $c = $this->catcher;
        $c->pushHandler(new TestingHandler(options: [ 'name' => 'ook' ]), new TestingHandler(options: [ 'name' => 'eek' ]));
        $h = $c->getHandlers();
        $this->assertEquals(3, count($h));
        $this->assertSame('ook', $h[1]->getOption('name'));
        $this->assertSame('eek', $h[2]->getOption('name'));

        $this->assertInstanceOf(PlainTextHandler::class, $c->shiftHandler());
        $h = $c->getHandlers();
        $this->assertEquals(2, count($h));
        $this->assertSame('ook', $h[0]->getOption('name'));
        $this->assertSame('eek', $h[1]->getOption('name'));

        $p = $c->popHandler();
        $this->assertInstanceOf(TestingHandler::class, $p);
        $h = $c->getHandlers();
        $this->assertEquals(1, count($h));
        $this->assertSame('eek', $p->getOption('name'));
        $this->assertSame('ook', $h[0]->getOption('name'));

        $c->unshiftHandler($p);
        $h = $c->getHandlers();
        $this->assertEquals(2, count($h));
        $this->assertSame('eek', $h[0]->getOption('name'));
        $this->assertSame('ook', $h[1]->getOption('name'));

        $c->setHandlers(new PlainTextHandler());
        $this->assertEquals(1, count($c->getHandlers()));
    }

    public function testWeirdErrorReporting(): void {
        error_reporting(\E_ERROR);
        $t = null;
        try {
            trigger_error('Ook!', \E_USER_WARNING);
        } catch (\Throwable $t) {} finally {
            $this->assertNull($t);
            $this->assertNull($this->catcher->getLastThrowable());
        }
    }


    /** @dataProvider provideFatalErrorTests */
    public function testFatalErrors(string $throwableClassName, \Closure $closure): void {
        $this->expectException($throwableClassName);
        $closure($this->catcher);
    }


    public static function provideFatalErrorTests(): iterable {
        $iterable = [
            [
                UnderflowException::class,
                function (Catcher $c): void {
                    $c->popHandler();
                }
            ],
            [
                ArgumentCountError::class,
                function (Catcher $c): void {
                    $c->pushHandler();
                }
            ],
            [
                UnderflowException::class,
                function (Catcher $c): void {
                    $c->shiftHandler();
                }
            ],
            [
                ArgumentCountError::class,
                function (Catcher $c): void {
                    $c->unshiftHandler();
                }
            ],
        ];

        foreach ($iterable as $i) {
            yield $i;
        }
    }

    public static function provideErrorHandlingTests(): iterable {
        foreach ([ \E_USER_NOTICE, \E_USER_DEPRECATED, \E_USER_WARNING, \E_USER_ERROR ] as $i) {
            yield [ $i ];
        }
    }

    public static function provideShutdownTests(): iterable {
        $iterable = [
            [
                function (IMock $m, IMock $h): void {
                    $m->errorHandlingMethod = Catcher::THROW_NO_ERRORS;
                    Phake::when($m)->getLastError()->thenReturn([
                        'type' => \E_ERROR,
                        'message' => 'Ook!',
                        'file' => '/dev/null',
                        'line' => 2112
                    ]);
                    $m->handleShutdown();
                    Phake::verify($m)->getLastError();
                    Phake::verify($m)->handleError(\E_ERROR, 'Ook!', '/dev/null', 2112);
                    Phake::verify($h, Phake::times(1))->invokeCallback();
                }
            ],
            [
                function (IMock $m, IMock $h): void {
                    $m->errorHandlingMethod = Catcher::THROW_NO_ERRORS;
                    Phake::when($m)->getLastError()->thenReturn([
                        'type' => \E_USER_ERROR,
                        'message' => 'Ook!',
                        'file' => '/dev/null',
                        'line' => 2112
                    ]);
                    $m->handleShutdown();
                    Phake::verify($m)->getLastError();
                    Phake::verify($m)->handleError(\E_USER_ERROR, 'Ook!', '/dev/null', 2112);
                    Phake::verify($h, Phake::times(1))->invokeCallback();
                }
            ],
            [
                function (IMock $m, IMock $h): void {
                    $m->errorHandlingMethod = Catcher::THROW_NO_ERRORS;
                    Phake::when($m)->getLastError()->thenReturn([
                        'type' => \E_USER_ERROR,
                        'message' => 'Ook!',
                        'file' => '/dev/null',
                        'line' => 2112
                    ]);
                    $m->handleShutdown();
                    Phake::verify($m)->getLastError();
                    Phake::verify($m)->handleError(\E_USER_ERROR, 'Ook!', '/dev/null', 2112);
                    Phake::verify($h, Phake::times(1))->invokeCallback();
                }
            ],
            [
                function (IMock $m, IMock $h): void {
                    $m->errorHandlingMethod = Catcher::THROW_NO_ERRORS;
                    $m->handleShutdown();
                    Phake::verify($m)->getLastError();
                    // Handler wouldn't be invoked because there aren't any errors in the output buffer.
                    Phake::verify($h, Phake::never())->invokeCallback();
                }
            ],
            [
                function (IMock $m, IMock $h): void {
                    // Nothing in the shutdown handler runs if Catcher is unregistered
                    $m->unregister();
                    $m->handleShutdown();
                    Phake::verify($m, Phake::never())->getLastError();
                    Phake::verify($h, Phake::never())->invokeCallback();
                }
            ]
        ];

        foreach ($iterable as $i) {
            yield $i;
        }
    }
}