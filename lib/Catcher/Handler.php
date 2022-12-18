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
    public const EXIT = 4; // What if this were a bitmask option like NOW?
    public const STOP = 8;

    // Output constants
    public const OUTPUT = 16;
    public const SILENT = 32;
    public const NOW = 64;


    protected ThrowableController $controller;

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
    /** When the SAPI is cli output errors to stderr; defaults to true */
    protected bool $_outputToStderr = true;
    /** If true the handler will be silent and won't output */
    protected bool $_silent = false;




    public function __construct(array $options = []) {
        foreach ($options as $key => $value) {
            $key = "_$key";
            if ($key === '_httpCode' && is_int($value) && $value !== 200 && max(400, min($value, 600)) !== $value) {
                throw new \InvalidArgumentException('Option "httpCode" can only be an integer of 200 or 400-599');
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

    public function handle(ThrowableController $controller): HandlerOutput {
        $output = $this->handleCallback($controller);
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


    abstract protected function dispatchCallback(): void;

    protected function getControlCode(): int {
        $code = self::CONTINUE;
        if ($this->_forceBreak) {
            $code = self::BREAK;
        }
        if ($this->_forceExit) {
            $code |= self::EXIT;
        }
        
        return $code;
    }

    protected function getOutputCode(): int {
        $code = self::OUTPUT;
        if ($this->_silent) {
            $code = self::SILENT;
        }
        if ($this->_forceOutputNow) {
            $code |= self::NOW;
        }

        return $code;
    }

    abstract protected function handleCallback(ThrowableController $controller): HandlerOutput;

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