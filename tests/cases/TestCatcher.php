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
        $this->assertSame(PlainTextHandler::class, $c->getHandlers()[0]::class);
        $c->__destruct();

        $c = new Catcher(
            new PlainTextHandler(),
            new HTMLHandler(),
            new JSONHandler()
        );
        $this->assertSame('MensBeam\Framework\Catcher', $c::class);
        $this->assertEquals(3, count($c->getHandlers()));
        $h = $c->getHandlers();
        $this->assertSame(PlainTextHandler::class, $h[0]::class);
        $this->assertSame(HTMLHandler::class, $h[1]::class);
        $this->assertSame(JSONHandler::class, $h[2]::class);
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
        $e = null;
        set_error_handler(function($errno) use (&$e) {
            $e = $errno;
        });

        $h = new PlainTextHandler();
        $c = new Catcher($h, $h);
        $c->__destruct();

        restore_error_handler();
        $this->assertEquals(\E_USER_WARNING, $e);
    }

    /**
     * @covers \MensBeam\Framework\Catcher::removeHandler()
     * 
     * @covers \MensBeam\Framework\Catcher::__construct()
     * @covers \MensBeam\Framework\Catcher::__destruct()
     * @covers \MensBeam\Framework\Catcher::getHandlers()
     * @covers \MensBeam\Framework\Catcher::removeHandler()
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

        $e = null;
        try {
            $h = [
                new PlainTextHandler(),
                new HTMLHandler(),
            ];
            $c = new Catcher(...$h);
            $c->removeHandler(...$h);
        } catch (\Throwable $t) {
            $e = $t::class;
        } finally {
            $c->__destruct();
            $this->assertSame(\Exception::class, $e);
        }
    }

    /**
     * @covers \MensBeam\Framework\Catcher::setHandlers()
     * 
     * @covers \MensBeam\Framework\Catcher::__construct()
     * @covers \MensBeam\Framework\Catcher::__destruct()
     * @covers \MensBeam\Framework\Catcher::getHandlers()
     * @covers \MensBeam\Framework\Catcher\Handler::__construct()
     */
    public function testMethod_setHandlers(): void {
        $c = new Catcher();
        $c->setHandlers(new PlainTextHandler());
        $h = $c->getHandlers();
        $this->assertEquals(1, count($h));
        $this->assertSame(PlainTextHandler::class, $h[0]::class);
        $c->__destruct();
    }

    /**
     * @covers \MensBeam\Framework\Catcher::unshiftHandler()
     * 
     * @covers \MensBeam\Framework\Catcher::__construct()
     * @covers \MensBeam\Framework\Catcher::__destruct()
     * @covers \MensBeam\Framework\Catcher\Handler::__construct()
     */
    public function testMethod_unshiftHandler(): void {
        $c = new Catcher(new PlainTextHandler());
        $c->unshiftHandler(new JSONHandler(), new HTMLHandler(), new PlainTextHandler());
        $h = $c->getHandlers();
        $this->assertEquals(4, count($h));
        $this->assertSame(JSONHandler::class, $h[0]::class);
        $this->assertSame(HTMLHandler::class, $h[1]::class);
        $this->assertSame(PlainTextHandler::class, $h[2]::class);
        $this->assertSame(PlainTextHandler::class, $h[3]::class);

        $e = null;
        set_error_handler(function($errno) use (&$e) {
            $e = $errno;
        });

        $c->unshiftHandler($h[0]);
        $c->__destruct();

        restore_error_handler();
        $this->assertEquals(\E_USER_WARNING, $e);
    }
}