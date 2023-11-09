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


/**
 * @covers \MensBeam\Catcher\JSONHandler
 * @covers \MensBeam\Catcher\Handler
 */
class TestJSONHandler extends \PHPUnit\Framework\TestCase {
    use HandlerInvocationTests;

    protected ?Handler $handler = null;


    public function setUp(): void {
        parent::setUp();

        $this->handler = new JSONHandler([
            'outputBacktrace' => true,
            'silent' => true
        ]);
    }

    /** @dataProvider provideInvocationTests */
    public function testInvocation(\Throwable $throwable, bool $silent, bool $log, ?string $logMethodName, ?array $ignore, int $line): void {
        $this->handler->setOption('outputToStderr', false);

        if (!$silent) {
            $this->handler->setOption('silent', false);
        }
        if ($log) {
            $l = Phake::mock(LoggerInterface::class);
            $this->handler->setOption('logger', $l);
        }
        if ($ignore !== null) {
            $this->handler->setOption('ignore', $ignore);
        }

        $o = $this->handler->handle(new ThrowableController($throwable));
        $c = $o['class'] ?? null;

        $h = $this->handler;
        ob_start();
        $h();
        $u = ob_get_clean();

        if (!$silent && $ignore === null) {
            $u = json_decode($u, true);
            $this->assertEquals($c, $u['class']);
            $this->assertEquals((new \ReflectionClass(HandlerInvocationTests::class))->getFileName(), $u['file']);
            $this->assertEquals($line, $u['line']);
            $this->assertNotNull($h->getLastOutputThrowable());
        } else {
            if ($ignore !== null) {
                $this->assertNull($h->getLastOutputThrowable());
            }
            $this->assertSame('', $u);
        }

        if ($log) {
            Phake::verify($l, Phake::times((int)(count($ignore ?? []) === 0)))->$logMethodName;
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
}