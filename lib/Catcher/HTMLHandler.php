<?php
/** 
 * @license MIT
 * Copyright 2022 Dustin Wilson, et al.
 * See LICENSE and AUTHORS files for details 
 */

declare(strict_types=1);
namespace Mensbeam\Framework\Catcher;


class HTMLHandler extends Handler {
    public const CONTENT_TYPE = 'text/html';

    /** The number of backtrace frames in which to print arguments; defaults to 5 */
    protected static int $_backtraceArgFrameLimit = 5;
    /** If true the handler will output backtraces; defaults to false */
    protected static bool $_outputBacktrace = false;
    /** If true the handler will output previous throwables; defaults to true */
    protected static bool $_outputPrevious = true;
    /** If true the handler will output times to the output; defaults to true */
    protected static bool $_outputTime = true;
    /** The PHP-standard date format which to use for times printed to output */
    protected static string $_timeFormat = 'H:i:s';




    public static function handle(\Throwable $throwable, ThrowableController $controller): bool {
        $document = new \DOMDocument();
        $frag = $document->createDocumentFragment();

        if (self::$_outputTime && self::$_timeFormat !== '') {
            $p = $document->createElement('p');
            $time = $document->createElement('time');
            $time->appendChild($document->createTextNode((new \DateTime())->format(self::$_timeFormat)));
            $p->appendChild($time);
            $frag->appendChild($p);
        }

        $frag->appendChild(self::$buildThrowable($document, $throwable, $controller));
        if (self::$_outputPrevious) {
            $prev = $throwable->getPrevious();
            $prevController = $controller->getPrevious();
            while ($prev) {
                $p = $document->createElement('p');
                $small = $document->createElement('small');
                $small->appendChild($document->createTextNode('Caused by ↴'));
                $p->appendChild($small);
                $frag->appendChild($p);
                $frag->appendChild(self::$buildThrowable($document, $prev, $prevController));
                $prev = $prev->getPrevious();
                $prevController = $prevController->getPrevious();
            }
        }

        if (self::$_outputBacktrace) {
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
                    $args = (!empty($frame['args']) && self::$_backtraceArgFrameLimit >= $num);
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

        $this->result = $frag;

        if (\PHP_SAPI !== 'cli' && self::$_output) {
            $document->loadHTML(sprintf(
                '<!doctype html><html><head><title>%s%s</title></head><body></body></html>', 
                (isset($_SERVER['protocol'])) ? "{$_SERVER['protocol']} " : '', 
                '500 Internal Server Error'
            ));
            $document->getElementsByTagName('body')[0]->appendChild($document->importNode($frag, true));

            self::$sendContentTypeHeader();
            http_response_code(500);
            echo $document->saveHTML();
            return (!self::$_passthrough);
        }

        return false;
    }


    protected static function buildThrowable(\DOMDocument $document, \Throwable $throwable, ThrowableController $controller): \DOMElement {
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