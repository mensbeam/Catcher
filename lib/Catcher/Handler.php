<?php
/** 
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details 
 */

declare(strict_types=1);
namespace Mensbeam\Framework\Catcher;


abstract class Handler {
    public const CONTENT_TYPE = null;

    public const CONTINUE = 1;
    public const BREAK = 2;
    public const EXIT = 4;
    public const OUTPUT = 16;
    public const OUTPUT_NOW = 32;
    public const SILENT = 64;


    protected ThrowableController $controller;
    protected array $data;
    /** The handler's result (bitmask) */
    protected int $outputCode;

    /** 
     * If true the handler will break the handler stack and won't continue onto the 
     * next handler regardless 
     */
    protected static bool $_forceBreak = false;
    /** If true the handler will continue onto the next handler regardless */
    protected static bool $_forceContinue = false;
    /** If true the handler will force an exit */
    protected static bool $_forceExit = false;
    /** 
     * If true the handler will output as soon as possible; however, if silent 
     * is true the handler will output nothing 
     */
    protected static bool $_forceOutputNow = false;
    /** If true the handler will be silent and won't output */
    protected static bool $_silent = false;




    protected function __construct(ThrowableController $controller, array $data = []) {
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
    }




    public static function config(array $config = []) {
        foreach ($config as $key => $value) {
            $key = "_$key";
            self::$$key = $value;
        }

        return __CLASS__;
    }

    abstract public static function create(ThrowableController $controller): self;


    public function getOutputCode(): int {
        return $this->outputCode;
    }

    public function getThrowable(): \Throwable {
        return $this->throwable;
    }

    abstract public function output(): void;
}