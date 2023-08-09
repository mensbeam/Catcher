<?php
/**
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details
 */

declare(strict_types=1);
namespace MensBeam\Catcher\Test;
use org\bovigo\vfs\vfsStream,
    Psr\Log\LoggerInterface,
    Psr\Log\LoggerTrait;


class FailLogger implements LoggerInterface {
    use LoggerTrait;

    public function log($level, string|\Stringable $message, array $context = []): void {
        $v = vfsStream::setup('ook');
        $d = vfsStream::newDirectory('ook', 0777)->at($v);
        file_put_contents($d->url(), $message);

        echo "Ook!\n";
    }

    public function error(string|\Stringable $message, array $context = []): void {
        trigger_error('Ook!', \E_USER_ERROR);
    }
}
