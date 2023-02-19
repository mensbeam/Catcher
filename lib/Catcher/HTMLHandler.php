<?php
/** 
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details 
 */

declare(strict_types=1);
namespace MensBeam\Catcher;


class HTMLHandler extends Handler {
    public const CONTENT_TYPE = 'text/html';

    
    /** The DOMDocument errors should be inserted into */
    protected ?\DOMDocument $_document = null;
    /** The XPath path to the element where the errors should be inserted */
    protected string $_errorPath = '/html/body';
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
        if (count($location) === 0 || (!$location->item(0) instanceof \DOMElement && !$location->item(0) instanceof \DOMDocumentFragment)) {
            throw new \InvalidArgumentException('Option "errorPath" must correspond to a location that is an instance of \DOMElement or \DOMDocumentFragment');
        }
        $this->errorLocation = $location->item(0);
    }




    protected function buildOutputThrowable(array $outputThrowable, bool $previous = false): \DOMDocumentFragment {
        $frag = $this->_document->createDocumentFragment();
        $tFrag = $this->_document->createDocumentFragment();
        $ip = $frag;
        $hasSiblings = false;

        if ($previous === false) {
            if (isset($outputThrowable['time'])) {
                $p = $this->_document->createElement('p');
                $time = $this->_document->createElement('time');
                $time->setAttribute('datetime', $outputThrowable['time']->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.vO'));
                $time->appendChild($this->_document->createTextNode($outputThrowable['time']->format($this->_timeFormat)));
                $p->appendChild($time);
                $frag->appendChild($p);
                
                $div = $this->_document->createElement('div');
                $frag->appendChild($div);
                $ip = $div;
            }
        } else {
            $span = $this->_document->createElement('span');
            $span->appendChild($this->_document->createTextNode('Caused by:'));
            $tFrag->appendChild($span);
            $tFrag->appendChild($this->_document->createTextNode(' '));
        }

        $b = $this->_document->createElement('b');
        $code = $this->_document->createElement('code');
        $code->appendChild($this->_document->createTextNode($outputThrowable['class']));
        $b->appendChild($code);
        if (isset($outputThrowable['errorType'])) {
            $b->insertBefore($this->_document->createTextNode("{$outputThrowable['errorType']} ("), $code);
            $b->appendChild($this->_document->createTextNode(')'));
        }
        $tFrag->appendChild($b);
        $tFrag->appendChild($this->_document->createTextNode(': '));
        $i = $this->_document->createElement('i');
        $i->appendChild($this->_document->createTextNode($outputThrowable['message']));
        $tFrag->appendChild($i);
        $tFrag->appendChild($this->_document->createTextNode(' in file '));
        $code = $this->_document->createElement('code');
        $code->appendChild($this->_document->createTextNode($outputThrowable['file']));
        $tFrag->appendChild($code);
        $tFrag->appendChild($this->_document->createTextNode(" on line {$outputThrowable['line']}"));

        if (isset($outputThrowable['previous'])) {
            $ul = $this->_document->createElement('ul');
            $li = $this->_document->createElement('li');
            $li->appendChild($this->buildOutputThrowable($outputThrowable['previous'], true));
            $ul->appendChild($li);
            $ip->appendChild($ul);
            $hasSiblings = true;
        }

        if ($previous === false && isset($outputThrowable['frames'])) {
            $p = $this->_document->createElement('p');
            $p->appendChild($this->_document->createTextNode('Stack trace:'));
            $ip->appendChild($p);

            $ol = $this->_document->createElement('ol');
            $ip->appendChild($ol);
            foreach ($outputThrowable['frames'] as $frame) {
                $li = $this->_document->createElement('li');
                $ol->appendChild($li);
                if (isset($frame['args'])) {
                    $t = $this->_document->createElement('p');
                    $li->appendChild($t);
                } else {
                    $t = $li;
                }
                $b = $this->_document->createElement('b');
                $code = $this->_document->createElement('code');
                $b->appendChild($code);
                $t->appendChild($b);

                if (isset($frame['class'])) {
                    $code->appendChild($this->_document->createTextNode($frame['class']));

                    if (isset($frame['errorType'])) {
                        $b->insertBefore($this->_document->createTextNode("{$frame['errorType']} ("), $code);
                        $b->appendChild($this->_document->createTextNode(')'));
                    } elseif (isset($frame['function'])) {
                        $code->firstChild->appendData("::{$frame['function']}");
                    }
                } elseif (!empty($frame['function'])) {
                    $code->appendChild($this->_document->createTextNode($frame['function']));
                }

                $t->appendChild($this->_document->createTextNode("\u{00a0}\u{00a0}"));
                $code = $this->_document->createElement('code');
                $code->appendChild($this->_document->createTextNode($frame['file']));
                $t->appendChild($code);
                $t->appendChild($this->_document->createTextNode(":{$frame['line']}"));

                if (isset($frame['args'])) {
                    $varExporter = $this->_varExporter;
                    $pre = $this->_document->createElement('pre');
                    $pre->appendChild($this->_document->createTextNode(trim($varExporter($frame['args']))));
                    $li->appendChild($pre);
                }
            }

            $hasSiblings = true;
        }

        if ($hasSiblings) {
            $p = $this->_document->createElement('p');
            $p->appendChild($tFrag);
            $ip->insertBefore($p, $ip->firstChild);
        } else {
            $ip->appendChild($tFrag);
        }

        return $frag;
    }

    protected function dispatchCallback(): void {
        $frag = $this->_document->createDocumentFragment();
        $allSilent = true;
        foreach ($this->outputBuffer as $o) {
            if ($o['outputCode'] & self::SILENT) {
                continue;
            }

            $li = $this->_document->createElement('li');
            $li->appendChild($this->buildOutputThrowable($o));
            $frag->appendChild($li);

            $allSilent = false;
        }

        if (!$allSilent) {
            $ul = $this->_document->createElement('ul');
            $ul->appendChild($frag);
            $this->errorLocation->appendChild($ul);
            $this->print($this->serializeDocument());
        }
    }

    protected function serializeDocument() {
        return $this->_document->saveHTML();
    }
}