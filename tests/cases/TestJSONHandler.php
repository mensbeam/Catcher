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
    JSONHandler,
    ThrowableController
};


class TestJSONHandler extends \PHPUnit\Framework\TestCase {
    /**
     * @covers \MensBeam\Foundation\Catcher\JSONHandler::dispatchCallback
     */
    public function testMethod_dispatchCallback(): void {
        $c = new ThrowableController(new \Exception(message: 'Ook!', previous: new Error(message: 'Eek!', code: \E_USER_ERROR, previous: new Error(message: 'Ack!'))));
        $h = new JSONHandler([
            'silent' => true,
            'outputToStderr' => false
        ]);
        $o = $h->handle($c);
        
        ob_start();
        $h->dispatch();
        $o = ob_get_clean();
        $this->assertEmpty($o);
    }
}