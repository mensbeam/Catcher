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


class TestPlainTextHandler extends \Giberti\PHPUnitLocalServer\LocalServerTestCase {
    public static function setupBeforeClass(): void {
        static::createServerWithDocroot('./tests/docroot');
    }

    /**
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     */
    public function testMethod___construct__exception(): void {
        $this->expectException(\InvalidArgumentException::class);
        $c = new Catcher(new PlainTextHandler([ 'httpCode' => 42 ]));
    }

    /**
     * @covers \MensBeam\Foundation\Catcher\Handler::dispatch
     * 
     * @covers \MensBeam\Foundation\Catcher::__construct
     * @covers \MensBeam\Foundation\Catcher::handleError
     * @covers \MensBeam\Foundation\Catcher::handleThrowable
     * @covers \MensBeam\Foundation\Catcher::pushHandler
     * @covers \MensBeam\Foundation\Catcher::register
     * @covers \MensBeam\Foundation\Catcher\Error::__construct
     * @covers \MensBeam\Foundation\Catcher\Handler::__construct
     * @covers \MensBeam\Foundation\Catcher\Handler::getControlCode
     * @covers \MensBeam\Foundation\Catcher\Handler::getOutputCode
     * @covers \MensBeam\Foundation\Catcher\Handler::handle
     * @covers \MensBeam\Foundation\Catcher\Handler::print
     * @covers \MensBeam\Foundation\Catcher\HandlerOutput::__construct
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::dispatchCallback
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::handleCallback
     * @covers \MensBeam\Foundation\Catcher\PlainTextHandler::serializeThrowable
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::__construct
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getErrorType
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getPrevious
     * @covers \MensBeam\Foundation\Catcher\ThrowableController::getThrowable
     */
    public function testMethod__dispatch(): void {
        $url = $this->getLocalServerUrl() . '/testDispatch.php';
        $this->assertEquals(1, preg_match('/^\[[0-9:]+\]\s+Warning/', file_get_contents($url)));
    }
}