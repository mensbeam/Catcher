<?php
/**
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details
 */

declare(strict_types=1);
namespace MensBeam\Catcher\Test;
use MensBeam\Catcher\Handler;


class TestingHandler extends Handler {
    public array $output = [];

    protected ?string $_name = null;
    // Could just use silent option instead, but we need to test Handler::SILENT
    protected bool $_print = false;
    // When printing output, print it as JSON
    protected bool $_printJSON = true;


    protected function handleCallback(array $output): array {
        $output['code'] = (\PHP_SAPI === 'cli') ? $output['code'] | self::NOW : $output['code'];
        return $output;
    }

    protected function invokeCallback(): void {
        foreach ($this->outputBuffer as $o) {
            if ($o['code'] & self::LOG) {
                $this->log($o['controller']->getThrowable(), json_encode([
                    'class' => $o['class'],
                    'code' => $o['code'],
                    'file' => $o['file'],
                    'line' => $o['line'],
                    'message' => $o['message']
                ]));
            }

            if (($o['code'] & self::OUTPUT) === 0) {
                continue;
            }

            $o = $this->cleanOutputThrowable($o);

            if ($this->_print) {
                if (!$this->_printJSON) {
                    $oo = '';
                    foreach ($o['frames'] as $f) {
                        if (!isset($f['args'])) {
                            continue;
                        }
                        $oo .= $this->serializeArgs($f['args']) . "\n";
                    }
                    $oo = rtrim($oo);
                } else {
                    $oo = json_encode($o, \JSON_THROW_ON_ERROR);
                }

                $this->print($oo);
            }

            $this->output[] = $o;
        }
    }
}