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
    PlainTextHandler,
    ThrowableController,
    UnderflowException
};
use Psr\Log\LoggerInterface,
    Phake;


/** @covers \MensBeam\Catcher\PlainTextHandler */
class TestPlainTextHandler extends \PHPUnit\Framework\TestCase {
    protected ?Handler $handler = null;


    public function setUp(): void {
        parent::setUp();

        $this->handler = new PlainTextHandler([
            'outputBacktrace' => true,
            'silent' => true
        ]);
    }

    ///** @dataProvider provideHandlingTests */
    /*public function testHandling(\Throwable $throwable, int $expectedCode, array $options = []): void {
        foreach ($options as $k => $v) {
            $this->handler->setOption($k, $v);
        }

        $o = $this->handler->handle(new ThrowableController($throwable));
        $this->assertSame($throwable::class, $o['controller']->getThrowable()::class);
        $this->assertEquals($expectedCode, $o['code']);
    }*/

    /** @dataProvider provideInvocationTests */
    public function testInvocation(\Throwable $throwable, bool $silent, bool $log, ?string $logMethodName, int $line): void {
        $this->handler->setOption('outputToStderr', false);

        if (!$silent) {
            $this->handler->setOption('silent', false);
        }
        if ($log) {
            $l = Phake::mock(LoggerInterface::class);
            $this->handler->setOption('logger', $l);
        }

        $o = $this->handler->handle(new ThrowableController($throwable));

        $c = $o['class'] ?? null;
        if ($c !== null && !empty($o['errorType'])) {
            $c = $o['errorType'];
        }

        $h = $this->handler;
        ob_start();
        $h();
        $u = ob_get_clean();
        $u = substr($u, 0, strpos($u, \PHP_EOL) ?: 0);

        if (!$silent) {
            $this->assertMatchesRegularExpression(sprintf('/^\[[\d:]+\]  %s: Ook\! in file %s on line %s$/', preg_quote($c, '/'), preg_quote(__FILE__, '/'), $line), $u);
        } else {
            $this->assertSame('', $u);
        }

        if ($log) {
            Phake::verify($l, Phake::times(1))->$logMethodName;
        }
    }


    public static function provideHandlingTests(): iterable {
        $options = [
            [ new \Exception('Ook!'), Handler::BUBBLES | Handler::EXIT, [ 'forceExit' => true ] ],
            [ new \Error('Ook!'), Handler::BUBBLES ],
            [ new Error('Ook!', \E_ERROR, '/dev/null', 42, new \Error('Eek!')), Handler::BUBBLES | Handler::NOW, [ 'forceOutputNow' => true ] ],
            [ new \Exception('Ook!'), Handler::BUBBLES, [ 'logger' => Phake::mock(LoggerInterface::class), 'logWhenSilent' => false ] ],
            [ new \Error('Ook!'), Handler::BUBBLES | Handler::LOG, [ 'forceOutputNow' => true, 'logger' => Phake::mock(LoggerInterface::class) ] ]
        ];

        foreach ($options as $o) {
            $o[1] |= Handler::NOW;
            yield $o;
        }
    }

    public static function provideInvocationTests(): iterable {
        $options = [
            [ new \Exception('Ook!'), false, true, 'critical' ],
            [ new \Error('Ook!'), true, false, null ],
            [ new Error('Ook!', \E_ERROR, __FILE__, __LINE__), false, true, 'error' ],
            [ new \Exception(message: 'Ook!', previous: new \Error(message: 'Eek!', previous: new \ParseError('Ack!'))), true, true, 'critical' ]
        ];

        $l = count($options);
        foreach ($options as $k => $o) {
            yield [ ...$o, __LINE__ - 4 - $l + $k ];
        }
    }
}