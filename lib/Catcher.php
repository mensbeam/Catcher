<?php
/**
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details
 */

declare(strict_types=1);
namespace MensBeam;
use MensBeam\Catcher\{
    ArgumentCountError,
    Error,
    Handler,
    PlainTextHandler,
    ThrowableController,
    UnderflowException
};


class Catcher {
    public const THROW_NO_ERRORS = 0;
    public const THROW_FATAL_ERRORS = 1;
    public const THROW_ALL_ERRORS = 2;

    /** When set to true Catcher won't exit when instructed */
    public bool $preventExit = false;
    /** Determines how errors are handled; THROW_* constants exist to control */
    public int $errorHandlingMethod = self::THROW_FATAL_ERRORS;

    /**
     * Stores the error reporting level set by Catcher to compare against when
     * unregistering
     */
    public ?int $errorReporting = null;
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
            throw new UnderflowException('Popping the last handler will cause the Catcher to have zero handlers; there must be at least one');
        }

        return array_pop($this->handlers);
    }

    public function pushHandler(Handler ...$handlers): void {
        if (count($handlers) === 0) {
            throw new ArgumentCountError(__METHOD__ . 'expects at least 1 argument, 0 given');
        }

        $this->handlers = [ ...$this->handlers, ...$handlers ];
    }

    public function register(): bool {
        if ($this->registered) {
            return false;
        }

        // If the current error reporting level has E_ERROR then remove it and store for
        // comparison when unregistering
        $errorReporting = error_reporting();
        if ($errorReporting & \E_ERROR) {
            $this->errorReporting = $errorReporting & ~\E_ERROR;
            error_reporting($this->errorReporting);
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
            throw new UnderflowException('Shifting the last handler will cause the Catcher to have zero handlers; there must be at least one');
        }

        return array_shift($this->handlers);
    }

    public function unregister(): bool {
        if (!$this->registered) {
            return false;
        }

        restore_error_handler();
        restore_exception_handler();

        // If error reporting has been set when registering and the error reporting level
        // is the same as it was when it was set then add E_ERROR back to the error
        $errorReporting = error_reporting();
        if ($this->errorReporting !== null && $this->errorReporting === $errorReporting) {
            error_reporting($errorReporting | \E_ERROR);
        }
        $this->errorReporting = null;

        $this->registered = false;
        return true;
    }

    public function unshiftHandler(Handler ...$handlers): void {
        if (count($handlers) === 0) {
            throw new ArgumentCountError(__METHOD__ . 'expects at least 1 argument, 0 given');
        }

        $this->handlers = [ ...$handlers, ...$this->handlers ];
    }


    /**
     * Converts regular errors into throwable Errors for easier handling; meant to be
     * used with set_error_handler.
     *
     * @internal
     */
    public function handleError(int $code, string $message, ?string $file = null, ?int $line = null): bool {
        if ($code && $code & error_reporting()) {
            $error = new Error($message, $code, $file, $line);
            if ($this->errorHandlingMethod > self::THROW_NO_ERRORS && ($this->errorHandlingMethod === self::THROW_ALL_ERRORS || $this->isErrorFatal($code)) && !$this->isShuttingDown) {
                $this->lastThrowable = $error;
                throw $error;
            } else {
                $this->handleThrowable($error);
                return true;
            }
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
        $exit = false;
        foreach ($this->handlers as $h) {
            $output = $h->handle($controller);

            if (!$this->isShuttingDown && $output['code'] & Handler::NOW) {
                $h();
            }
            if ($output['code'] & Handler::EXIT) {
                $exit = true;
            }
            if (($output['code'] & Handler::BUBBLES) === 0) {
                break;
            }
        }

        if (
            $exit ||
            $this->isShuttingDown ||
            $throwable instanceof \Exception ||
            ($throwable instanceof Error && $this->isErrorFatal($throwable->getCode())) ||
            (!$throwable instanceof Error && $throwable instanceof \Error)
        ) {
            foreach ($this->handlers as $h) {
                if ($this->isShuttingDown) {
                    $h->setOption('outputBacktrace', false);
                }
                $h();
            }

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

        $this->isShuttingDown = true;
        if ($error = $this->getLastError()) {
            if ($this->isErrorFatal($error['type'])) {
                $errorReporting = error_reporting();
                if ($this->errorReporting !== null && $this->errorReporting === $errorReporting && ($this->errorReporting & \E_ERROR) === 0) {
                    error_reporting($errorReporting | \E_ERROR);
                }
                $this->handleError($error['type'], $error['message'], $error['file'], $error['line']);
            }
        } else {
            foreach ($this->handlers as $h) {
                $h();
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