<?php
/**
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details
 */

declare(strict_types=1);
namespace MensBeam\Catcher;

class Error extends \Error {
    public function __construct(string $message = '', int $code = 0, string $file = '', int $line = 0, ?\Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->file = $file;
        $this->line = $line;
    }
}