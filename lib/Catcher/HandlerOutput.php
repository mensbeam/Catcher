<?php
/** 
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details 
 */

declare(strict_types=1);
namespace MensBeam\Framework\Catcher;


class HandlerOutput {
    public readonly int $controlCode;
    public readonly mixed $output;
    public readonly int $outputCode;


    public function __construct(int $controlCode, int $outputCode, mixed $output) {
        $this->controlCode = $controlCode;
        $this->outputCode = $outputCode;
        $this->output = $output;
    }
}