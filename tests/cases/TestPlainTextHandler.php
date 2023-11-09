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
    ThrowableController
};
use Psr\Log\LoggerInterface,
    Phake;


/**
 * @covers \MensBeam\Catcher\PlainTextHandler
 * @covers \MensBeam\Catcher\Handler
 */
class TestPlainTextHandler extends \PHPUnit\Framework\TestCase {
    use HandlerInvocationTests;

    protected ?Handler $handler = null;


    public function setUp(): void {
        parent::setUp();

        $this->handler = new PlainTextHandler([
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
        if ($c !== null && !empty($o['errorType'])) {
            $c = $o['errorType'];
        }

        $h = $this->handler;
        ob_start();
        $h();
        $u = ob_get_clean();
        $u = substr($u, 0, strpos($u, \PHP_EOL) ?: 0);

        if (!$silent && $ignore === null) {
            $this->assertMatchesRegularExpression(sprintf('/^\[[\d:]+\]  %s: Ook\! in file %s on line %s$/', preg_quote($c, '/'), preg_quote((new \ReflectionClass(HandlerInvocationTests::class))->getFileName(), '/'), $line), $u);
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