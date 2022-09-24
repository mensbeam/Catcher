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
    ThrowableHandler
};


class Catcher {
    /** 
     * Array of handlers the exceptions are passed to
     * 
     * @var ThrowableHandler[] 
     */
    protected array $handlers = [];
    /** Flag set when the shutdown handler is run */
    protected bool $isShuttingDown = false;




    public function __construct(ThrowableHandler ...$handlers) {
        $this->handlers = $handlers;
        $prev = set_error_handler([ $this, 'handleError' ]);
        $prev = set_exception_handler([ $this, 'handleThrowable' ]);
        register_shutdown_function([ $this, 'handleShutdown' ]);
    }



    /** 
     * Gets the list of handlers
     * 
     * @return ThrowableHandler[] 
     */
    public function getHandlers(): array {
        return $this->handlers;
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
        foreach ($this->handlers as $h) {
            // If the handler returns true it means the handler handled the exception properly, 
            // and there's no reason to pass it off to another.
            if ($h->handle($throwable, $controller)) {
                break;
            }
        }

        if ($this->isShuttingDown || $throwable instanceof \Exception || in_array($throwable->getCode(), [ \E_ERROR, \E_PARSE, \E_CORE_ERROR, \E_COMPILE_ERROR, \E_USER_ERROR ])) {
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
        
        $error = error_get_last();
        if ($error && in_array($error['type'], [ \E_ERROR, \E_PARSE, \E_CORE_ERROR, \E_CORE_WARNING, \E_COMPILE_ERROR, \E_COMPILE_WARNING ])) {
            $this->handleError($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }
}