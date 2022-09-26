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


class PlainTextHandler extends ThrowableHandler implements LoggerAwareInterface {
    public const CONTENT_TYPE = 'text/plain';

    /** The number of backtrace frames in which to print arguments; defaults to 5 */
    protected int $_backtraceArgFrameLimit = 5;
    /** The PSR-3 compatible logger in which to log to; defaults to null (no logging) */
    protected ?LoggerInterface $_logger = null;
    /** If true the handler will output backtraces; defaults to false */
    protected bool $_outputBacktrace = false;
    /** If true the handler will output previous throwables; defaults to true */
    protected bool $_outputPrevious = true;
    /** 
     * If true the handler will output times to the output. This is ignored by the 
     * logger which should have its own timestamping methods; defaults to true 
     */
    protected bool $_outputTime = true;
    /** The PHP-standard date format which to use for times printed to output */
    protected string $_timeFormat = '[H:i:s]';




    public function __construct(array $config = []) {
        parent::__construct($config);
    }




    public function getBacktraceArgFrameLimit(): int {
        return $this->_getBacktraceArgFrameLimit;
    }

    public function getLogger(): ?LoggerInterface {
        return $this->_logger;
    }

    public function getOutputBacktrace(): bool {
        return $this->_outputBacktrace;
    }

    public function getOutputPrevious(): bool {
        return $this->_outputPrevious;
    }

    public function getOutputTime(): bool {
        return $this->_outputTime;
    }

    public function getTimeFormat(): bool {
        return $this->_timeFormat;
    }

    public function handle(\Throwable $throwable, ThrowableController $controller): bool {
        // If this can't output and there's no logger to log to then there's nothing to do 
        // here. Continue on to the next handler.
        if (!$this->_output && $this->_logger === null) {
            return false;
        }

        $message = $this->serializeThrowable($throwable, $controller);
        if ($this->_outputPrevious) {
            $prev = $throwable->getPrevious();
            $prevController = $controller->getPrevious();
            while ($prev) {
                $message .= sprintf("\n\nCaused by ↴\n%s", $this->serializeThrowable($prev, $prevController));
                $prev = $prev->getPrevious();
                $prevController = $prevController->getPrevious();
            }
        }

        if ($this->_outputBacktrace) {
            $frames = $controller->getFrames();
            $message .= "\nStack trace:";

            $num = 1;
            foreach ($frames as $frame) {
                $class = (!empty($frame['error'])) ? "{$frame['error']} ({$frame['class']})" : $frame['class'] ?? '';
                $function = $frame['function'] ?? '';

                $args = '';
                if (!empty($frame['args']) && $this->_backtraceArgFrameLimit >= $num) {
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

        if ($this->_logger !== null) {
            $this->log($throwable, $message);
        }

        if ($this->_output) {
            // Logger handles its own timestamps
            if ($this->_outputTime && $this->_timeFormat !== '') {
                $time = (new \DateTime())->format($this->_timeFormat) . '  ';
                $timeStrlen = strlen($time);

                $message = preg_replace('/^/m', str_repeat(' ', $timeStrlen), $message);
                $message = preg_replace('/^ {' . $timeStrlen . '}/', $time, $message);
            }

            if (\PHP_SAPI === 'cli') {
                fprintf(\STDERR, "$message\n");
            } else {
                $this->sendContentTypeHeader();
                http_response_code(500);
                echo "$message\n";
            }
    
            return (!$this->_passthrough);
        }

        return false;
    }

    public function setBacktraceArgFrameLimit(int $value): void {
        $this->_getBacktraceArgFrameLimit = $value;
    }

    public function setLogger(?LoggerInterface $value): void {
        $this->_logger = $value;
    }

    public function setOutputBacktrace(bool $value): void {
        $this->_outputBacktrace = $value;
    }

    public function setOutputPrevious(bool $value): void {
        $this->_outputPrevious = $value;
    }

    public function setOutputTime(bool $value): void {
        $this->_outputTime = $value;
    }

    public function setTimeFormat(bool $value): void {
        $this->_timeFormat = $value;
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
            }
        } elseif ($throwable instanceof \Exception) {
            if ($throwable instanceof \PharException || $throwable instanceof \RuntimeException) {
                $this->_logger->alert($message);
            }
        }

        $this->_logger->critical($message);
    }

    protected function serializeThrowable(\Throwable $throwable, ThrowableController $controller): string {
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