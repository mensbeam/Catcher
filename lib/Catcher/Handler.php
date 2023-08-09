<?php
/**
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details
 */

declare(strict_types=1);
namespace MensBeam\Catcher;
use MensBeam\Catcher,
    Psr\Log\LoggerInterface;


abstract class Handler {
    public const CONTENT_TYPE = null;

    // Control constants
    public const BUBBLES = 1;
    public const EXIT = 2;
    public const LOG = 4;
    public const NOW = 8;
    public const OUTPUT = 16;

    /**
     * Array of HandlerOutputs the handler creates
     *
     * @var array[]
     */
    protected array $outputBuffer = [];

    /** The number of backtrace frames in which to print arguments; defaults to 5 */
    protected int $_backtraceArgFrameLimit = 5;
    /** If true the handler will move onto the next item in the stack of handlers */
    protected bool $_bubbles = true;
    /**
     * The character encoding used for errors; only used if headers weren't sent before
     * an error occurred
     */
    protected string $_charset = 'UTF-8';
    /** If true the handler will force an exit after all handlers have run */
    protected bool $_forceExit = false;
    /** If true the handler will output as soon as possible, unless silenced */
    protected bool $_forceOutputNow = false;
    /** The HTTP code to be sent; possible values: 200, 400-599 */
    protected int $_httpCode = 500;
    /** The PSR-3 compatible logger in which to log to; defaults to null (no logging) */
    protected ?LoggerInterface $_logger = null;
    /** When set to true the handler will still send logs when silent */
    protected bool $_logWhenSilent = true;
    /** If true the handler will output backtraces; defaults to false */
    protected bool $_outputBacktrace = false;
    /** If true the handler will output previous throwables; defaults to true */
    protected bool $_outputPrevious = true;
    /** If true the handler will output times to the output; defaults to true */
    protected bool $_outputTime = true;
    /** When the SAPI is cli output errors to stderr; defaults to true */
    protected bool $_outputToStderr = true;
    /** If true the handler will be silent and won't output */
    protected bool $_silent = false;
    /** The PHP-standard date format which to use for timestamps in output */
    protected string $_timeFormat = 'Y-m-d\TH:i:s.vO';




    public function __construct(array $options = []) {
        foreach ($options as $key => $value) {
            $this->setOption($key, $value);
        }
    }




    public function __invoke(): void {
        if (count($this->outputBuffer) === 0) {
            return;
        }

        // Send the headers if possible and necessary
        if (isset($_SERVER['REQUEST_URI'])) {
            // Can't figure out a way to test coverage here
            // @codeCoverageIgnoreStart
            if (!headers_sent()) {
                header_remove('location');
                header(sprintf('Content-type: %s; charset=%s', static::CONTENT_TYPE, $this->_charset));
            }
            http_response_code($this->_httpCode);
            // @codeCoverageIgnoreEnd
        }

        $this->invokeCallback();
        $this->outputBuffer = [];
    }

    public function getOption(string $name): mixed {
        $class = get_class($this);
        if (!property_exists($class, "_$name")) {
            trigger_error(sprintf('Undefined option in %s: %s', $class, $name), \E_USER_WARNING);
            return null;
        }

        $name = "_$name";
        return $this->$name;
    }

    public function handle(ThrowableController $controller): array {
        $output = $this->buildOutputArray($controller);

        if ($this->_outputBacktrace) {
            $output['frames'] = $controller->getFrames(argFrameLimit: $this->_backtraceArgFrameLimit);
        }
        if ($this->_outputTime && $this->_timeFormat !== '') {
            $output['time'] = new \DateTimeImmutable();
        }

        $code = 0;
        if ($this->_bubbles) {
            $code = self::BUBBLES;
        }
        if ($this->_forceExit) {
            $code |= self::EXIT;
        }
        if ($this->_logger !== null && (!$this->_silent || ($this->_silent && $this->_logWhenSilent))) {
            $code |= self::LOG;
        }
        if ($this->_forceOutputNow) {
            $code |= self::NOW;
        }
        if (!$this->_silent) {
            $code |= self::OUTPUT;
        }
        $output['code'] = $code;

        $output = $this->handleCallback($output);
        $this->outputBuffer[] = $output;
        return $output;
    }

    /**
     * If an error is triggered while logging or using the var exporter the error
     * would be output by PHP's handler because it occurs within the custom error
     * handler. This is used to attempt as best as possible to have the error be
     * output by Catcher instead.
     *
     * @internal
     */
    public function handleError(int $code, string $message, ?string $file = null, ?int $line = null): bool {
        if ($code && $code & error_reporting()) {
            // PHP's method for getting the current exception handler is stupid,
            // but that's how it is...
            $exceptionHandler = set_exception_handler(null);
            set_exception_handler($exceptionHandler);

            // If the current exception handler happens to not be Catcher use PHP's handler
            // instead.
            if (!is_array($exceptionHandler) || !$exceptionHandler[0] instanceof Catcher) {
                return false;
            }

            // Iterate through the handlers and disable logging to prevent
            // infinite looping of error handlers
            $catcher = $exceptionHandler[0];
            $handlers = $catcher->getHandlers();
            $handlersCount = count($handlers);
            $silentCount = 0;
            foreach ($handlers as $h) {
                $h->setOption('logger', null);

                if ($h->getOption('silent')) {
                    $silentCount++;
                }
            }

            // If all of the handlers are silent then use PHP's handler instead; this is
            // because a valid use for Catcher is to have it be silent but instead have the
            // logger print the errors to stderr/stdout. This should only apply to fatal
            // errors; this shouldn't happen in normal operation but is here just in case
            if (Catcher::isErrorFatal($code) && $silentCount === $handlersCount) {
                return false; //@codeCoverageIgnore
            }

            $catcher->handleError($code, $message, $file, $line);
        }

        return true;
    }

    public function setOption(string $name, mixed $value): void {
        $class = get_class($this);
        if (!property_exists($class, "_$name")) {
            trigger_error(sprintf('Undefined option in %s: %s', $class, $name), \E_USER_WARNING);
            return;
        }

        if (
            $name === 'httpCode' &&
            is_int($value) &&
            $value !== 200 &&
            max(400, min($value, 418)) !== $value &&
            max(421, min($value, 429)) !== $value &&
            $value !== 431 &&
            $value !== 451 &&
            max(500, min($value, 511)) !== $value &&
            // Cloudflare extensions
            max(520, min($value, 527)) !== $value &&
            $value !== 530
        ) {
            throw new RangeException('Option "httpCode" can only be a valid HTTP 200, 4XX, or 5XX code');
        }

        $name = "_$name";
        $this->$name = $value;
    }


    protected function buildOutputArray(ThrowableController $controller): array {
        $throwable = $controller->getThrowable();

        $output = [
            'controller' => $controller,
            'class' => $throwable::class,
            'code' => $throwable->getCode(),
            'file' => $throwable->getFile() ?: '[UNKNOWN]',
            'line' => $throwable->getLine(),
            'message' => $throwable->getMessage()
        ];

        if ($throwable instanceof Error) {
            $output['errorType'] = $controller->getErrorType();
        }

        if ($this->_outputPrevious) {
            $prevController = $controller->getPrevious();
            if ($prevController) {
                $output['previous'] = $this->buildOutputArray($prevController);
            }
        }

        return $output;
    }

    protected function cleanOutputThrowable(array $outputThrowable): array {
        unset($outputThrowable['controller']);
        unset($outputThrowable['code']);

        if (isset($outputThrowable['previous'])) {
            $outputThrowable['previous'] = $this->cleanOutputThrowable($outputThrowable['previous']);
        }
        if (isset($outputThrowable['time'])) {
            $outputThrowable['time'] = $outputThrowable['time']->format($this->_timeFormat);
        }

        return $outputThrowable;
    }


    abstract protected function handleCallback(array $output): array;
    abstract protected function invokeCallback(): void;

    protected function log(\Throwable $throwable, string $message): void {
        if ($this->_logger === null) {
            return;
        }

        $context = [ 'exception' => $throwable ];
        set_error_handler([ $this, 'handleError' ]);
        if ($throwable instanceof \Error) {
            switch ($throwable->getCode()) {
                case \E_NOTICE:
                case \E_USER_NOTICE:
                case \E_STRICT:
                    $this->_logger->notice($message, $context);
                break;
                case \E_WARNING:
                case \E_COMPILE_WARNING:
                case \E_USER_WARNING:
                case \E_DEPRECATED:
                case \E_USER_DEPRECATED:
                    $this->_logger->warning($message, $context);
                break;
                case \E_PARSE:
                case \E_CORE_ERROR:
                case \E_COMPILE_ERROR:
                    $this->_logger->critical($message, $context);
                break;
                default: $this->_logger->error($message, $context);
            }
        } elseif ($throwable instanceof \PharException || $throwable instanceof \RuntimeException) {
            $this->_logger->alert($message, $context);
        } else {
            $this->_logger->critical($message, $context);
        }
        restore_error_handler();
    }

    protected function print(string $string): void {
        if (strtolower(\PHP_SAPI) === 'cli' && $this->_outputToStderr) {
            // Can't test this in code coverage without printing errors to STDERR
            fwrite(\STDERR, $string); // @codeCoverageIgnore
            return; // @codeCoverageIgnore
        }

        echo $string;
    }

    protected function serializeArgs(mixed $value): string {
        $o = '';
        if (count($value) > 0) {
            $o .= '(' . \PHP_EOL;
            $a = '';
            foreach ($value as $v) {
                $aa = null;
                if ($v instanceof \Closure) {
                    $aa = 'Closure';
                } elseif (is_array($v)) {
                    $aa = 'array';
                } elseif (is_object($v)) {
                    $type = gettype($v);
                    $aa = ($type === 'object') ? get_class($v) : $type;
                } else {
                    $aa = var_export($v, true);
                }
                $a .= sprintf('    %s,' . \PHP_EOL, $aa);
            }
            $a = rtrim($a, ',' . \PHP_EOL) . \PHP_EOL;
            $o .= "$a)" . \PHP_EOL;
        }

        return $o;
    }
}