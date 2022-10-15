<?php
/** 
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details 
 */

declare(strict_types=1);
namespace Mensbeam\Framework;
use Mensbeam\Framework\Catcher\{
    ThrowableController,
    Handler
};


class Catcher {
    /** 
     * Array of handlers the exceptions are passed to
     * 
     * @var Handler[] 
     */
    protected array $handlers = [];
    protected array $handlerClasses = [];
    /** Flag set when the shutdown handler is run */
    protected bool $isShuttingDown = false;

    protected Map $results;




    public function __construct(string ...$handlerClasses) {
        $this->handlerClasses = $handlerClasses;
        $this->results = new Map();

        set_error_handler([ $this, 'handleError' ]);
        set_exception_handler([ $this, 'handleThrowable' ]);
        register_shutdown_function([ $this, 'handleShutdown' ]);
    }




    /** 
     * Converts regular errors into throwable Errors for easier handling; meant to be 
     * used with set_error_handler.
     * 
     * @internal
     */
    public function handleError(int $code, string $message, ?string $file = null, ?int $line = null): bool {
        if ($code !== 0 && error_reporting()) {
            $error = new Error($message, $code, $file, $line);
            if ($this->isShuttingDown) {
                throw $error;
            } else {
                $this->handleThrowable($error);
            }

            return true;
        }

        return false;
    }

    /** 
     * Handles both Exceptions and Errors; meant to be used with set_exception_handler.
     * 
     * @internal
     */
    public function handleThrowable(\Throwable $throwable): void {
        $controller = new ThrowableController($throwable);
        foreach ($this->handlerClasses as $h) {
            $handler = $h::create($controller);
            $code = $handler->getOutputCode();

            if ($code & Handler::OUTPUT_NOW) {
                $handler->output();
            } else {
                $this->handlers[] = $handler;
            }

            if ($code & Handler::CONTINUE) {
                break;
            }
        }

        /*if ($this->isShuttingDown || $throwable instanceof \Exception || in_array($throwable->getCode(), [ \E_ERROR, \E_PARSE, \E_CORE_ERROR, \E_COMPILE_ERROR, \E_USER_ERROR ]) {

        }*/

        if (
            $this->isShuttingDown || 
            $throwable instanceof \Exception || 
            in_array($throwable->getCode(), [ \E_ERROR, \E_PARSE, \E_CORE_ERROR, \E_COMPILE_ERROR, \E_USER_ERROR ])
        ) {
            exit($throwable->getCode());
        }
    }

    /** 
     * Handles shutdowns, passes all possible built-in error codes to the error handler.
     * 
     * @internal
     */
    public function handleShutdown() {
        $this->isShuttingDown = true;
        if (error_get_last() && in_array($error['type'], [ \E_ERROR, \E_PARSE, \E_CORE_ERROR, \E_CORE_WARNING, \E_COMPILE_ERROR, \E_COMPILE_WARNING ])) {
            $this->handleError($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }

    public static function isHTTPRequest() {
        return (\PHP_SAPI !== 'cli' && isset($_SERVER['REQUEST_URI']) && isset($_SERVER['REQUEST_METHOD']));
    }
}