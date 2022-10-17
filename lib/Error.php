<?php
/** 
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details 
 */

declare(strict_types=1);
namespace MensBeam\Framework;

class Error extends \Error {
    public function __construct(string $message = '', int $code = 0, ?string $file = null, ?int $line = line, ?\Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->file = $file;
        $this->line = $line;
    }
}