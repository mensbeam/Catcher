<?php
/** 
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details 
 */

declare(strict_types=1);
namespace MensBeam\Framework\Catcher;


class HTMLHandler extends Handler {
    public const CONTENT_TYPE = 'text/html';

    protected ?\DOMDocument $_document = null;
    protected static array $bullshit = [];
    /** If true the handler will output times to the output; defaults to true */
    protected bool $_outputTime = true;
    /** The PHP-standard date format which to use for times printed to output */
    protected string $_timeFormat = 'H:i:s';




    public function __construct(array $options = []) {
        parent::__construct($options);
        
        if ($this->_document === null) {
            $this->_document = new \DOMDocument();
            $this->_document->loadHTML(<<<HTML
            <!DOCTYPE html>
            <html>
             <head><title>HTTP {$this->_httpCode}</title></head> 
             <body></body>
            </html>
            HTML);
        }
    }




    protected function buildThrowable(ThrowableController $controller): \DOMElement {
        $throwable = $controller->getThrowable();
        $p = $this->_document->createElement('p');

        $class = $throwable::class;
        if ($throwable instanceof \Error) {
            $type = $controller->getErrorType();
            if ($type !== null) {
                $b = $this->_document->createElement('b');
                $b->appendChild($this->_document->createTextNode($type));
                $p->appendChild($b);
                $p->appendChild($this->_document->createTextNode(' ('));
                $code = $this->_document->createElement('code');
                $code->appendChild($this->_document->createTextNode($throwable::class));
                $p->appendChild($code);
                $p->appendChild($this->_document->createTextNode(')'));
            } else {
                $code = $this->_document->createElement('code');
                $code->appendChild($this->_document->createTextNode($throwable::class));
                $p->appendChild($code);
            }
        }

        $p->appendChild($this->_document->createTextNode(': '));
        $i = $this->_document->createElement('i');
        $i->appendChild($this->_document->createTextNode($throwable->getMessage()));
        $p->appendChild($i);
        $p->appendChild($this->_document->createTextNode(' in file '));
        $code = $this->_document->createElement('code');
        $code->appendChild($this->_document->createTextNode($throwable->getFile()));
        $p->appendChild($code);
        $p->appendChild($this->_document->createTextNode(' on line ' . $throwable->getLine()));
        return $p;
    }

    protected function dispatchCallback(): void {
        $body = $this->_document->getElementsByTagName('body')[0];
        foreach ($this->outputBuffer as $o) {
            if ($o->outputCode & self::SILENT) {
                continue;
            }

            $body->appendChild($o->output);
        }

        $output = $this->_document->saveHTML();
        if (\PHP_SAPI === 'CLI') {
            fprintf(\STDERR, "$output\n");
        } else {
            echo $output;
        }
    }

    protected function handleCallback(ThrowableController $controller): HandlerOutput {
        $frag = $this->_document->createDocumentFragment();

        if ($this->_outputTime && $this->_timeFormat !== '') {
            $p = $this->_document->createElement('p');
            $time = $this->_document->createElement('time');
            $time->appendChild($this->_document->createTextNode((new \DateTime())->format($this->_timeFormat)));
            $p->appendChild($time);
            $frag->appendChild($p);
        }

        $frag->appendChild($this->buildThrowable($controller));
        if ($this->_outputPrevious) {
            $prevController = $controller->getPrevious();
            while ($prevController) {
                $p = $this->_document->createElement('p');
                $small = $this->_document->createElement('small');
                $small->appendChild($this->_document->createTextNode('Caused by ↴'));
                $p->appendChild($small);
                $frag->appendChild($p);
                $frag->appendChild($this->buildThrowable($prevController));
                $prevController = $prevController->getPrevious();
            }
        }

        if ($this->_outputBacktrace) {
            $frames = $controller->getFrames();
            $p = $this->_document->createElement('p');
            $p->appendChild($this->_document->createTextNode('Stack trace:'));
            $frag->appendChild($p);

            if (count($frames) > 0) {
                $ol = $this->_document->createElement('ol');
                $p->appendChild($ol);
                $num = 1;
                foreach ($frames as $frame) {
                    $li = $this->_document->createElement('li');
                    $args = (!empty($frame['args']) && $this->_backtraceArgFrameLimit >= $num);
                    $t = ($args) ? $this->_document->createElement('p') : $li;

                    if (!empty($frame['error'])) {
                        $b = $this->_document->createElement('b');
                        $b->appendChild($this->_document->createTextNode($frame['error']));
                        $t->appendChild($b);
                        $t->appendChild($this->_document->createTextNode(' ('));
                        $code = $this->_document->createElement('code');
                        $code->appendChild($this->_document->createTextNode($frame['class']));
                        $t->appendChild($code);
                        $t->appendChild($this->_document->createTextNode(')'));
                    } elseif (!empty($frame['class'])) {
                        $code = $this->_document->createElement('code');
                        $code->appendChild($this->_document->createTextNode($frame['class']));
                        $t->appendChild($code);
                    }
                    
                    $class = $frame['class'] ?? '';
                    $function = $frame['function'] ?? '';
                    if ($function) {
                        if ($class) {
                            $code->firstChild->textContent .= "::{$function}()";
                        } else {
                            $code = $this->_document->createElement('code');
                            $code->appendChild($this->_document->createTextNode("{$function}()"));
                            $t->appendChild($code);
                        }
                    }

                    $t->appendChild($this->_document->createTextNode(' '));
                    $i = $this->_document->createElement('i');
                    $i->appendChild($this->_document->createTextNode($frame['file']));
                    $t->appendChild($i);
                    $t->appendChild($this->_document->createTextNode(":{$frame['line']}"));
    
                    if ($args) {
                        $li->appendChild($t);
                        $pre = $this->_document->createElement('pre');
                        $pre->appendChild($this->_document->createTextNode(var_export($frame['args'], true)));
                        $li->appendChild($pre);
                    }

                    $ol->appendChild($li);
                }
            }
        }

        return new HandlerOutput($this->getControlCode(), $this->getOutputCode(), $frag);
    }
}