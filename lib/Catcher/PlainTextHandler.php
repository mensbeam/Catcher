<?php
/** 
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details 
 */

declare(strict_types=1);
namespace MensBeam\Foundation\Catcher;
use \Psr\Log\LoggerInterface;


class PlainTextHandler extends Handler {
    public const CONTENT_TYPE = 'text/plain';

    /** The PSR-3 compatible logger in which to log to; defaults to null (no logging) */
    protected ?LoggerInterface $_logger = null;
    /** If true the handler will output times to the output; defaults to true */
    protected bool $_outputTime = true;
    /** The PHP-standard date format which to use for timestamps in output */
    protected string $_timeFormat = '[H:i:s]';




    protected function dispatchCallback(): void {
        foreach ($this->outputBuffer as $o) {
            if ($o->outputCode & self::SILENT) {
                continue;
            }

            $this->print($o->output);
        }
    }

    protected function handleCallback(ThrowableController $controller): HandlerOutput {
        $output = $this->serializeThrowable($controller);
        if ($this->_outputPrevious) {
            $prevController = $controller->getPrevious();
            while ($prevController) {
                $output .= sprintf("\n\nCaused by ↴\n%s", $this->serializeThrowable($prevController));
                $prevController = $prevController->getPrevious();
            }
        }

        if ($this->_outputBacktrace) {
            $frames = $controller->getFrames();
            $output .= "\nStack trace:";

            $num = 1;
            $maxDigits = strlen((string)count($frames));
            foreach ($frames as $frame) {
                $class = (!empty($frame['error'])) ? "{$frame['error']} ({$frame['class']})" : $frame['class'] ?? '';
                $function = $frame['function'] ?? '';

                $args = '';
                if (!empty($frame['args']) && $this->_backtraceArgFrameLimit >= $num) {
                    $args = "\n" . preg_replace('/^/m', str_repeat(' ', $maxDigits) . '| ', var_export($frame['args'], true));
                }

                $template = "\n%{$maxDigits}d. %s";
                if ($class && $function) {
                    $template .= '::';
                }
                $template .= ($function) ? '%s()' : '%s';
                $template .= '  %s:%d%s';

                $output .= sprintf(
                    "$template\n",
                    $num++,
                    $class,
                    $function,
                    $frame['file'],
                    $frame['line'],
                    $args
                );
            }
        }

        // The logger will handle timestamps itself.
        if ($this->_logger !== null) {
            $this->log($controller->getThrowable(), $message);
        }

        if (!$this->_silent && $this->_outputTime && $this->_timeFormat !== '') {
            $time = (new \DateTime())->format($this->_timeFormat) . '  ';
            $timeStrlen = strlen($time);

            $output = preg_replace('/^/m', str_repeat(' ', $timeStrlen), $output);
            $output = preg_replace('/^ {' . $timeStrlen . '}/', $time, $output);
        }

        $outputCode = $this->getOutputCode();
        return new HandlerOutput($this->getControlCode(), (\PHP_SAPI === 'cli') ? $outputCode | self::NOW : $outputCode, $output);
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

    protected function serializeThrowable(ThrowableController $controller): string {
        $throwable = $controller->getThrowable();
        $class = $throwable::class;
        if ($throwable instanceof Error) {
            $type = $controller->getErrorType();
            $type = ($throwable instanceof Error) ? $controller->getErrorType() : null;
            $class = ($type !== null) ? "$type (" . $throwable::class . ")" : $throwable::class;
        }

        return sprintf(
            '%s: %s in file %s on line %d',
            $class,
            $throwable->getMessage(),
            $throwable->getFile(),
            $throwable->getLine()
        );
    }
}