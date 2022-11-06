<?php
/** 
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details 
 */

declare(strict_types=1);
namespace MensBeam\Foundation;
use MensBeam\Foundation\Catcher\{
    Handler,
    PlainTextHandler,
    ThrowableController,
};


class Catcher {
    /** When set to true Catcher won't exit when instructed */
    public static $preventExit = false;

    /** 
     * Array of handlers the exceptions are passed to
     * 
     * @var Handler[] 
     */
    protected array $handlers = [];
    /** Flag set when the shutdown handler is run */
    protected bool $isShuttingDown = false;
    /** Flag set when the class has registered error, exception, and shutdown handlers */
    protected bool $registered = false;
    /** The last throwable handled by Catcher */
    protected ?\Throwable $lastThrowable = null;




    public function __construct(Handler ...$handlers) {
        if (count($handlers) === 0) {
            $handlers = [ new PlainTextHandler() ];
        }

        $this->pushHandler(...$handlers);
        $this->register();
    }



    public function getHandlers(): array {
        return $this->handlers;
    }

    public function getLastThrowable(): ?\Throwable {
        return $this->lastThrowable;
    }

    public function isRegistered(): bool {
        return $this->registered;
    }

    public function popHandler(): Handler {
        if (count($this->handlers) === 1) {
            throw new \Exception("Popping the last handler will cause the Catcher to have zero handlers; there must be at least one\n");
        }
        
        return array_pop($this->handlers);
    }

    public function pushHandler(Handler ...$handlers): void {
        if (count($handlers) === 0) {
            throw new \ArgumentCountError(__METHOD__ . "expects at least 1 argument, 0 given\n");
        }

        $prev = [];
        foreach ($handlers as $h) {
            if (in_array($h, $this->handlers, true) || in_array($h, $prev, true)) {
                trigger_error("Handlers must be unique; skipping\n", \E_USER_WARNING);
                continue;
            }

            $prev[] = $h;
            $this->handlers[] = $h;
        }
    }

    public function register(): bool {
        if ($this->registered) {
            return false;
        }

        set_error_handler([ $this, 'handleError' ]);
        set_exception_handler([ $this, 'handleThrowable' ]);
        register_shutdown_function([ $this, 'handleShutdown' ]);
        $this->registered = true;
        return true;
    }

    public function setHandlers(Handler ...$handlers): void {
        $this->handlers = [];
        $this->pushHandler(...$handlers);
    }

    public function shiftHandler(): Handler {
        if (count($this->handlers) === 1) {
            throw new \Exception("Shifting the last handler will cause the Catcher to have zero handlers; there must be at least one\n");
        }
        
        return array_shift($this->handlers);
    }

    public function unregister(): bool {
        if (!$this->registered) {
            return false;
        }

        restore_error_handler();
        restore_exception_handler();
        $this->registered = false;
        return true;
    }

    public function unshiftHandler(Handler ...$handlers): void {
        if (count($handlers) === 0) {
            throw new \ArgumentCountError(__METHOD__ . "expects at least 1 argument, 0 given\n");
        }

        $modified = false;
        $prev = [];
        foreach ($handlers as $v => $h) {
            if (in_array($h, $this->handlers, true) || in_array($h, $prev, true)) {
                trigger_error("Handlers must be unique; skipping\n", \E_USER_WARNING);
                unset($handlers[$v]);
                $modified = true;
                continue;
            }

            $prev[] = $h;
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
            $this->handleThrowable(new Error($message, $code, $file, $line));
            return true;
        }
        
        // If preventing exit we don't want a false here to halt processing
        return (self::$preventExit);
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
            if ($output->outputCode & Handler::NOW) {
                $h->dispatch();
            }

            $controlCode = $output->controlCode;
            if ($controlCode & Handler::BREAK) {
                break;
            }
        }

        if (
            $this->isShuttingDown ||
            $controlCode & Handler::EXIT ||
            $throwable instanceof \Exception || 
            ($throwable instanceof Error && in_array($throwable->getCode(), [ \E_ERROR, \E_PARSE, \E_CORE_ERROR, \E_COMPILE_ERROR, \E_USER_ERROR ])) ||
            (!$throwable instanceof Error && $throwable instanceof \Error)
        ) {
            foreach ($this->handlers as $h) {
                $h->dispatch();
            }

            $this->lastThrowable = $throwable;

            // Don't want to exit here when shutting down so any shutdown functions further 
            // down the stack still run.
            if (!self::$preventExit && !$this->isShuttingDown) {
                $this->exit($throwable->getCode());
            }
        }

        $this->lastThrowable = $throwable;
    }

    /** 
     * Handles shutdowns, passes all possible built-in error codes to the error handler.
     * 
     * @internal
     */
    public function handleShutdown(): void {
        if (!$this->registered) {
            return;
        }

        $this->isShuttingDown = true;
        if ($error = $this->getLastError()) {
            if (in_array($error['type'], [ \E_ERROR, \E_PARSE, \E_CORE_ERROR, \E_CORE_WARNING, \E_COMPILE_ERROR, \E_COMPILE_WARNING ])) {
                $this->handleError($error['type'], $error['message'], $error['file'], $error['line']);
            }
        }
    }


    /** Exists so the method may be replaced when mocking in tests */
    protected function exit(int $status): void {
        // This won't be shown as executed in code coverage
        exit($status); //@codeCoverageIgnore
    }

    /** Exists so the method may be replaced when mocking in tests */
    protected function getLastError(): ?array {
        return error_get_last();
    }
}