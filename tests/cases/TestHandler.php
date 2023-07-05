<?php
/**
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details
 */

declare(strict_types=1);
namespace MensBeam\Catcher\Test;
use MensBeam\Catcher\{
    Error,
    Handler,
    RangeException,
    ThrowableController
};
use MensBeam\Catcher,
    Psr\Log\LoggerInterface,
    Phake;


/** @covers \MensBeam\Catcher\Handler */
class TestHandler extends ErrorHandlingTestCase {
    protected ?Handler $handler = null;


    public function setUp(): void {
        parent::setUp();

        $this->handler = new TestingHandler([
            'outputBacktrace' => true
        ]);
    }

    /** @dataProvider provideArgumentSerializationTests */
    public function testArgumentSerialization(mixed $arg): void {
        // This looks silly because the argument is never used, but the handler will
        // pick it up and print it anyway which is what we're testing here
        $this->handler->setOption('print', true);
        $this->handler->setOption('printJSON', false);
        $this->handler->setOption('outputToStderr', false);
        $this->handler->handle(new ThrowableController(new \Exception('Ook!')));
        $h = $this->handler;
        ob_start();
        $h();
        $o = ob_get_clean();
        $this->assertNotEmpty($o);
    }

    /** @dataProvider provideHandlingTests */
    public function testHandling(\Throwable $throwable, int $expectedCode, array $options = []): void {
        foreach ($options as $k => $v) {
            $this->handler->setOption($k, $v);
        }

        $o = $this->handler->handle(new ThrowableController($throwable));
        $this->assertSame($throwable::class, $o['controller']->getThrowable()::class);
        $this->assertEquals($expectedCode, $o['code']);
    }

    public function testInvocation(): void {
        $this->handler->handle(new ThrowableController(new \Exception('Ook!')));
        $r = new \ReflectionProperty($this->handler::class, 'outputBuffer');
        $r->setAccessible(true);
        $this->assertEquals(1, count($r->getValue($this->handler)));

        $h = $this->handler;
        $h();
        $this->assertEquals(0, count($r->getValue($this->handler)));
        $h();
        $this->assertEquals(0, count($r->getValue($this->handler)));
    }

    /** @dataProvider provideLogTests */
    public function testLog(\Throwable $throwable, string $methodName): void {
        $l = Phake::mock(LoggerInterface::class);
        $this->handler->setOption('logger', $l);
        $this->handler->handle(new ThrowableController($throwable));
        $h = $this->handler;
        $h();
        Phake::verify($l, Phake::times(1))->$methodName;
    }

    /** @dataProvider provideOptionsTests */
    public function testOptions(string $option, mixed $value): void {
        $this->handler->setOption($option, $value);
        $this->assertSame($value, $this->handler->getOption($option));
    }

    public function testPrinting(): void {
        $this->handler->setOption('print', true);
        $this->handler->setOption('outputToStderr', false);
        $this->handler->handle(new ThrowableController(new \Exception('Ook!')));
        $h = $this->handler;
        ob_start();
        $h();
        $o = ob_get_clean();
        $this->assertNotEmpty($o);
        $o = json_decode($o, true);
        $this->assertSame(\Exception::class, $o['class']);
        $this->assertSame(__FILE__, $o['file']);
        $this->assertSame(__LINE__ - 9, $o['line']);
        $this->assertSame('Ook!', $o['message']);
    }

    /** @dataProvider provideSupplementalErrorHandlingTests */
    public function testSupplementalErrorHandling(\Closure $closure, bool $useCatcher, bool $silent): void {
        if (!$silent) {
            $this->handler->setOption('print', true);
            $this->handler->setOption('printJSON', false);
            $this->handler->setOption('outputToStderr', false);
        } else {
            $this->handler->setOption('silent', true);
        }

        if ($useCatcher) {
            $c = new Catcher($this->handler);
        }

        ob_start();
        $closure($this->handler);
        $o = ob_get_clean();
        $this->assertNotEmpty($o);

        if ($useCatcher) {
            $c->unregister();
            unset($c);
        }
    }


    public function testFatalError(): void {
        $this->expectException(RangeException::class);
        $this->handler->setOption('httpCode', 42);
    }

    /** @dataProvider provideNonFatalErrorTests */
    public function testNonFatalErrors(int $code, string $message, \Closure $closure): void {
        $closure($this->handler);
        $this->assertEquals($code, $this->lastError?->getCode());
        $this->assertSame($message, $this->lastError?->getMessage());
    }


    public static function provideArgumentSerializationTests(): iterable {
        $options = [
            [ fn() => true ],
            [ new \stdClass() ],
            [ new class{} ],
            [ (object)[] ],
            [ 'ook' ],
            [ 42 ],
            [ \M_PI ]
        ];

        foreach ($options as $o) {
            yield $o;
        }
    }

    public static function provideHandlingTests(): iterable {
        $options = [
            [ new \Exception('Ook!'), Handler::BUBBLES | Handler::OUTPUT | Handler::EXIT, [ 'forceExit' => true ] ],
            [ new \Error('Ook!'), Handler::BUBBLES | Handler::OUTPUT ],
            [ new \Exception('Ook!'), Handler::BUBBLES, [ 'silent' => true ] ],
            [ new Error('Ook!', \E_ERROR, '/dev/null', 42, new \Error('Eek!')), Handler::BUBBLES | Handler::OUTPUT | Handler::NOW, [ 'forceOutputNow' => true ] ],
            [ new \Exception('Ook!'), Handler::BUBBLES, [ 'silent' => true, 'logger' => Phake::mock(LoggerInterface::class), 'logWhenSilent' => false ] ],
            [ new \Error('Ook!'), Handler::BUBBLES | Handler::OUTPUT | Handler::LOG, [ 'forceOutputNow' => true, 'logger' => Phake::mock(LoggerInterface::class) ] ]
        ];

        foreach ($options as $o) {
            // TestingHandler adds Handler::NOW to the output code to make
            // testing with it in TestCatcher less of a pain, so it needs to be
            // added here
            $o[1] |= Handler::NOW;
            yield $o;
        }
    }

    public static function provideLogTests(): iterable {
        $options = [
            [ new Error('Ook!', \E_NOTICE, '/dev/null', 0, new \Error('Eek!')), 'notice' ],
            [ new Error('Ook!', \E_USER_NOTICE, '/dev/null', 0), 'notice' ],
            [ new Error('Ook!', \E_STRICT, '/dev/null', 0, new \Error('Eek!')), 'notice' ],
            [ new Error('Ook!', \E_WARNING, '/dev/null', 0), 'warning' ],
            [ new Error('Ook!', \E_COMPILE_WARNING, '/dev/null', 0, new \Error('Eek!')), 'warning' ],
            [ new Error('Ook!', \E_USER_WARNING, '/dev/null', 0), 'warning' ],
            [ new Error('Ook!', \E_DEPRECATED, '/dev/null', 0, new \Error('Eek!')), 'warning' ],
            [ new Error('Ook!', \E_USER_DEPRECATED, '/dev/null', 0), 'warning' ],
            [ new Error('Ook!', \E_PARSE, '/dev/null', 0, new \Error('Eek!')), 'critical' ],
            [ new Error('Ook!', \E_CORE_ERROR, '/dev/null', 0), 'critical' ],
            [ new Error('Ook!', \E_COMPILE_ERROR, '/dev/null', 0, new \Error('Eek!')), 'critical' ],
            [ new Error('Ook!', \E_ERROR, '/dev/null', 0), 'error' ],
            [ new Error('Ook!', \E_USER_ERROR, '/dev/null', 0, new \Error('Eek!')), 'error' ],
            [ new \PharException('Ook!'), 'alert' ],
            [ new \Exception('Ook!'), 'critical' ],
        ];

        foreach ($options as $o) {
            yield $o;
        }
    }

    public static function provideNonFatalErrorTests(): iterable {
        $iterable = [
            [
                \E_USER_WARNING,
                'Undefined option in ' . TestingHandler::class . ': ook',
                function (Handler $h): void {
                    $h->getOption('ook');
                }
            ],
            [
                \E_USER_WARNING,
                'Undefined option in ' . TestingHandler::class . ': ook',
                function (Handler $h): void {
                    $h->setOption('ook', 'eek');
                }
            ]
        ];

        foreach ($iterable as $i) {
            yield $i;
        }
    }

    public static function provideOptionsTests(): iterable {
        $options = [
            [ 'backtraceArgFrameLimit', 42 ],
            [ 'bubbles', false ],
            [ 'charset', 'UTF-16' ],
            [ 'forceExit', true ],
            [ 'forceOutputNow', true ],
            [ 'httpCode', 200 ],
            [ 'httpCode', 400 ],
            [ 'httpCode', 502 ],
            [ 'logger', Phake::mock(LoggerInterface::class) ],
            [ 'logWhenSilent', false ],
            [ 'outputBacktrace', true ],
            [ 'outputPrevious', false ],
            [ 'outputTime', false ],
            [ 'outputToStderr', false ],
            [ 'silent', true ],
            [ 'timeFormat', 'Y-m-d' ]
        ];

        foreach ($options as $o) {
            yield $o;
        }
    }

    public static function provideSupplementalErrorHandlingTests(): iterable {
        $iterable = [
            // Test with a logger that errors without a Catcher
            [ function (Handler $h): void {
                $h->setOption('logger', new FailLogger());
                $h->handle(new ThrowableController(new \Exception('Ook!')));
                $h();
            }, false, false ],
            // Test with a logger that errors with a Catcher
            [ function (Handler $h): void {
                $h->setOption('logger', new FailLogger());
                $h->handle(new ThrowableController(new \Exception('Ook!')));
                $h();
            }, true, false ],
            // Test with a logger that errors with a Catcher but silent
            [ function (Handler $h): void {
                $h->setOption('logger', new FailLogger());
                $h->handle(new ThrowableController(new \Exception('Ook!')));
                $h();
            }, true, true ]
        ];

        foreach ($iterable as $i) {
            yield $i;
        }
    }
}