<?php
/** 
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details 
 */

declare(strict_types=1);
namespace MensBeam\Foundation\Catcher;
use \Psr\Log\LoggerInterface;


class JSONHandler extends Handler {
    public const CONTENT_TYPE = 'application/json';

    /** If true the handler will output times to the output; defaults to true */
    protected bool $_outputTime = true;
    /** The PHP-standard date format which to use for timestamps in output */
    protected string $_timeFormat = 'c';


    protected function dispatchCallback(): void {
        $output = [
            'status' => (string)$this->_httpCode
        ];

        $errors = [];
        foreach ($this->outputBuffer as $o) {
            if ($o->outputCode & self::SILENT) {
                continue;
            }

            $errors[] = $o->output;
        }
        if (count($errors) === 0) {
            return;
        }
        
        $output['errors'] = $errors;
        $this->print(json_encode($output, \JSON_PARTIAL_OUTPUT_ON_ERROR));
    }

    protected function handleCallback(ThrowableController $controller): HandlerOutput {
        $output = $this->buildThrowableArray($controller);
        if ($this->_outputPrevious) {
            $target = $output;
            $prevController = $controller->getPrevious();
            while ($prevController) {
                $prev = $this->buildThrowableArray($prevController);
                $target['previous'] = $prev;
                $target = $prev;
                $prevController = $prevController->getPrevious();
            }
        }

        if ($this->_outputBacktrace) {
            $output['frames'] = $controller->getFrames();
        }

        if ($this->_outputTime && $this->_timeFormat !== '') {
            $output['timestamp'] = (new \DateTime())->format($this->_timeFormat);
        }

        return new HandlerOutput($this->getControlCode(), $this->getOutputCode(), $output);
    }



    protected function log(\Throwable $throwable, string $message): void {
        if ($throwable instanceof \Error) {
            switch ($throwable->getCode()) {
                case \E_NOTICE:
                case \E_USER_NOTICE:
                case \E_STRICT:
                    $this->_logger->notice($message);
                break;
                case \E_WARNING:
                case \E_COMPILE_WARNING:
                case \E_USER_WARNING:
                case \E_DEPRECATED:
                case \E_USER_DEPRECATED:
                    $this->_logger->warning($message);
                break;
                case \E_RECOVERABLE_ERROR:
                    $this->_logger->error($message);
                break;
                case \E_PARSE:
                case \E_CORE_ERROR:
                case \E_COMPILE_ERROR:
                    $this->_logger->alert($message);
                break;
                default: $this->_logger->critical($message);
            }
        } elseif ($throwable instanceof \Exception && ($throwable instanceof \PharException || $throwable instanceof \RuntimeException)) {
            $this->_logger->alert($message);
        } else {
            $this->_logger->critical($message);
        }
    }

    protected function buildThrowableArray(ThrowableController $controller): array {
        $throwable = $controller->getThrowable();
        $type = $throwable::class;
        if ($throwable instanceof Error) {
            $t = $controller->getErrorType();
            $t = ($throwable instanceof Error) ? $controller->getErrorType() : null;
            $type = ($t !== null) ? "$t (" . $type . ")" : $type;
        }

        return [
            'code' => $throwable->getCode(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'message' => $throwable->getMessage(),
            'type' => $type
        ];
    }
}