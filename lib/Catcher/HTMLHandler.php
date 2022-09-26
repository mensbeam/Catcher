<?php
/** 
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details 
 */

declare(strict_types=1);
namespace Mensbeam\Framework\Catcher;


class HTMLHandler extends ThrowableHandler {
    public const CONTENT_TYPE = 'text/html';

    /** The number of backtrace frames in which to print arguments; defaults to 5 */
    protected int $_backtraceArgFrameLimit = 5;
    /** If true the handler will output backtraces; defaults to false */
    protected bool $_outputBacktrace = false;
    /** If true the handler will output previous throwables; defaults to true */
    protected bool $_outputPrevious = true;
    /** If true the handler will output times to the output; defaults to true */
    protected bool $_outputTime = true;
    /** The PHP-standard date format which to use for times printed to output */
    protected string $_timeFormat = 'H:i:s';




    public function __construct(array $config = []) {
        parent::__construct($config);
    }




    public function getBacktraceArgFrameLimit(): int {
        return $this->_getBacktraceArgFrameLimit;
    }

    public function getOutputBacktrace(): bool {
        return $this->_outputBacktrace;
    }

    public function getOutputPrevious(): bool {
        return $this->_outputPrevious;
    }

    public function getOutputTime(): bool {
        return $this->_outputTime;
    }

    public function getTimeFormat(): bool {
        return $this->_timeFormat;
    }

    public function handle(\Throwable $throwable, ThrowableController $controller): bool {
        $document = new \DOMDocument();
        $frag = $document->createDocumentFragment();

        if ($this->_outputTime && $this->_timeFormat !== '') {
            $p = $document->createElement('p');
            $time = $document->createElement('time');
            $time->appendChild($document->createTextNode((new \DateTime())->format($this->_timeFormat)));
            $p->appendChild($time);
            $frag->appendChild($p);
        }

        $frag->appendChild($this->buildThrowable($document, $throwable, $controller));
        if ($this->_outputPrevious) {
            $prev = $throwable->getPrevious();
            $prevController = $controller->getPrevious();
            while ($prev) {
                $p = $document->createElement('p');
                $small = $document->createElement('small');
                $small->appendChild($document->createTextNode('Caused by ↴'));
                $p->appendChild($small);
                $frag->appendChild($p);
                $frag->appendChild($this->buildThrowable($document, $prev, $prevController));
                $prev = $prev->getPrevious();
                $prevController = $prevController->getPrevious();
            }
        }

        if ($this->_outputBacktrace) {
            $frames = $controller->getFrames();
            $p = $document->createElement('p');
            $p->appendChild($document->createTextNode('Stack trace:'));
            $frag->appendChild($p);

            if (count($frames) > 0) {
                $ol = $document->createElement('ol');
                $p->appendChild($ol);
                $num = 1;
                foreach ($frames as $frame) {
                    $li = $document->createElement('li');
                    $args = (!empty($frame['args']) && $this->_backtraceArgFrameLimit >= $num);
                    $t = ($args) ? $document->createElement('p') : $li;

                    if (!empty($frame['error'])) {
                        $b = $document->createElement('b');
                        $b->appendChild($document->createTextNode($frame['error']));
                        $t->appendChild($b);
                        $t->appendChild($document->createTextNode(' ('));
                        $code = $document->createElement('code');
                        $code->appendChild($document->createTextNode($frame['class']));
                        $t->appendChild($code);
                        $t->appendChild($document->createTextNode(')'));
                    } elseif (!empty($frame['class'])) {
                        $code = $document->createElement('code');
                        $code->appendChild($document->createTextNode($frame['class']));
                        $t->appendChild($code);
                    }
                    
                    $class = $frame['class'] ?? '';
                    $function = $frame['function'] ?? '';
                    if ($function) {
                        if ($class) {
                            $code->firstChild->textContent .= "::{$function}()";
                        } else {
                            $code = $document->createElement('code');
                            $code->appendChild($document->createTextNode("{$function}()"));
                            $t->appendChild($code);
                        }
                    }

                    $t->appendChild($document->createTextNode(' '));
                    $i = $document->createElement('i');
                    $i->appendChild($document->createTextNode($frame['file']));
                    $t->appendChild($i);
                    $t->appendChild($document->createTextNode(":{$frame['line']}"));
    
                    if ($args) {
                        $li->appendChild($t);
                        $pre = $document->createElement('pre');
                        $pre->appendChild($document->createTextNode(var_export($frame['args'], true)));
                        $li->appendChild($pre);
                    }

                    $ol->appendChild($li);
                }
            }
        }

        $this->_result = $frag;

        if (\PHP_SAPI !== 'cli' && $this->_output) {
            $document->loadHTML(sprintf(
                '<!doctype html><html><head><title>%s%s</title></head><body></body></html>', 
                (isset($_SERVER['protocol'])) ? "{$_SERVER['protocol']} " : '', 
                '500 Internal Server Error'
            ));
            $document->getElementsByTagName('body')[0]->appendChild($document->importNode($frag, true));

            $this->sendContentTypeHeader();
            http_response_code(500);
            echo $document->saveHTML();
            return (!$this->_passthrough);
        }

        return false;
    }

    public function setBacktraceArgFrameLimit(int $value): void {
        $this->_getBacktraceArgFrameLimit = $value;
    }

    public function setOutputBacktrace(bool $value): void {
        $this->_outputBacktrace = $value;
    }

    public function setOutputPrevious(bool $value): void {
        $this->_outputPrevious = $value;
    }

    public function setOutputTime(bool $value): void {
        $this->_outputTime = $value;
    }

    public function setTimeFormat(bool $value): void {
        $this->_timeFormat = $value;
    }

    protected function buildThrowable(\DOMDocument $document, \Throwable $throwable, ThrowableController $controller): \DOMElement {
        $p = $document->createElement('p');

        $class = $throwable::class;
        if ($throwable instanceof \Error) {
            $type = $controller->getErrorType();
            if ($type !== null) {
                $b = $document->createElement('b');
                $b->appendChild($document->createTextNode($type));
                $p->appendChild($b);
                $p->appendChild($document->createTextNode(' ('));
                $code = $document->createElement('code');
                $code->appendChild($document->createTextNode($throwable::class));
                $p->appendChild($code);
                $p->appendChild($document->createTextNode(')'));
            } else {
                $code = $document->createElement('code');
                $code->appendChild($document->createTextNode($throwable::class));
                $p->appendChild($code);
            }
        }

        $p->appendChild($document->createTextNode(': '));
        $i = $document->createElement('i');
        $i->appendChild($document->createTextNode($throwable->getMessage()));
        $p->appendChild($i);
        $p->appendChild($document->createTextNode(' in file '));
        $code = $document->createElement('code');
        $code->appendChild($document->createTextNode($throwable->getFile()));
        $p->appendChild($code);
        $p->appendChild($document->createTextNode(' on line ' . $throwable->getLine()));
        return $p;
    }
}