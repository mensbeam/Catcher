<?php
/** 
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details 
 */

declare(strict_types=1);
namespace MensBeam\Framework\Catcher;


abstract class Handler {
    public const CONTENT_TYPE = null;

    // Control constants
    public const CONTINUE = 1;
    public const BREAK = 2;
    public const EXIT = 4;

    // Output constants
    public const OUTPUT = 16;
    public const OUTPUT_NOW = 32;
    public const SILENT = 64;


    protected ThrowableController $controller;

    /** 
     * Array of HandlerOutputs the handler creates
     * 
     * @var HandlerOutput[] 
     */
    protected array $outputBuffer = [];
    /** 
     * Array of option property names; used when overloading
     * 
     * @var string[] 
     */
    protected array $optionNames;

    /** The number of backtrace frames in which to print arguments; defaults to 5 */
    protected int $_backtraceArgFrameLimit = 5;
    /** 
     * The character encoding used for errors; only used if headers weren't sent before 
     * an error occurred 
     */
    protected string $_charset = 'UTF-8';
    /** If true the handler will continue onto the next handler regardless */
    protected bool $_forceContinue = false;
    /** If true the handler will force an exit */
    protected bool $_forceExit = false;
    /** 
     * If true the handler will output as soon as possible; however, if silent 
     * is true the handler will output nothing 
     */
    protected bool $_forceOutputNow = false;
    /** The HTTP code to be sent */
    protected int $_httpCode = 500;
    /** If true the handler will output backtraces; defaults to false */
    protected bool $_outputBacktrace = false;
    /** If true the handler will output previous throwables; defaults to true */
    protected bool $_outputPrevious = true;
    /** If true the handler will be silent and won't output */
    protected bool $_silent = false;




    public function __construct(array $options = []) {
        foreach ($options as $key => $value) {
            $key = "_$key";
            if ($key === '_httpCode' && is_int($value) && ($value < 400 || $value >= 600)) {
                throw new \InvalidArgumentException('Option "httpCode" can only be an integer between 400 and 599');
            }

            $this->$key = $value;
        }

        $properties = (new \ReflectionClass($this))->getProperties(\ReflectionProperty::IS_PROTECTED);
        $this->optionNames = [];
        foreach ($properties as $p) {
            $name = $p->getName();
            if ($name[0] === '_') {
                $this->optionNames[] = $name;
            }
        }
    }

    /*protected function __construct(ThrowableController $controller, array $data = []) {
        $this->controller = $controller;
        $this->data = $data;

        if (!self::$_silent) {
            $this->outputCode = (!self::$_forceOutputNow) ? self::OUTPUT : self::OUTPUT_NOW;
        } else {
            $this->outputCode = self::SILENT;
        }

        if ($forceContinue) {
            $this->outputCode |= self::CONTINUE;
            return;
        } elseif ($forceExit) {
            $this->outputCode |= self::EXIT;
            return;
        }

        if ($this->outputCode !== self::SILENT) {
            $throwable = $controller->getThrowable();
            if ($throwable instanceof \Exception || in_array($throwable->getCode(), [ \E_ERROR, \E_PARSE, \E_CORE_ERROR, \E_COMPILE_ERROR, \E_USER_ERROR ])) {
                $this->outputCode |= self::EXIT;
                return;
            }
        }

        $this->outputCode |= ($this->outputCode === self::SILENT) ? self::CONTINUE : self::BREAK;
        return;
    }*/


    public function dispatch(): void {
        if (count($this->outputBuffer) === 0) {
            return;
        }

        // Send the headers if possible and necessary
        if (isset($_SERVER['REQUEST_URI'])) {
            if (!headers_sent()) {
                header_remove('location');
                header(sprintf('Content-type: %s; charset=%s', static::CONTENT_TYPE, $this->_charset));
            }
            http_response_code($this->_httpCode);
        }

        $this->dispatchCallback();
        $this->outputBuffer = [];
    }

    public function handle(ThrowableController $controller): HandlerOutput {
        $output = $this->handleCallback($controller);
        $this->outputBuffer[] = $output;
        return $output;
    }


    abstract protected function dispatchCallback(): void;


    /*protected function createOutput(mixed $output): HandlerOutput {
        return new HandlerOutput($this->getControlCode(), $this->getOutputCode(), $output);
    }*/

    protected function getControlCode(): int {
        $code = self::BREAK;
        if ($this->_forceExit) {
            $code = self::EXIT;
        } elseif ($this->_forceContinue) {
            $code = self::CONTINUE;
        }
        
        return $code;
    }

    protected function getOutputCode(): int {
        $code = self::OUTPUT;
        if ($this->_silent) {
            $code = self::SILENT;
            if ($this->_forceOutputNow) {
                $code |= self::OUTPUT_NOW;
            }
        } elseif ($this->_forceOutputNow) {
            $code = self::OUTPUT_NOW;
        }

        return $code;
    }

    abstract protected function handleCallback(ThrowableController $controller): HandlerOutput;

    protected function print(string $string): void {
        if (strtolower(\PHP_SAPI) === 'cli') {
            fprintf(\STDERR, "$string\n");
        } else {
            echo $string;
        }
    }

    /*public function __get(string $name): mixed {
        $name = "_$name";
        if (in_array($name, $this->optionNames)) {
            return $this->$name;
        }
    }

    public function __set(string $name, mixed $value): void {
        $name = "_$name";
        if (in_array($name, $this->optionNames)) {
            $this->$name = $value;
        }
    }*/
}