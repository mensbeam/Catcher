<?php
/** 
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details 
 */

declare(strict_types=1);
namespace MensBeam\Catcher;
use Psr\Log\LoggerInterface;


class PlainTextHandler extends Handler {
    public const CONTENT_TYPE = 'text/plain';

    /** The PSR-3 compatible logger in which to log to; defaults to null (no logging) */
    protected ?LoggerInterface $_logger = null;
    /** The PHP-standard date format which to use for timestamps in output */
    protected string $_timeFormat = '[H:i:s]';



    protected function dispatchCallback(): void {
        if ($this->_logger) {
            foreach ($this->outputBuffer as $o) {
                $output = $this->serializeOutputThrowable($o);
                if ($o['outputCode'] & self::SILENT) {
                    continue;
                }

                $this->print($output);
            }
        } else {
            foreach ($this->outputBuffer as $o) {
                if ($o['outputCode'] & self::SILENT) {
                    continue;
                }
    
                $this->print($this->serializeOutputThrowable($o));
            }
        }
    }

    protected function handleCallback(array $output): array {
        $output['outputCode'] = (\PHP_SAPI === 'cli') ? $output['outputCode'] | self::NOW : $output['outputCode'];
        return $output;
    }

    protected function log(\Throwable $throwable, string $message): void {
        if ($throwable instanceof \Error) {
            switch ($throwable->getCode()) {
                case \E_NOTICE:
                case \E_USER_NOTICE:
                case \E_STRICT:
                    $this->_logger->notice($message);
                break;
                case \E_WARNING:
                case \E_COMPILE_WARNING:
                case \E_USER_WARNING:
                case \E_DEPRECATED:
                case \E_USER_DEPRECATED:
                    $this->_logger->warning($message);
                break;
                case \E_RECOVERABLE_ERROR:
                    $this->_logger->error($message);
                break;
                case \E_PARSE:
                case \E_CORE_ERROR:
                case \E_COMPILE_ERROR:
                    $this->_logger->alert($message);
                break;
                default: $this->_logger->critical($message);
            }
        } elseif ($throwable instanceof \Exception && ($throwable instanceof \PharException || $throwable instanceof \RuntimeException)) {
            $this->_logger->alert($message);
        } else {
            $this->_logger->critical($message);
        }
    }

    protected function serializeOutputThrowable(array $outputThrowable, bool $previous = false): string {
        $class = $outputThrowable['class'] ?? null;
        if ($class !== null && !empty($outputThrowable['errorType'])) {
            $class = "{$outputThrowable['errorType']} ($class)";
        }
        
        $output = sprintf(
            '%s: %s in file %s on line %d' . \PHP_EOL,
            $class,
            $outputThrowable['message'],
            $outputThrowable['file'],
            $outputThrowable['line']
        );

        if (!empty($outputThrowable['previous'])) {
            if ($previous) {
                $output .= '  ';
            }
            $output .= '↳ ' . $this->serializeOutputThrowable($outputThrowable['previous'], true);
        }

        if (!$previous) {
            if (isset($outputThrowable['frames']) && count($outputThrowable['frames']) > 0) {
                $output .= \PHP_EOL . 'Stack trace:' . \PHP_EOL;
                $maxDigits = strlen((string)count($outputThrowable['frames']));
                $indent = str_repeat(' ', $maxDigits);
                foreach ($outputThrowable['frames'] as $key => $frame) {
                    $method = null;
                    if (!empty($frame['class'])) {
                        if (!empty($frame['errorType'])) {
                            $method = "{$frame['errorType']} ({$frame['class']})";
                        } else {
                            $method = $frame['class'];
                            if (!empty($frame['function'])) {
                                $method .= "::{$frame['function']}";
                            }
                        }
                    } elseif (!empty($frame['function'])) {
                        $method = $frame['function'];
                    }
                    
                    $output .= sprintf("%{$maxDigits}d. %s  %s:%d" . \PHP_EOL,
                        $key + 1,
                        $method,
                        $frame['file'],
                        $frame['line']
                    );

                    if (!empty($frame['args']) && $this->_backtraceArgFrameLimit > $key) {
                        $varExporter = $this->_varExporter;
                        $output .= preg_replace('/^/m', "$indent| ", $varExporter($frame['args'])) . \PHP_EOL;
                    }
                }

                $output = rtrim($output) . \PHP_EOL;
            }

            if (!empty($this->_logger)) {
                $this->log($outputThrowable['controller']->getThrowable(), $output);
            }

            if (!empty($outputThrowable['time'])) {
                $timestamp = $outputThrowable['time']->format($this->_timeFormat) . '  ';
                $output = ltrim(preg_replace('/^/m', str_repeat(' ', strlen($timestamp)), "$timestamp$output"));
            }
        }

        return $output;
    }
}