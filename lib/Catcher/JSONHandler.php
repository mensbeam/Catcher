<?php
/**
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details
 */

declare(strict_types=1);
namespace MensBeam\Catcher;


class JSONHandler extends Handler {
    public const CONTENT_TYPE = 'application/json';

    /** If true the handler will pretty print JSON output; defaults to true */
    protected bool $_prettyPrint = true;
    /** If true the handler will pretty print JSON output when logging; defaults to false */
    protected bool $_prettyPrintWhenLogging = false;


    protected function handleCallback(array $output): array {
        $output['code'] = (\PHP_SAPI === 'cli') ? $output['code'] | self::NOW : $output['code'];
        return $output;
    }

    protected function invokeCallback(): void {
        foreach ($this->outputBuffer as $o) {
            if (($o['code'] & self::OUTPUT) === 0) {
                if ($o['code'] & self::LOG) {
                    $this->serializeOutputThrowable($o);
                }

                continue;
            }

            $this->print($this->serializeOutputThrowable($o));
        }
    }


    protected function serializeOutputThrowable(array $outputThrowable, bool $previous = false): array|string {
        $controller = $outputThrowable['controller'];
        $output = $this->cleanOutputThrowable($outputThrowable);
        $jsonFlags = \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE;

        if (isset($outputThrowable['previous'])) {
            $output['previous'] = $this->serializeOutputThrowable($outputThrowable['previous'], true);
        }

        if (!$previous) {
            if (isset($outputThrowable['frames']) && is_array($outputThrowable['frames']) && count($outputThrowable['frames']) > 0) {
                $output['frames'] = $outputThrowable['frames'];
            }

            // The log message shouldn't have the timestamp added to it.
            if ($outputThrowable['code'] & self::LOG) {
                $o = $output;
                unset($o['time']);
                $this->log($controller->getThrowable(), json_encode($o, ($this->_prettyPrintWhenLogging) ? $jsonFlags | \JSON_PRETTY_PRINT : $jsonFlags));
            }

            $output = json_encode($output, ($this->_prettyPrint) ? $jsonFlags | \JSON_PRETTY_PRINT : $jsonFlags);
        }

        return $output;
    }
}