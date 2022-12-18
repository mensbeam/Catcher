<?php
/** 
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details 
 */

declare(strict_types=1);
namespace MensBeam\Foundation\Catcher;


class HTMLHandler extends Handler {
    public const CONTENT_TYPE = 'text/html';

    
    /** The DOMDocument errors should be inserted into */
    protected ?\DOMDocument $_document = null;
    /** The XPath path to the element where the errors should be inserted */
    protected string $_errorPath = '/html/body';
    /** If true the handler will output times to the output; defaults to true */
    protected bool $_outputTime = true;
    /** The PHP-standard date format which to use for times printed to output */
    protected string $_timeFormat = 'H:i:s';

    protected \DOMXPath $xpath;
    protected \DOMElement $errorLocation;


    

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

        $this->xpath = new \DOMXPath($this->_document);
        $location = $this->xpath->query($this->_errorPath);
        if (count($location) === 0 || !$location->item(0) instanceof \DOMElement) {
            throw new \InvalidArgumentException('Option "errorPath" must correspond to a location that is an instance of \DOMElement');
        }
        $this->errorLocation = $location->item(0);
    }




    protected function buildThrowable(ThrowableController $controller): \DOMDocumentFragment {
        $throwable = $controller->getThrowable();
        $frag = $this->_document->createDocumentFragment();

        $b = $this->_document->createElement('b');
        $type = $controller->getErrorType();
        $class = $throwable::class;
        $b->appendChild($this->_document->createTextNode($type ?? $class));
        if ($type !== null) {
            $b->firstChild->textContent .= ' ';
            $code = $this->_document->createElement('code');
            $code->appendChild($this->_document->createTextNode("($class)"));
            $b->appendChild($code);
        }
        $frag->appendChild($b);

        $frag->appendChild($this->_document->createTextNode(': '));
        $i = $this->_document->createElement('i');
        $i->appendChild($this->_document->createTextNode($throwable->getMessage()));
        $frag->appendChild($i);
        $frag->appendChild($this->_document->createTextNode(' in file '));
        $code = $this->_document->createElement('code');
        $code->appendChild($this->_document->createTextNode($throwable->getFile()));
        $frag->appendChild($code);
        $frag->appendChild($this->_document->createTextNode(' on line ' . $throwable->getLine()));
        return $frag;
    }

    protected function dispatchCallback(): void {
        $ul = $this->_document->createElement('ul');
        $this->errorLocation->appendChild($ul);

        $allSilent = true;
        foreach ($this->outputBuffer as $o) {
            if ($o->outputCode & self::SILENT) {
                continue;
            }

            $allSilent = false;
            $li = $this->_document->createElement('li');
            $li->appendChild($o->output);
            $ul->appendChild($li);
        }

        if (!$allSilent) {
            $this->print($this->_document->saveHTML());
        }
    }

    protected function handleCallback(ThrowableController $controller): HandlerOutput {
        $frag = $this->_document->createDocumentFragment();

        if ($this->_outputTime && $this->_timeFormat !== '') {
            $p = $this->_document->createElement('p');
            $time = $this->_document->createElement('time');
            $now = new \DateTimeImmutable();
            $tz = $now->getTimezone()->getName();
            if ($tz !== 'UTC' || !in_array($this->_timeFormat, [ 'c', 'Y-m-d\TH:i:sO', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s\Z' ])) {
                $n = ($tz !== 'UTC') ? $now->setTimezone(new \DateTimeZone('UTC')) : $now;
                $time->setAttribute('datetime', $n->format('Y-m-d\TH:i:s\Z'));
            }
            $time->appendChild($this->_document->createTextNode($now->format($this->_timeFormat)));
            $p->appendChild($time);
            $frag->appendChild($p);

            $ip = $this->_document->createElement('div');
            $frag->appendChild($ip);
        } else {
            $ip = $frag;
        }

        $p = $this->_document->createElement('p');
        $p->appendChild($this->buildThrowable($controller));
        $ip->appendChild($p);

        if ($this->_outputPrevious) {
            $prev = $controller->getPrevious();
            if ($prev !== null) {
                $ul = $this->_document->createElement('ul');
                $ip->appendChild($ul);
                $f = null;
                while ($prev) {
                    if ($f !== null) {
                        $p = $this->_document->createElement('p');
                        $p->appendChild($f);
                        $li->appendChild($p);
                        $ul = $this->_document->createElement('ul');
                        $li->appendChild($ul);
                    }

                    $li = $this->_document->createElement('li');
                    $ul->appendChild($li);
                    $f = $this->_document->createDocumentFragment();
                    $span = $this->_document->createElement('span');
                    $span->appendChild($this->_document->createTextNode('Caused by:'));
                    $f->appendChild($span);
                    $f->appendChild($this->_document->createTextNode(' '));
                    $f->appendChild($this->buildThrowable($prev));

                    $prev = $prev->getPrevious();
                }

                $li->appendChild($f);
            }
        }

        if ($this->_outputBacktrace) {
            $frames = $controller->getFrames();
            if (count($frames) > 0) {
                $p = $this->_document->createElement('p');
                $p->appendChild($this->_document->createTextNode('Stack trace:'));
                $ip->appendChild($p);

                $ol = $this->_document->createElement('ol');
                $ip->appendChild($ol);

                $num = 0;
                foreach ($frames as $frame) {
                    $li = $this->_document->createElement('li');
                    $ol->appendChild($li);

                    $args = (isset($frame['args']) && $this->_backtraceArgFrameLimit >= ++$num);
                    if ($args) {
                        $t = $this->_document->createElement('p');
                        $li->appendChild($t);
                    } else {
                        $t = $li;
                    }

                    $b = $this->_document->createElement('b');
                    $code = $this->_document->createElement('code');
                    $b->appendChild($code);
                    $t->appendChild($b);
                    
                    $text = $frame['error'] ?? $frame['class'] ?? '';
                    if (isset($frame['function'])) {
                        $text = ((isset($frame['class'])) ? '::' : '') . "{$frame['function']}()";
                    }
                    $code->appendChild($this->_document->createTextNode($text));

                    $t->appendChild($this->_document->createTextNode("\u{00a0}\u{00a0}"));
                    $code = $this->_document->createElement('code');
                    $code->appendChild($this->_document->createTextNode($frame['file']));
                    $t->appendChild($code);
                    $t->appendChild($this->_document->createTextNode(":{$frame['line']}"));

                    if ($args) {
                        $pre = $this->_document->createElement('pre');
                        $pre->appendChild($this->_document->createTextNode(print_r($frame['args'], true)));
                        $li->appendChild($pre);
                    }
                }
            }
        }

        return new HandlerOutput($this->getControlCode(), $this->getOutputCode(), $frag);
    }
}