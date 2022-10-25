<?php
/** 
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details 
 */

declare(strict_types=1);
namespace MensBeam\Framework\TestCase;
use MensBeam\Framework\Catcher;
use MensBeam\Framework\Catcher\{
    PlainTextHandler,
    HTMLHandler,
    JSONHandler
};


class TestCatcher extends \PHPUnit\Framework\TestCase {

    /**
     * @covers \MensBeam\Framework\Catcher::__construct()
     * 
     * @covers \MensBeam\Framework\Catcher::getHandlers()
     * @covers \MensBeam\Framework\Catcher::pushHandler()
     * @covers \MensBeam\Framework\Catcher::__destruct()
     * @covers \MensBeam\Framework\Catcher\Handler::__construct()
     */
    public function testMethod___construct(): void {
        $c = new Catcher();
        $this->assertSame('MensBeam\Framework\Catcher', $c::class);
        $this->assertEquals(1, count($c->getHandlers()));
        $c->__destruct();

        $c = new Catcher(
            new PlainTextHandler(),
            new HTMLHandler(),
            new JSONHandler()
        );
        $this->assertSame('MensBeam\Framework\Catcher', $c::class);
        $this->assertEquals(3, count($c->getHandlers()));
        $c->__destruct();
    }

    /**
     * @covers \MensBeam\Framework\Catcher::pushHandler()
     * 
     * @covers \MensBeam\Framework\Catcher::__construct()
     * @covers \MensBeam\Framework\Catcher::__destruct()
     * @covers \MensBeam\Framework\Catcher\Handler::__construct()
     */
    public function testMethod_pushHandler__warning(): void {
        set_error_handler(function($errno) {
            $this->assertEquals(\E_USER_WARNING, $errno);
        });

        $h = new PlainTextHandler();
        $c = new Catcher($h, $h);
        $c->__destruct();

        restore_error_handler();
    }

    /**
     * @covers \MensBeam\Framework\Catcher::removeHandler()
     * 
     * @covers \MensBeam\Framework\Catcher::__construct()
     * @covers \MensBeam\Framework\Catcher::__destruct()
     * @covers \MensBeam\Framework\Catcher\Handler::__construct()
     */
    public function testMethod_removeHandler(): void {
        $h = new HTMLHandler();
        $c = new Catcher(
            new PlainTextHandler(),
            $h
        );
        $this->assertEquals(2, count($c->getHandlers()));
        $c->removeHandler($h);
        $this->assertEquals(1, count($c->getHandlers()));
        $c->__destruct();
    }

    /**
     * @covers \MensBeam\Framework\Catcher::removeHandler()
     * 
     * @covers \MensBeam\Framework\Catcher::__construct()
     * @covers \MensBeam\Framework\Catcher::__destruct()
     * @covers \MensBeam\Framework\Catcher\Handler::__construct()
     */
    public function testMethod_removeHandler__exception(): void {
        try {
            $h = [
                new PlainTextHandler(),
                new HTMLHandler(),
            ];
            $c = new Catcher(...$h);
            $c->removeHandler(...$h);
        } catch (\Exception $e) {
            $this->assertSame(\Exception::class, $e::class);
        } finally {
            $c->__destruct();
        }
    }
}