<?php
/** 
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details 
 */

declare(strict_types=1);
namespace Mensbeam\Framework\Catcher;
use \Psr\Log\{
    LoggerAwareInterface,
    LoggerInterface
};


class PlainTextHandler extends Handler implements LoggerAwareInterface {
    public const CONTENT_TYPE = 'text/plain';

    /** The number of backtrace frames in which to print arguments; defaults to 5 */
    protected static int $_backtraceArgFrameLimit = 5;
    /** The PSR-3 compatible logger in which to log to; defaults to null (no logging) */
    protected static ?LoggerInterface $_logger = null;
    /** If true the handler will output backtraces; defaults to false */
    protected static bool $_outputBacktrace = false;
    /** If true the handler will output previous throwables; defaults to true */
    protected static bool $_outputPrevious = true;
    /** 
     * If true the handler will output times to the output. This is ignored by the 
     * logger which should have its own timestamping methods; defaults to true 
     */
    protected static bool $_outputTime = true;
    /** The PHP-standard date format which to use for times printed to output */
    protected static string $_timeFormat = '[H:i:s]';


    public static function create(ThrowableController $controller): self {
        $message = null;
        if (self::$_logger !== null) {
            $message = self::serialize($controller);
            self::$log($controller->getThrowable(), $message);
        }

        return new self(
            controller: $controller,
            data: [ 'message' => $message ]
        );
    }

    public function output(): void {
        $message = $this->data['message'] ?? self::serialize($this->controller);
        if (self::$_outputTime && self::$_timeFormat !== '') {
            $time = (new \DateTime())->format(self::$_timeFormat) . '  ';
            $timeStrlen = strlen($time);

            $message = preg_replace('/^/m', str_repeat(' ', $timeStrlen), $message);
            $message = preg_replace('/^ {' . $timeStrlen . '}/', $time, $message);
        }


        if (!Catcher::isHTTPRequest()) {
            fprintf(\STDERR, "$message\n");
        } else {
            echo "$message\n";
        }
    }

    public function getOutputCode(): int {
        if ($this->outputCode !== null) {
            return $this->outputCode;
        }

        $code = parent::getOutputCode();
        // When the sapi is CLI we want to output as soon as possible if 
        $this->outputCode = ($code & self::OUTPUT === 0 && \PHP_SAPI === 'CLI') ? $code &~ self::OUTPUT | self::OUTPUT_NOW : $code;
        return $this->outputCode;
    }


    protected static function log(\Throwable $throwable, string $message): void {
        if ($throwable instanceof \Error) {
            switch ($throwable->getCode()) {
                case \E_NOTICE:
                case \E_USER_NOTICE:
                case \E_STRICT:
                    self::$_logger->notice($message);
                break;
                case \E_WARNING:
                case \E_COMPILE_WARNING:
                case \E_USER_WARNING:
                case \E_DEPRECATED:
                case \E_USER_DEPRECATED:
                    self::$_logger->warning($message);
                break;
                case \E_RECOVERABLE_ERROR:
                    self::$_logger->error($message);
                break;
                case \E_PARSE:
                case \E_CORE_ERROR:
                case \E_COMPILE_ERROR:
                    self::$_logger->alert($message);
                break;
            }
        } elseif ($throwable instanceof \Exception) {
            if ($throwable instanceof \PharException || $throwable instanceof \RuntimeException) {
                self::$_logger->alert($message);
            }
        } else {
            self::$_logger->critical($message);
        }
    }

    protected function prependTimestamps(string $message): string {
        $time = (new \DateTime())->format(self::$_timeFormat) . '  ';
        $timeStrlen = strlen($time);

        $message = preg_replace('/^/m', str_repeat(' ', $timeStrlen), $message);
        return preg_replace('/^ {' . $timeStrlen . '}/', $time, $message);
    }

    protected static function serialize(ThrowableController $controller): string {
        $message = self::$serializeThrowable($controller);
        if (self::$_outputPrevious) {
            $prev = $throwable->getPrevious();
            $prevController = $controller->getPrevious();
            while ($prev) {
                $message .= sprintf("\n\nCaused by ↴\n%s", self::$serializeThrowable($prev, $prevController));
                $prev = $prev->getPrevious();
                $prevController = $prevController->getPrevious();
            }
        }

        if (self::$_outputBacktrace) {
            $frames = $controller->getFrames();
            $message .= "\nStack trace:";

            $num = 1;
            foreach ($frames as $frame) {
                $class = (!empty($frame['error'])) ? "{$frame['error']} ({$frame['class']})" : $frame['class'] ?? '';
                $function = $frame['function'] ?? '';

                $args = '';
                if (!empty($frame['args']) && self::$_backtraceArgFrameLimit >= $num) {
                    $args = "\n" . preg_replace('/^/m', str_repeat(' ', strlen((string)$num) + 2) . '| ', var_export($frame['args'], true));
                }

                $template = "\n%3d. %s";
                if ($class && $function) {
                    $template .= '::';
                }
                $template .= ($function) ? '%s()' : '%s';
                $template .= '  %s:%d%s';

                $message .= sprintf(
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

        return $message;
    }

    protected static function serializeThrowable(ThrowableController $controller): string {
        $throwable = $controller->getThrowable();
        $class = $throwable::class;
        if ($throwable instanceof \Error) {
            $type = $controller->getErrorType();
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