[a]: https://php.net/manual/en/book.dom.php
[b]: https://github.com/Seldaek/monolog

# Catcher

Catcher is a Throwable catcher and error handling library for PHP. Error handling is accomplished using a stack-based approach.

Catcher uses classes called _handlers_ to handle throwables sent its way. PHP is currently in a state of flux when it comes to errors. There are traditional PHP errors which are triggered in userland by using `trigger_error()` which can't be caught using `try`/`catch` and are generally a pain to work with. PHP has begun to remedy this problem by introducing the `\Error` class and its various child classes. However, a lot of functions and core aspects of the language itself continue to use legacy errors. This class does away with this pain point in PHP by turning all errors into throwables. Non user-level fatal errors are picked up by Catcher using its shutdown handler. This means that simply by invoking Catcher one may now catch (almost) any error PHP then handles.

## Requirements

* PHP 8.1 or newer with the following _optional_ extensions:
    * [dom][a] extension (for HTMLHandler)

## Installation

```shell
composer require mensbeam/catcher
```

## Usage

For most use cases this library requires no configuration and little effort to integrate into non-complex environments:

```php
use MensBeam\Foundation\Catcher;

$catcher = new Catcher();
```

That's it. It will automatically register Catcher as an exception, error, and shutdown handler and use `PlainTextHandler` as its sole handler. Catcher can be configured to use one or multiple _handlers_. Imagine a situation where it is necessary to both output text for logging and JSON for an API endpoint. This is easily done using Catcher:

```php
use MensBeam\Foundation\Catcher,
    Monolog\Logger;
use MensBeam\Foundation\Catcher\{
    JSONHandler,
    PlainTextHandler
};

$catcher = new Catcher(
    new PlainTextHandler([
        'logger' => new Logger('log'),
        'silent' => true
    ]),
    new JSONHandler()
);
```

The example above uses [Monolog][b] for its logger, but any PSR-3-compatible logger will work. The `PlainTextHandler` is configured to use a logger where then it will send any and all errors to the logger to do with as it pleases. It is also configured to be otherwise silent. `JSONHandler` is then configured using its default configuration. Handlers are placed within a stack and executed in the order by which they are fed to Catcher, so in this case `PlainTextHandler` will go first, logging the error. `JSONHandler` will follow afterwards and print the JSON.

Catcher comes built-in with the following handlers:

* `HTMLHandler` – Outputs errors in a clean HTML document; uses DOM to assemble the document.
* `JSONHandler` – Outputs errors in a JSON format mostly representative of how errors are stored internally by Catcher handlers; it is provided as an example. The decision to make it like this was made because errors often need to be represented according to a particular specification, and we cannot possibly support them all. `JSONHandler`, however, can be easily extended to suit individual project needs.
* `PlainTextHandler` – Outputs errors in plain text and provides for logging

### MensBeam\Foundation\Catcher

```php
namespace MensBeam\Foundation;
use Mensbeam\Foundation\Catcher\Handler;

class Catcher {
    public bool $preventExit = false;
    public bool $throwErrors = true;

    public function __construct(Handler ...$handlers);

    public function getHandlers(): array;
    public function getLastThrowable(): ?\Throwable;
    public function isRegistered(): bool;
    public function popHandler(): Handler;
    public function pushHandler(Handler ...$handlers): void;
    public function register(): bool;
    public function setHandlers(Handler ...$handlers): void;
    public function shiftHandler(): Handler;
    public function unregister(): bool;
    public function unshiftHandler(Handler ...$handlers): void;
}
```

#### Properties

_preventExit_: When set to true Catcher won't exit at all even after fatal errors or exceptions
_throwErrors_: When set to true Catcher will convert errors to throwables

#### MensBeam\Foundation\Catcher::getHandlers

Returns an array of the handlers defined for use in the Catcher instance

#### MensBeam\Foundation\Catcher::getLastThrowable

Returns the last throwable that this instance of Catcher has handled

#### MensBeam\Foundation\Catcher::isRegistered

Returns whether the Catcher still is registered as a error, exception, and shutdown handler

#### MensBeam\Foundation\Catcher::popHandler

Pops the last handler off the stack and returns it

#### MensBeam\Foundation\Catcher::pushHandler

Pushes the specified handler(s) onto the stack

#### MensBeam\Foundation\Catcher::register

Registers the Catcher instance as an error, exception, and shutdown handler. By default the constructor does this automatically, but this method exists in case `unregister` has been called.

#### MensBeam\Foundation\Catcher::setHandlers

Replaces the stack of handlers with those specified as parameters.

#### MensBeam\Foundation\Catcher::shiftHandler

Shifts the first handler off the stack of handlers and returns it.

#### MensBeam\Foundation\Catcher::unregister

Unregisters the Catcher instance as an error, exception and shutdown handler.

#### MensBeam\Foundation\Catcher::unshiftHandler

Unshifts the specified handler(s) onto the beginning of the stack

### MensBeam\Foundation\Handler

All handlers inherit from this abstract class. Since it is an abstract class meant for constructing handlers protected methods and properties will be documented as well.

```php
namespace MensBeam\Foundation\Catcher;

abstract class Handler {
    public const CONTENT_TYPE = null;

    // Control constants
    public const CONTINUE = 1;
    public const BREAK = 2;
    public const EXIT = 4;

    // Output constants
    public const OUTPUT = 8;
    public const SILENT = 16;
    public const NOW = 32;

    protected array $outputBuffer;

    protected int $_backtraceArgFrameLimit;
    protected string $_charset = 'UTF-8';
    protected bool $_forceBreak = false;
    protected bool $_forceExit = false;
    protected bool $_forceOutputNow = false;
    protected int $_httpCode = 500;
    protected bool $_outputBacktrace = false;
    protected bool $_outputPrevious = true;
    protected bool $_outputTime = true;
    protected bool $_outputToStderr = true;
    protected bool $_silent = false;
    protected string $_timeFormat = 'Y-m-d\TH:i:s.vO';

    public function __construct(array $options = []);

    public function dispatch(): void;
    public function getOption(string $name): mixed;
    public function handle(ThrowableController $controller): array;
    public function setOption(string $name, mixed $value): void;

    protected function buildOutputArray(ThrowableController $controller): array;
    protected function cleanOutputThrowable(array $outputThrowable): array;

    abstract protected function dispatchCallback(): void;

    protected function handleCallback(array $output): array;
    protected function print(string $string): void;
}
```

#### Constants

_CONTENT\_TYPE_: The mime type of the content that is output by the handler
_CONTINUE_: The stack loop continues onto the next handler after handling; this is the default behavior
_BREAK_: The stack loop breaks after the handler finishes, causing any further down in the stack to not run
_EXIT_: The stack loop exits after running all handlers
_OUTPUT_: The handler will output
_SILENT_: The handler will be silent
_NOW_: Outputs immediately after handling

#### Properties (Protected)

Properties which begin with an underscore all are options. They can be set either through the constructor or via `setHandler` by name, removing the underscore at the beginning. All handlers inherit these options.

_outputBuffer_: This is where the output arrays representing the handled throwables are stored until they are dispatched.
_\_backtraceArgFrameLimit_: The number of frames by which there can be arguments output with them. Defaults to _5_.
_\_charset_: The character set of the output; only used if headers weren't sent before an error occurred. Defaults to _"UTF\_8"_.
_\_forceBreak_: When set this will force the stack loop to break after the handler has run. Defaults to _false_.
_\_forceExit_: When set this will force an exit after all handlers have been run. Defaults to _false_.
_\_forceOutputNow_: When set this will force output of the handler immediately. Defaults to _false_.
_\_httpCode_: The HTTP code to be sent; possible values are 200, 400-599. Defaults to _500_.
_\_outputBacktrace_: When true will output a stack trace. Defaults to _false_.
_\_outputPrevious_: When true will output previous throwables. Defaults to _true_.
_\_outputTime_: When true will output times to the output. Defaults to _true_.
_\_outputToStderr_: When the SAPI is cli output errors to stderr. Defaults to _true_.
_\_silent_: When true the handler won't output anything. Defaults to _false_.
_\_timeFormat_: The PHP-standard date format which to use for times in output. Defaults to _"Y-m-d\\TH:i:s.vO"_.

#### MensBeam\Foundation\Catcher\Handler::dispatch

Outputs the stored throwable arrays in the output buffer.

#### MensBeam\Foundation\Catcher\Handler::getOption

Returns the value of the provided option name

#### MensBeam\Foundation\Catcher\Handler::handle

Handles the provided `ThrowableController` and stores the output array in the output buffer to be dispatched later

#### MensBeam\Foundation\Catcher\Handler::setOption

Sets the provided option with the provided value

#### MensBeam\Foundation\Catcher\Handler::buildOutputArray (protected)

With a given `ThrowableController` will output an array to be stored in the output buffer

#### MensBeam\Foundation\Catcher\Handler::cleanOutputThrowable (protected)

"Cleans" an output throwable -- an individual item in the output array -- by removing information that's unnecessary in the output; useful for structured data output such as JSON.

#### MensBeam\Foundation\Catcher\Handler::dispatchCallback (protected)

A callback method meant to be extended by inherited classes to control how the class outputs the throwable arrays

#### MensBeam\Foundation\Catcher\Handler::handleCallback (protected)

A callback method meant to be extended by inherited classes where the output array can be manipulated before storing in the output buffer

#### MensBeam\Foundation\Catcher\Handler::print (protected)

Prints the provided string to stderr or stdout depending on how the handler is configured and which SAPI is being used.