<?php
/** 
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details 
 */

declare(strict_types=1);
namespace Mensbeam\Framework\Catcher;


abstract class ThrowableHandler {
    public const CONTENT_TYPE = null;

    /** If true the handler will output data; if false it will be silent */
    protected bool $_output = true;
    /** 
     * If true the handler will pass on through to the next handler even if it 
     * successfully handles the throwable; if false it will prevent execution of the 
     * next handler if it successfully handles the throwable 
     */
    protected bool $_passthrough = false;
    /** 
     * The result of the handler's processing of the throwable; most of the time this 
     * will be a string, but in some cases like the HTMLHandler it's a 
     * DOMDocumentFragment so it may be further manipulated 
     */
    protected mixed $_result = null;




    public function __construct(array $config = []) {
        foreach ($config as $key => $value) {
            $key = "_$key";
            $this->$key = $value;
        }
    }


    

    public function getOutput(): bool {
        return $this->_output;
    }

    public function getPassthrough(): bool {
        return $this->_passthrough;
    }

    public function getResult(): mixed {
        return $this->_result;
    }

    abstract public function handle(\Throwable $throwable, ThrowableController $controller): bool;

    public function setOutput(bool $value): void {
        $this->_output = $value;
    }

    public function setPassthrough(bool $value): void {
        $this->_passthrough = $value;
    }

    protected function sendContentTypeHeader(): void {
        if (!isset($_SERVER['REQUEST_URI']) || headers_sent()) {
            return;
        }

        header('Content-Type: ' . static::CONTENT_TYPE);
    }
}