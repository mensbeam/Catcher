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
    Handler,
    PlainTextHandler,
    ThrowableController
};
use Eloquent\Phony\Phpunit\Phony,
    Psr\Log\LoggerInterface;


class TestPlainTextHandler extends \PHPUnit\Framework\TestCase {
    /**
     * @covers \MensBeam\Catcher\PlainTextHandler::handleCallback
     * 
     * @covers \MensBeam\Catcher\Handler::__construct
     * @covers \MensBeam\Catcher\Handler::buildOutputArray
     * @covers \MensBeam\Catcher\Handler::handle
     * @covers \MensBeam\Catcher\PlainTextHandler::dispatchCallback
     * @covers \MensBeam\Catcher\PlainTextHandler::handleCallback
     * @covers \MensBeam\Catcher\PlainTextHandler::log
     * @covers \MensBeam\Catcher\PlainTextHandler::serializeOutputThrowable
     * @covers \MensBeam\Catcher\ThrowableController::__construct
     * @covers \MensBeam\Catcher\ThrowableController::getErrorType
     * @covers \MensBeam\Catcher\ThrowableController::getFrames
     * @covers \MensBeam\Catcher\ThrowableController::getPrevious
     * @covers \MensBeam\Catcher\ThrowableController::getThrowable
     */
    public function testMethod_handleCallback(): void {
        $c = new ThrowableController(new \Exception(message: 'Ook!', previous: new \Error(message: 'Eek!', previous: new Error(message: 'Ack!', code: \E_USER_ERROR))));
        $l = Phony::mock(LoggerInterface::class);
        $h = new PlainTextHandler([ 
            'logger' => $l->get(), 
            'outputBacktrace' => true,
            'outputToStderr' => false
        ]);
        $o = $h->handle($c);
        $this->assertSame(Handler::CONTINUE, $o['controlCode']);
        $this->assertSame(Handler::OUTPUT | Handler::NOW, $o['outputCode']);
        $this->assertTrue(isset($o['previous']));

        ob_start();
        $h->dispatch();
        ob_end_clean();

        $l->critical->called();

        $c = new ThrowableController(new \Exception(message: 'Ook!', previous: new \Error(message: 'Eek!', previous: new Error(message: 'Ack!', code: \E_USER_ERROR))));
        $l = Phony::mock(LoggerInterface::class);
        $h = new PlainTextHandler([ 
            'logger' => $l->get(), 
            'silent' => true
        ]);
        $o = $h->handle($c);
        $this->assertSame(Handler::CONTINUE, $o['controlCode']);
        $this->assertSame(Handler::SILENT | Handler::NOW, $o['outputCode']);
        $this->assertTrue(isset($o['previous']));

        ob_start();
        $h->dispatch();
        ob_end_clean();

        $l->critical->called();
    }


    /**
     * @covers \MensBeam\Catcher\PlainTextHandler::log
     * 
     * @covers \MensBeam\Catcher\Error::__construct
     * @covers \MensBeam\Catcher\Handler::__construct
     * @covers \MensBeam\Catcher\Handler::buildOutputArray
     * @covers \MensBeam\Catcher\Handler::handle
     * @covers \MensBeam\Catcher\PlainTextHandler::dispatchCallback
     * @covers \MensBeam\Catcher\PlainTextHandler::handleCallback
     * @covers \MensBeam\Catcher\PlainTextHandler::dispatchCallback
     * @covers \MensBeam\Catcher\ThrowableController::__construct
     * @covers \MensBeam\Catcher\ThrowableController::getErrorType
     * @covers \MensBeam\Catcher\ThrowableController::getPrevious
     * @covers \MensBeam\Catcher\ThrowableController::getThrowable
     */
    public function testMethod_log(): void {
        $l = Phony::mock(LoggerInterface::class);
        $h = new PlainTextHandler([ 
            'logger' => $l->get(),
            'outputToStderr' => false
        ]);

        $e = [
            'notice' => [
                \E_NOTICE,
                \E_USER_NOTICE,
                \E_STRICT
            ],
            'warning' => [
                \E_WARNING,
                \E_COMPILE_WARNING,
                \E_USER_WARNING,
                \E_DEPRECATED,
                \E_USER_DEPRECATED
            ],
            'error' => [
                \E_RECOVERABLE_ERROR
            ],
            'alert' => [
                \E_PARSE,
                \E_CORE_ERROR,
                \E_COMPILE_ERROR
            ]
        ];
        
        foreach ($e as $k => $v) {
            foreach ($v as $vv) {
                $h->handle(new ThrowableController(new Error('Ook!', $vv)));

                ob_start();
                $h->dispatch();
                ob_end_clean();

                $l->$k->called();
            }
        }

        $h->handle(new ThrowableController(new \PharException('Ook!')));
        ob_start();
        $h->dispatch();
        ob_end_clean();
        $l->alert->called();

        $h->handle(new ThrowableController(new \RuntimeException('Ook!')));
        ob_start();
        $h->dispatch();
        ob_end_clean();
        $l->alert->called();
    }
}