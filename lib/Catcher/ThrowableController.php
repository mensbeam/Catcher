<?php
/** 
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details 
 */

declare(strict_types=1);
namespace MensBeam\Catcher;


class ThrowableController {
    private string|bool|null $errorType = false;
    private ?array $frames = null;
    private ThrowableController|bool|null $previousThrowableController = false;

    private \Throwable $throwable;




    public function __construct(\Throwable $throwable) {
        $this->throwable = $throwable;
    }



    /** Gets the type name for an Error object */
    public function getErrorType(): ?string {
        if ($this->errorType !== false) {
            return $this->errorType;
        }

        if (!$this->throwable instanceof Error) {
            $this->errorType = null;
            return null;
        }

        switch ($this->throwable->getCode()) {
            case \E_ERROR: 
                $this->errorType = 'PHP Fatal Error';
            break;
            case \E_WARNING:
                $this->errorType = 'PHP Warning';
            break;
            case \E_PARSE:
                $this->errorType = 'PHP Parsing Error';
            break;
            case \E_NOTICE:
                $this->errorType = 'PHP Notice';
            break;
            case \E_CORE_ERROR: 
                $this->errorType = 'PHP Core Error';
            break;
            case \E_CORE_WARNING:
                $this->errorType = 'PHP Core Warning';
            break;
            case \E_COMPILE_ERROR:
                $this->errorType = 'Compile Error';
            break;
            case \E_COMPILE_WARNING:
                $this->errorType = 'Compile Warning';
            break;
            case \E_STRICT:
                $this->errorType = 'Runtime Notice';
            break;
            case \E_RECOVERABLE_ERROR:
                $this->errorType = 'Recoverable Error';
            break;
            case \E_DEPRECATED:
            case \E_USER_DEPRECATED:
                $this->errorType = 'Deprecated';
            break;
            case \E_USER_ERROR:
                $this->errorType = 'Fatal Error';
            break;
            case \E_USER_WARNING:
                $this->errorType = 'Warning';
            break;
            case \E_USER_NOTICE:
                $this->errorType = 'Notice';
            break;
            default:
                $this->errorType = null;
        }

        return $this->errorType;
    }

    /** Gets backtrace frames */
    public function getFrames(int $argFrameLimit = \PHP_INT_MAX): array {
        if ($this->frames !== null) {
            return $this->frames;
        }
        if ($argFrameLimit < 0) {
            throw new \RangeException('Argument argFrameLimit cannot be less than 0');
        }

        if (
            !$this->throwable instanceof \Error ||
            !in_array($this->throwable->getCode(), [ E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING ]) ||
            !extension_loaded('xdebug') || 
            !function_exists('xdebug_info') || 
            sizeof(\xdebug_info('mode')) === 0
        ) {
            $frames = $this->throwable->getTrace();
        } else {
            $frames = array_values(array_diff_key(xdebug_get_function_stack(), debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS)));
        }

        // PHP for some stupid reason thinks it's okay not to provide line numbers and file 
        // names when using call_user_func_array; this fixes that. 
        // (https://bugs.php.net/bug.php?id=44428)
        foreach ($frames as $key => $frame) {
            if (empty($frame['file'])) {
                $file = '[INTERNAL]';
                $line = 0;

                $next = $frames[$key + 1] ?? [];

                if (
                    !empty($next['file']) && 
                    !empty($next['function']) &&
                    !empty($next['line']) && 
                    str_contains($next['function'], 'call_user_func')
                ) {
                    $file = $next['file'];
                    $line = $next['line'];
                }

                $frames[$key]['file'] = $file;
                $frames[$key]['line'] = $line;
            }

            $frames[$key]['line'] = (int)$frames[$key]['line'];
        }

        // Delete everything that has anything to do with userland error handling
        $frameCount = count($frames);
        if ($frameCount > 0) {
            $tFile = $this->throwable->getFile();
            $tLine = $this->throwable->getLine();

            for ($i = $frameCount - 1; $i >= 0; $i--) {
                $frame = $frames[$i];
                if ($tFile === $frame['file'] && $tLine === $frame['line']) {
                    array_splice($frames, 0, $i);
                    break;
                }
            }
        }

        // Add a frame for the throwable to the beginning of the array
        $f = [
            'file' => $this->throwable->getFile() ?: '[UNKNOWN]',
            'line' => (int)$this->throwable->getLine(),
            'class' => $this->throwable::class,
            'args' => [
                $this->throwable->getMessage()
            ]
        ];

        // Add the error code and type if it is an Error.
        if ($this->throwable instanceof \Error) {
            $errorType = $this->getErrorType();
            if ($errorType !== null) {
                $f['code'] = $this->throwable->getCode(); 
                $f['errorType'] = $errorType;
            }
        }

        array_unshift($frames, $f);

        // Go through previous throwables and merge in their frames
        if ($prev = $this->getPrevious()) {
            $frames = [ ...$frames, ...$prev->getFrames() ];

            $temp = [];
            foreach ($frames as $f) {
                if (isset($f['file']) && isset($f['line'])) {
                    foreach ($temp as $t) {
                        if (isset($t['file']) && isset($t['line']) && $f['file'] === $t['file'] && $f['line'] === $t['line']) {
                            continue 2;
                        }
                    }
                }

                $temp[] = $f;
            }
            $frames = $temp;
        }

        // Lastly, remove all args past the specified limit.
        if ($argFrameLimit !== \PHP_INT_MAX) {
            for ($i = $argFrameLimit, $frameCount = count($frames); $i < $frameCount; $i++) {
                unset($frames[$i]['args']);
            }
        }

        $this->frames = $frames;
        return $frames;
    }

    public function getPrevious(): ?ThrowableController {
        if ($this->previousThrowableController !== false) {
            return $this->previousThrowableController;
        }

        if ($prev = $this->throwable->getPrevious()) {
            $prev = new ThrowableController($prev);
        }

        $this->previousThrowableController = $prev;
        return $prev;
    }

    public function getThrowable(): \Throwable {
        return $this->throwable;
    }
}