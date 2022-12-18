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
    Handler,
    PlainTextHandler,
    ThrowableController
};
use Eloquent\Phony\Phpunit\Phony,
    Psr\Log\LoggerInterface;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class TestPlainTextHandler extends \PHPUnit\Framework\TestCase {
    /**
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::handleCallback
     * 
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     * @covers \MensBeam\Foundation\Catcher\Handler::getControlCode
     * @covers \MensBeam\Foundation\Catcher\Handler::getOutputCode
     * @covers \MensBeam\Foundation\Catcher\Handler::handle
     * @covers \MensBeam\Foundation\Catcher\HandlerOutput::__construct
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::log
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::serializeThrowable
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::__construct
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getErrorType
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getFrames
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getPrevious
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getThrowable
     */
    public function testMethod_handleCallback(): void {
        $c = new ThrowableController(new \Exception(message: 'Ook!', previous: new \Error('Eek!')));
        $l = Phony::mock(LoggerInterface::class);
        $h = new PlainTextHandler([ 
            'logger' => $l->get(), 
            'outputBacktrace' => true 
        ]);
        $o = $h->handle($c);
        $this->assertSame(Handler::CONTINUE, $o->controlCode);
        $this->assertSame(Handler::OUTPUT | Handler::NOW, $o->outputCode);
        $this->assertStringContainsString('↳', $o->output);
        $l->critical->called();
    }


    /**
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::log
     * 
     * @covers \MensBeam\Foundation\Catcher\Error::__construct
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     * @covers \MensBeam\Foundation\Catcher\Handler::getControlCode
     * @covers \MensBeam\Foundation\Catcher\Handler::getOutputCode
     * @covers \MensBeam\Foundation\Catcher\Handler::handle
     * @covers \MensBeam\Foundation\Catcher\HandlerOutput::__construct
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::handleCallback
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::serializeThrowable
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::__construct
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getErrorType
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getPrevious
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getThrowable
     */
    public function testMethod_log(): void {
        $l = Phony::mock(LoggerInterface::class);
        $h = new PlainTextHandler([ 'logger' => $l->get() ]);

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
                $l->$k->called();
            }
        }

        $h->handle(new ThrowableController(new \PharException('Ook!')));
        $l->alert->called();

        $h->handle(new ThrowableController(new \RuntimeException('Ook!')));
        $l->alert->called();
    }
}