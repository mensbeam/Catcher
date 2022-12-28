<?php
/** 
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details 
 */

declare(strict_types=1);
namespace MensBeam\Foundation\Catcher;


abstract class Handler {
    public const CONTENT_TYPE = null;

    // Control constants
    public const CONTINUE = 1;
    public const BREAK = 2;
    public const EXIT = 4;

    // Output constants
    public const OUTPUT = 8;
    public const SILENT = 16;
    public const NOW = 32;


    /** 
     * Array of HandlerOutputs the handler creates
     * 
     * @var HandlerOutput[] 
     */
    protected array $outputBuffer = [];

    /** The number of backtrace frames in which to print arguments; defaults to 5 */
    protected int $_backtraceArgFrameLimit = 5;
    /** 
     * The character encoding used for errors; only used if headers weren't sent before 
     * an error occurred 
     */
    protected string $_charset = 'UTF-8';
    /** If true the handler will force break the loop through the stack of handlers */
    protected bool $_forceBreak = false;
    /** If true the handler will force an exit */
    protected bool $_forceExit = false;
    /** 
     * If true the handler will output as soon as possible; however, if silent 
     * is true the handler will output nothing 
     */
    protected bool $_forceOutputNow = false;
    /** The HTTP code to be sent; possible values: 200, 400-599 */
    protected int $_httpCode = 500;
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
            $key = "_$key";
            if ($key === '_httpCode' && is_int($value) && $value !== 200 && max(400, min($value, 600)) !== $value) {
                throw new \RangeException('Option "httpCode" can only be an integer of 200 or 400-599');
            }

            $this->$key = $value;
        }
    }




    public function dispatch(): void {
        if (count($this->outputBuffer) === 0) {
            return;
        }

        // Send the headers if possible and necessary
        if (isset($_SERVER['REQUEST_URI'])) {
            // Can't figure out a way to test coverage here, but the logic is tested thoroughly 
            // when running tests in HTTP
            // @codeCoverageIgnoreStart
            if (!headers_sent()) {
                header_remove('location');
                header(sprintf('Content-type: %s; charset=%s', static::CONTENT_TYPE, $this->_charset));
            }
            http_response_code($this->_httpCode);
            // @codeCoverageIgnoreEnd
        }

        $this->dispatchCallback();
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

        $code = self::CONTINUE;
        if ($this->_forceBreak) {
            $code = self::BREAK;
        }
        if ($this->_forceExit) {
            $code |= self::EXIT;
        }
        $output['controlCode'] = $code;

        $code = self::OUTPUT;
        if ($this->_silent) {
            $code = self::SILENT;
        }
        if ($this->_forceOutputNow) {
            $code |= self::NOW;
        }
        $output['outputCode'] = $code;

        $output = $this->handleCallback($output);
        $this->outputBuffer[] = $output;
        return $output;
    }

    public function setOption(string $name, mixed $value): void {
        $class = get_class($this);
        if (!property_exists($class, "_$name")) {
            trigger_error(sprintf('Undefined option in %s: %s', $class, $name), \E_USER_WARNING);
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
        unset($outputThrowable['controlCode']);
        unset($outputThrowable['outputCode']);

        if (isset($outputThrowable['previous'])) {
            $outputThrowable['previous'] = $this->cleanOutputThrowable($outputThrowable['previous']);
        }
        if (isset($outputThrowable['time'])) {
            $outputThrowable['time'] = $outputThrowable['time']->format($this->_timeFormat);
        }

        return $outputThrowable;
    }

    abstract protected function dispatchCallback(): void;

    protected function handleCallback(array $output): array {
        return $output;
    }

    protected function print(string $string): void {
        $string = "$string\n";
        if (strtolower(\PHP_SAPI) === 'cli' && $this->_outputToStderr) {
            // Can't test this in code coverage without printing errors to STDERR
            fwrite(\STDERR, $string); // @codeCoverageIgnore
        } else {
            echo $string;
        }
    }
}