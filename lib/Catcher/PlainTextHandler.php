<?php
/**
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details
 */

declare(strict_types=1);
namespace MensBeam\Catcher;


class PlainTextHandler extends Handler {
    public const CONTENT_TYPE = 'text/plain';

    /** The PHP-standard date format which to use for timestamps in output */
    protected string $_timeFormat = '[H:i:s]';


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

    protected function serializeOutputThrowable(array $outputThrowable, bool $previous = false): string {
        $class = $outputThrowable['class'] ?? null;
        if ($class !== null && !empty($outputThrowable['errorType'])) {
            $class = $outputThrowable['errorType'];
        }

        $output = sprintf(
            '%s: %s in file %s on line %d' . \PHP_EOL,
            $class,
            $outputThrowable['message'],
            $outputThrowable['file'],
            $outputThrowable['line']
        );

        if (isset($outputThrowable['previous']) && is_array($outputThrowable['previous'])) {
            if ($previous) {
                $output .= '  ';
            }
            $output .= '↳ ' . $this->serializeOutputThrowable($outputThrowable['previous'], true);
        }

        if (!$previous) {
            if (isset($outputThrowable['frames']) && is_array($outputThrowable['frames']) && count($outputThrowable['frames']) > 0) {
                $output .= \PHP_EOL . 'Stack trace:' . \PHP_EOL;
                $maxDigits = strlen((string)count($outputThrowable['frames']));
                $indent = str_repeat(' ', $maxDigits);
                foreach ($outputThrowable['frames'] as $key => $frame) {
                    $method = $frame['class'] ?? "{$frame['function']}()" ?? null;
                    if (isset($frame['class']) && $method === $frame['class']) {
                        if (isset($frame['errorType'])) {
                            $method = "{$frame['errorType']} ({$frame['class']})";
                        } elseif (isset($frame['function'])) {
                            if (str_starts_with($frame['function'], '{closure')) {
                                // We have no way of automatically testing this
                                $method = "{$frame['function']}()"; // @codeCoverageIgnore
                            } else {
                                $ref = new \ReflectionMethod($frame['class'], $frame['function']);
                                $method .= (($ref->isStatic()) ? '::' : '->') . $frame['function'] . '()';
                            }
                        }
                    }

                    $output .= sprintf("%{$maxDigits}d. %s  %s:%d" . \PHP_EOL,
                        $key + 1,
                        $method,
                        $frame['file'],
                        $frame['line']
                    );

                    if (isset($frame['args']) && count($frame['args']) > 0 && $this->_backtraceArgFrameLimit > $key) {
                        $output .= preg_replace('/^/m', "$indent| ", $this->serializeArgs($frame['args'])) . \PHP_EOL;
                    }
                }

                $output = rtrim($output) . \PHP_EOL;
            }

            // The log message shouldn't have the timestamp added to it.
            if ($outputThrowable['code'] & self::LOG) {
                $this->log($outputThrowable['controller']->getThrowable(), $output);
            }

            if (isset($outputThrowable['time']) && $outputThrowable['time'] instanceof \DateTimeInterface) {
                $timestamp = $outputThrowable['time']->format($this->_timeFormat) . '  ';
                $output = ltrim(preg_replace('/^(?=\h*\S)/m', str_repeat(' ', strlen($timestamp)), "$timestamp$output"));
            }
        }

        return $output;
    }
}