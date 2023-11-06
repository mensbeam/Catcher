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
    JSONHandler,
    ThrowableController
};
use Psr\Log\LoggerInterface,
    Phake;


/** @covers \MensBeam\Catcher\JSONHandler */
class TestJSONHandler extends \PHPUnit\Framework\TestCase {
    protected ?Handler $handler = null;


    public function setUp(): void {
        parent::setUp();

        $this->handler = new JSONHandler([
            'outputBacktrace' => true,
            'silent' => true
        ]);
    }

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

        $h = $this->handler;
        ob_start();
        $h();
        $u = ob_get_clean();

        if (!$silent) {
            $u = json_decode($u, true);
            $this->assertEquals($c, $u['class']);
            $this->assertEquals(__FILE__, $u['file']);
            $this->assertEquals($line, $u['line']);
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