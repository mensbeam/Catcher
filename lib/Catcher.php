<?php
/** 
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details 
 */

declare(strict_types=1);
namespace MensBeam\Foundation;
use MensBeam\Foundation\Catcher\{
    Error,
    Handler,
    PlainTextHandler,
    ThrowableController,
};


class Catcher {
    /** Fork when throwing non-exiting errors, if available */
    public bool $forking = true;
    /** When set to true Catcher won't exit when instructed */
    public bool $preventExit = false;
    /** When set to true Catcher will throw errors as throwables */
    public bool $throwErrors = true;

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

        error_reporting(error_reporting() & ~\E_ERROR);
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
        error_reporting(error_reporting() | \E_ERROR);
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
            $error = new Error($message, $code, $file, $line);
            if ($this->throwErrors) {
                // The point of this library is to allow treating of errors as if they were 
                // exceptions but instead have things like warnings, notices, etc. not stop 
                // execution. You normally can't have it both ways. So, what's going on here is 
                // that if the error wouldn't normally stop execution the newly-created Error 
                // throwable is thrown in a fork instead, allowing execution to resume in the 
                // parent process.
                if ($this->isErrorFatal($code)) {
                    throw $error;
                } elseif ($this->forking && \PHP_SAPI === 'cli' && function_exists('pcntl_fork')) {
                    $pid = pcntl_fork();
                    if ($pid === -1) {
                        // This can't be covered unless it is possible to fake a misconfigured system
                        throw new \Exception(message: 'Could not create fork to throw Error', previous: $error); // @codeCoverageIgnore
                    } elseif (!$pid) {
                        // This can't be covered because it happens in the fork
                        throw $error; // @codeCoverageIgnore
                    }
                    
                    pcntl_wait($status);
                    return true;
                }
            }

            $this->handleThrowable($error);
            return true;
        }
        
        // If preventing exit we don't want a false here to halt processing
        return ($this->preventExit);
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
            if ($output['outputCode'] & Handler::NOW) {
                $h->dispatch();
            }

            $controlCode = $output['controlCode'];
            if ($controlCode & Handler::BREAK) {
                break;
            }
        }

        if (
            $this->isShuttingDown ||
            $controlCode & Handler::EXIT ||
            $throwable instanceof \Exception || 
            ($throwable instanceof Error && $this->isErrorFatal($throwable->getCode())) ||
            (!$throwable instanceof Error && $throwable instanceof \Error)
        ) {
            foreach ($this->handlers as $h) {
                if ($this->isShuttingDown) {
                    $h->setOption('outputBacktrace', false);
                }
                $h->dispatch();
            }

            $this->lastThrowable = $throwable;

            // Don't want to exit here when shutting down so any shutdown functions further 
            // down the stack still run.
            if (!$this->preventExit && !$this->isShuttingDown) {
                $this->exit(max($throwable->getCode(), 1));
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

        $this->throwErrors = false;
        $this->isShuttingDown = true;
        if ($error = $this->getLastError()) {
            if ($this->isErrorFatal($error['type'])) {
                $this->handleError($error['type'], $error['message'], $error['file'], $error['line']);
            }
        } else {
            foreach ($this->handlers as $h) {
                $h->dispatch();
            }
        }
    }


    /** Exists so exits can be tracked in mocks in testing */
    protected function exit(int $status): void {
        // This won't be shown as executed in code coverage
        exit($status); // @codeCoverageIgnore
    }

    /** Checks if the error code is fatal */
    protected function isErrorFatal(int $code): bool {
        return in_array($code, [ \E_ERROR, \E_PARSE, \E_CORE_ERROR, \E_COMPILE_ERROR, \E_USER_ERROR, \E_RECOVERABLE_ERROR ]);
    }

    /** Exists so the method may be replaced when mocking in tests */
    protected function getLastError(): ?array {
        return error_get_last();
    }
}