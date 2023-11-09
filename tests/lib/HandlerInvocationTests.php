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
    Handler
};


trait HandlerInvocationTests {
    public static function provideInvocationTests(): iterable {
        $options = [
            [ new \Exception('Ook!'), false, true, 'critical', null ],
            [ new \Exception('Ook!'), false, true, 'critical', [ \Exception::class ] ],
            [ new \Error('Ook!'), true, false, null, null ],
            [ new \Error('Ook!'), true, false, null, [ \Error::class ] ],
            [ new Error('Ook!', \E_ERROR, __FILE__, __LINE__), false, true, 'error', null ],
            [ new Error('Ook!', \E_ERROR, __FILE__, __LINE__), false, true, 'error', [ \E_ERROR ] ],
            [ new Error('Ook!', \E_ERROR, __FILE__, __LINE__), false, true, 'error', [ \E_ALL ] ],
            [ new Error('Ook!', \E_NOTICE, __FILE__, __LINE__), false, true, 'notice', null ],
            [ new Error('Ook!', \E_NOTICE, __FILE__, __LINE__), false, true, 'notice', [ \E_NOTICE ] ],
            [ new Error('Ook!', \E_NOTICE, __FILE__, __LINE__), false, true, 'notice', [ Handler::NON_FATAL_ERROR ] ],
            [ new \Exception(message: 'Ook!', previous: new \Error(message: 'Eek!', previous: new \ParseError('Ack!'))), true, true, 'critical', null ],
            [ new \Exception(message: 'Ook!', previous: new \Error(message: 'Eek!', previous: new \ParseError('Ack!'))), true, true, 'critical', [ \Exception::class ] ]
        ];

        $l = count($options);
        foreach ($options as $k => $o) {
            yield [ ...$o, __LINE__ - 4 - $l + $k ];
        }
    }
}