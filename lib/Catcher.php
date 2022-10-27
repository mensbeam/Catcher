<?php
/** 
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details 
 */

declare(strict_types=1);
namespace MensBeam\Framework;
use MensBeam\Framework\Catcher\{
    Handler,
    PlainTextHandler,
    ThrowableController,
};


class Catcher {
    /** 
     * Array of handlers the exceptions are passed to
     * 
     * @var Handler[] 
     */
    protected array $handlers = [];
    /** Flag set when the shutdown handler is run */
    protected bool $isShuttingDown = false;




    public function __construct(Handler ...$handlers) {
        if (count($handlers) === 0) {
            $handlers = [ new PlainTextHandler() ];
        }

        $this->pushHandler(...$handlers);

        set_error_handler([ $this, 'handleError' ]);
        set_exception_handler([ $this, 'handleThrowable' ]);
        register_shutdown_function([ $this, 'handleShutdown' ]);
    }



    public function getHandlers(): array {
        return $this->handlers;
    }

    public function pushHandler(Handler ...$handlers): void {
        foreach ($handlers as $h) {
            if (in_array($h, $this->handlers, true)) {
                trigger_error("Handlers must be unique; skipping\n", \E_USER_WARNING);
                continue;
            }

            $this->handlers[] = $h;
        }
    }

    public function removeHandler(Handler ...$handlers): void {
        foreach ($handlers as $h) {
            foreach ($this->handlers as $k => $hh) {
                if ($h === $hh) {
                    if (count($this->handlers) === 1) {
                        throw new \Exception("Removing handler will cause the Catcher to have zero handlers; there must be at least one\n");
                    }

                    unset($this->handlers[$k]);
                    $this->handlers = array_values($this->handlers);
                    continue 2;
                }
            }
        }
    }

    public function setHandlers(Handler ...$handlers): void {
        $this->handlers = [];
        $this->pushHandler(...$handlers);
    }

    public function unshiftHandler(Handler ...$handlers): void {
        $modified = false;
        foreach ($handlers as $v => $h) {
            if (in_array($h, $this->handlers, true)) {
                trigger_error("Handlers must be unique; skipping\n", \E_USER_WARNING);
                unset($handlers[$v]);
                $modified = true;
                continue;
            }
        }
        if ($modified) {
            $handlers = array_values($handlers);
        }

        if (count($handlers) > 0) {
            $this->handlers = [ ...$handlers, ...$this->handlers ];
        }
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
            $output = $h->handle($controller);
            if ($output->outputCode & Handler::OUTPUT_NOW) {
                $h->dispatch();
            }

            $controlCode = $output->controlCode;
            if ($controlCode !== Handler::CONTINUE) {
                break;
            }
        }

        if (
            $throwable instanceof \Exception || 
            ($throwable instanceof Error && in_array($throwable->getCode(), [ \E_ERROR, \E_PARSE, \E_CORE_ERROR, \E_COMPILE_ERROR, \E_USER_ERROR ])) ||
            $throwable instanceof \Error
        ) {
            foreach ($this->handlers as $h) {
                $h->dispatch();
            }

            exit($throwable->getCode());
        } elseif ($controlCode === Handler::EXIT) {
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
        if ($error = error_get_last()) {
            if (in_array($error['type'], [ \E_ERROR, \E_PARSE, \E_CORE_ERROR, \E_CORE_WARNING, \E_COMPILE_ERROR, \E_COMPILE_WARNING ])) {
                $this->handleError($error['type'], $error['message'], $error['file'], $error['line']);
            }
        }
    }


    public function __destruct() {
        restore_error_handler();
        restore_exception_handler();
        register_shutdown_function(fn() => false);
    }
}