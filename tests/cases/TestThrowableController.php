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
    ThrowableController,
    UnderflowException
};


/** @covers \MensBeam\Catcher\ThrowableController */
class TestThrowableController extends \PHPUnit\Framework\TestCase {

    /** @dataProvider provideErrorTypeTests */
    public function testErrorTypes(\Throwable $throwable, ?string $expectedType = null): void {
        $c = new ThrowableController($throwable);
        $this->assertSame($expectedType, $c->getErrorType());
        // Tests caching
        $this->assertSame($expectedType, $c->getErrorType());
    }

    /** @dataProvider provideGettingFramesTests */
    public function testGettingFrames(\Throwable $throwable): void {
        $c = new ThrowableController($throwable);
        $f = $c->getFrames(rand(0, 10));
        $this->assertNotNull($f);
        $this->assertGreaterThan(0, count($f));
        // Tests caching
        $this->assertNotNull($c->getFrames());
    }

    /** @dataProvider provideGettingFramesInCallUserFuncTests */
    public function testGettingFramesInCallUserFunc(\Closure $closure): void {
        $f = false;
        try {
            $closure();
            call_user_func_array(function() {
                throw new \Exception('Ook!');
            }, []);
        } catch (\Throwable $t) {
            $c = new ThrowableController($t);
            $f = $c->getFrames();
        } finally {
            $this->assertNotNull($f);
            $this->assertGreaterThan(0, count($f));
        }
    }

    public function testGetPrevious(): void {
        $t = new ThrowableController(new \Exception(message: 'Ook!', previous: new Error(message: 'Ook!', code: \E_ERROR, previous: new \Exception('Ook!'))));
        $this->assertInstanceOf(\Exception::class, $t->getThrowable());
        $t2 = $t->getPrevious();
        $this->assertInstanceOf(Error::class, $t2->getThrowable());
        $t3 = $t2->getPrevious();
        $this->assertInstanceOf(\Exception::class, $t3->getThrowable());
        // Tests caching
        $t3 = $t2->getPrevious();
        $this->assertInstanceOf(\Exception::class, $t3->getThrowable());
    }


    public function testFatalError(): void {
        $this->expectException(UnderflowException::class);
        $c = new ThrowableController(new \Exception('ook'));
        $f = $c->getFrames(-1);
    }

    public static function provideErrorTypeTests(): iterable {
        $options = [
            [ new Error('Ook!', \E_ERROR, '/dev/null', 0), 'PHP Fatal Error' ],
            [ new Error('Ook!', \E_WARNING, '/dev/null', 0), 'PHP Warning' ],
            [ new Error('Ook!', \E_PARSE, '/dev/null', 0), 'PHP Parsing Error' ],
            [ new Error('Ook!', \E_NOTICE, '/dev/null', 0), 'PHP Notice' ],
            [ new Error('Ook!', \E_CORE_ERROR, '/dev/null', 0), 'PHP Core Error' ],
            [ new Error('Ook!', \E_CORE_WARNING, '/dev/null', 0), 'PHP Core Warning' ],
            [ new Error('Ook!', \E_COMPILE_ERROR, '/dev/null', 0), 'Compile Error' ],
            [ new Error('Ook!', \E_COMPILE_WARNING, '/dev/null', 0), 'Compile Warning' ],
            [ new Error('Ook!', \E_STRICT, '/dev/null', 0), 'Runtime Notice' ],
            [ new Error('Ook!', \E_RECOVERABLE_ERROR, '/dev/null', 0), 'Recoverable Error' ],
            [ new Error('Ook!', \E_DEPRECATED, '/dev/null', 0), 'Deprecated' ],
            [ new Error('Ook!', \E_USER_DEPRECATED, '/dev/null', 0), 'Deprecated' ],
            [ new Error('Ook!', \E_USER_ERROR, '/dev/null', 0), 'Fatal Error' ],
            [ new Error('Ook!', \E_USER_WARNING, '/dev/null', 0), 'Warning' ],
            [ new Error('Ook!', \E_USER_NOTICE, '/dev/null', 0), 'Notice' ],
            [ new Error('Ook!', \E_ALL, '/dev/null', 0) ],
            [ new \Exception('Ook!') ],
        ];

        foreach ($options as $o) {
            yield $o;
        }
    }

    public static function provideGettingFramesTests(): iterable {
        $options = [
            [ new Error('Ook!', \E_ERROR, '/dev/null', 0) ],
            [ new Error('Ook!', \E_WARNING, '/dev/null', 0) ],
            [ new Error('Ook!', \E_PARSE, '/dev/null', 0) ],
            [ new Error('Ook!', \E_NOTICE, '/dev/null', 0) ],
            [ new Error('Ook!', \E_CORE_ERROR, '/dev/null', 0) ],
            [ new Error('Ook!', \E_CORE_WARNING, '/dev/null', 0) ],
            [ new Error('Ook!', \E_COMPILE_ERROR, '/dev/null', 0) ],
            [ new Error('Ook!', \E_COMPILE_WARNING, '/dev/null', 0) ],
            [ new Error('Ook!', \E_STRICT, '/dev/null', 0) ],
            [ new Error('Ook!', \E_RECOVERABLE_ERROR, '/dev/null', 0) ],
            [ new Error('Ook!', \E_DEPRECATED, '/dev/null', 0) ],
            [ new Error('Ook!', \E_USER_DEPRECATED, '/dev/null', 0) ],
            [ new Error('Ook!', \E_USER_ERROR, '/dev/null', 0) ],
            [ new Error('Ook!', \E_USER_WARNING, '/dev/null', 0) ],
            [ new Error('Ook!', \E_USER_NOTICE, '/dev/null', 0) ],
            [ new \Exception('Ook!') ],
            [ new \Exception(message: 'Ook!', previous: new Error(message: 'Ook!', code: \E_ERROR, previous: new \Exception('Ook!'))) ]
        ];

        foreach ($options as $o) {
            yield $o;
        }
    }

    public static function provideGettingFramesInCallUserFuncTests(): iterable {
        $options = [
            function () {
                call_user_func_array(function() {
                    throw new \Exception('Ook!');
                }, []);
            },
            function () {
                function ook() {}
                call_user_func('ook', []);
            }
        ];

        foreach ($options as $o) {
            yield [ $o ];
        }
    }
}