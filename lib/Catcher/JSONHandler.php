<?php
/** 
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details 
 */

declare(strict_types=1);
namespace MensBeam\Foundation\Catcher;


class JSONHandler extends Handler {
    public const CONTENT_TYPE = 'application/json';


    protected function dispatchCallback(): void {
        foreach ($this->outputBuffer as $key => $value) {
            if ($value['outputCode'] & self::SILENT) {
                unset($this->outputBuffer[$key]);
                continue;
            }

            $this->outputBuffer[$key] = $this->cleanOutputThrowable($this->outputBuffer[$key]);
        }

        if (count($this->outputBuffer) > 0) {
            $this->print(json_encode([
                'errors' => $this->outputBuffer
            ], \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_PARTIAL_OUTPUT_ON_ERROR | \JSON_UNESCAPED_SLASHES));
        }
    }
}