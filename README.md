[a]: https://php.net/manual/en/book.dom.php
[b]: https://code.mensbeam.com/MensBeam/Logger
[c]: https://github.com/symfony/yaml
[d]: https://www.php.net/manual/en/function.pcntl-fork.php
[e]: https://www.php.net/manual/en/function.print-r.php
[f]: https://github.com/symfony/var-exporter
[g]: https://github.com/php-fig/log

# Catcher #

_Catcher_ is a Throwable catcher and error handling library for PHP. Error handling is accomplished using a stack-based approach.

_Catcher_ uses classes called _handlers_ to handle throwables sent its way. PHP is currently in a state of flux when it comes to errors. There are traditional PHP errors which are triggered in userland by using `trigger_error()` which can't be caught using `try`/`catch` and are generally a pain to work with. PHP has begun to remedy this problem by introducing the `\Error` class and its various child classes. However, a lot of functions and core aspects of the language itself continue to use legacy errors. This class attempts to make that pain point much easier by throwing fatal errors as `\Error`s. It is possible to configure _Catcher_ on the fly to throw non fatal errors, allowing you to easily catch them as you would exceptions.


## Requirements ##

* PHP >= 8.1
* [psr/log][g] ^3.0


## Installation ##

```shell
composer require mensbeam/catcher
```


## Usage ##

For most use cases this library requires no configuration and little effort to integrate into non-complex environments:

```php
use MensBeam\Catcher;

$catcher = new Catcher();
```

That's it. It will automatically register Catcher as an exception, error, and shutdown handler and use `PlainTextHandler` as its sole handler. _Catcher_ is fully configurable and can be configured to use one or multiple _handlers_. At present there is only one handler, the aforementioned `PlainTextHandler`. All handlers may be configured to log errors like so:

```php
use MensBeam\Catcher,
    MensBeam\Logger;

$catcher = new Catcher(
    new PlainTextHandler([
        'logger' => new Logger('log'),
        'silent' => true
    ])
);
```

The example above uses [Logger][b] for its logger, but any PSR-3-compatible logger will work.

### A Note About Notices, Warnings, etc. ###

As described in the summary paragraph at the beginning of this document, Catcher by default converts all fatal errors to `Throwable`s and will leave warnings, notices, etc. alone. However, it can be configured to throw these too.

```php
$catcher = new Catcher();
$catcher->errorHandlingMethod = Catcher::THROW_ALL_ERRORS;

try {
    trigger_error(\E_USER_WARNING, 'Ook!');
} catch (\Throwable $t) {
    echo $t->message();
}
```

Output:
```
Ook!
```

### Error Handling ###

PHP by default won't allow fatal errors to be handled by error handlers. It will instead print the error and exit. However, before code execution halts any shutdown functions are run. Catcher will retrieve the last error and manually process it. This causes multiple instances of the same error to be output. Because of this Catcher alters the error reporting level by always removing `E_ERROR` from it when registering the handlers. `E_ERROR` is bitwise or'd back to the error reporting level when unregistering. If this behavior is undesirable then `E_ERROR` can be manually included back into error reporting at any time after Catcher instantiates. Keep in mind Catcher _will not_ include `E_ERROR` back into the error reporting level bitmask if the error reporting level was modified after Catcher was instantiated or if the error reporting level didn't have it when Catcher registered its handlers.

## Documentation ##

### MensBeam\Catcher ###

This is the main class in the library. Unless you have a need to configure a handler or use multiple handlers there usually isn't a need to interact with the rest of the library at all.

```php
namespace MensBeam;
use MensBeam\Catcher\Handler;

class Catcher {
    public const THROW_NO_ERRORS = 0;
    public const THROW_FATAL_ERRORS = 1;
    public const THROW_ALL_ERRORS = 2;

    public int $errorHandlingMethod = self::THROW_FATAL_ERRORS;
    public bool $preventExit = false;

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

#### Constants ####

_THROW\_NO\_ERRORS_: When used in _errorHandlingMethod_ will cause Catcher to not throw any errors.  
_THROW\_FATAL\_ERRORS_: When used in _errorHandlingMethod_ will cause Catcher throw only fatal errors; this is the default behavior.  
_THROW\_ALL\_ERRORS_: When used in _errorHandlingMethod_ will cause Catcher to throw all errors.  
_NOW_: When returned within the output bitmask, it causes Catcher to have the handler immediately be invoked.  
_OUTPUT_: When returned within the output bitkask, it causes the handler to output the throwable when invoked.

#### Properties ####

_errorHandlingMethod_: Determines how errors are handled; THROW_* constants exist to control  
_preventExit_: When set to true Catcher won't exit at all even after fatal errors or exceptions

#### MensBeam\Catcher::getHandlers ####

Returns an array of the handlers defined for use in the Catcher instance

#### MensBeam\Catcher::getLastThrowable ####

Returns the last throwable that this instance of Catcher has handled

#### MensBeam\Catcher::isRegistered ####

Returns whether the Catcher still is registered as a error, exception, and shutdown handler

#### MensBeam\Catcher::popHandler ####

Pops the last handler off the stack and returns it

#### MensBeam\Catcher::pushHandler ####

Pushes the specified handler(s) onto the stack

#### MensBeam\Catcher::register ####

Registers the Catcher instance as an error, exception, and shutdown handler. By default the constructor does this automatically, but this method exists in case `unregister` has been called.

#### MensBeam\Catcher::setHandlers ####

Replaces the stack of handlers with those specified as parameters.

#### MensBeam\Catcher::shiftHandler ####

Shifts the first handler off the stack of handlers and returns it.

#### MensBeam\Catcher::unregister ####

Unregisters the Catcher instance as an error, exception and shutdown handler.

#### MensBeam\Catcher::unshiftHandler ####

Unshifts the specified handler(s) onto the beginning of the stack


### MensBeam\Catcher\Handler ###

All handlers inherit from this abstract class. Since it is an abstract class meant for constructing handlers protected methods and properties will be documented here as well.

```php
namespace MensBeam\Catcher;

abstract class Handler {
    public const CONTENT_TYPE = null;

    // Control constants
    public const BUBBLES = 1;
    public const EXIT = 2;
    public const LOG = 4;
    public const NOW = 8;
    public const OUTPUT = 16;

    protected array $outputBuffer;

    // Options
    protected int $_backtraceArgFrameLimit = 5;
    protected bool $_bubbles = true;
    protected string $_charset = 'UTF-8';
    protected bool $_forceExit = false;
    protected bool $_forceOutputNow = false;
    protected int $_httpCode = 500;
    protected ?LoggerInterface $_logger = null;
    protected bool $_logWhenSilent = true;
    protected bool $_outputBacktrace = false;
    protected bool $_outputPrevious = true;
    protected bool $_outputTime = true;
    protected bool $_outputToStderr = true;
    protected bool $_silent = false;
    protected string $_timeFormat = 'Y-m-d\TH:i:s.vO';

    public function __construct(array $options = []);

    public function __invoke(): void;
    public function getOption(string $name): mixed;
    public function setOption(string $name, mixed $value): void;

    protected function buildOutputArray(ThrowableController $controller): array;
    protected function cleanOutputThrowable(array $outputThrowable): array;
    abstract protected function handleCallback(array $output): array;
    abstract protected function invokeCallback(): void;
    protected function log(\Throwable $throwable, string $message): void;
    protected function print(string $string): void;
    protected function serializeArgs(mixed $value): string;
}
```

#### Constants ####

_CONTENT\_TYPE_: The mime type of the content that is output by the handler  

_BUBBLES_: When returned within the output bitmask, it causes the stack loop to continue onto the next handler after handling; this is a default behavior.  
_EXIT_: When returned within the output bitmask, it causes Catcher to exit after running all handlers.  
_LOG_: When returned within the output bitmask, it causes Catcher to log the output to a supplied logger.  
_NOW_: When returned within the output bitmask, it causes Catcher to have the handler immediately be invoked.  
_OUTPUT_: When returned within the output bitkask, it causes the handler to output the throwable when invoked.

#### Properties (Protected) ####

_outputBuffer_: This is where the output arrays representing the handled throwables are stored until they are dispatched.

#### Options ####

Properties which begin with an underscore all are options. They can be set either through the constructor or via `setHandler` by name, removing the underscore at the beginning. All handlers inherit these options. Options in inherited classes should also begin with an underscore (_\__). How to extend `Handler` will be explained further down in the document.

_backtraceArgFrameLimit_: The number of frames by which there can be arguments output with them. Defaults to _5_.  
_bubbles_: If true the handler will move onto the next item in the stack of handlers. Defaults to _true_.  
_charset_: The character set of the output; only used if headers weren't sent before an error occurred. No conversion is done. Defaults to _"UTF\_8"_.  
_forceBreak_: When set this will force the stack loop to break after the handler has run. Defaults to _false_.  
_forceExit_: When set this will force an exit after all handlers have been run. Defaults to _false_.  
_forceOutputNow_: When set this will force output of the handler immediately. Defaults to _false_.  
_httpCode_: The HTTP code to be sent; possible values are 200, 400-599. Defaults to _500_.  
_logger_: The PSR-3 compatible logger in which to log to. Defaults to _null_ (no logging).  
_logWhenSilent_: When set to true the handler will still send logs when silent. Defaults to _true_.  
_outputBacktrace_: When true will output a stack trace. Defaults to _false_.  
_outputPrevious_: When true will output previous throwables. Defaults to _true_.  
_outputTime_: When true will output times to the output. Defaults to _true_.  
_outputToStderr_: When the SAPI is cli output errors to stderr. Defaults to _true_.  
_silent_: When true the handler won't output anything. Defaults to _false_.  
_timeFormat_: The PHP-standard date format which to use for times in output. Defaults to _"Y-m-d\\TH:i:s.vO"_.


#### MensBeam\Catcher\Handler::__invoke ####

Outputs the stored throwable arrays in the output buffer.

#### MensBeam\Catcher\Handler::getOption ####

Returns the value of the provided option name

#### MensBeam\Catcher\Handler::handle ####

Handles the provided `ThrowableController` and stores the output array in the output buffer to be dispatched later

#### MensBeam\Catcher\Handler::setOption ####

Sets the provided option with the provided value

#### MensBeam\Catcher\Handler::buildOutputArray (protected) ####

With a given `ThrowableController` will output an array to be stored in the output buffer

#### MensBeam\Catcher\Handler::cleanOutputThrowable (protected) ####

"Cleans" an output throwable -- an individual item in the output array -- by removing information that's unnecessary in the output; useful for structured data output such as JSON.

#### MensBeam\Catcher\Handler::handleCallback (protected) ####

A callback method meant to be extended by inherited classes where the output array can be manipulated before storing in the output buffer

#### MensBeam\Catcher\Handler::invokeCallback (protected) ####

A callback method meant to be extended by inherited classes to control how the class outputs the throwable arrays

#### MensBeam\Catcher\Handler::print (protected) ####

Prints the provided string to stderr or stdout depending on how the handler is configured and which SAPI is being used.

#### MensBeam\Catcher\Handler::serializeArgs (protected) ####

Serializes argument arrays in stack traces; does not recurse, only showing top level arguments.

### MensBeam\Catcher\ThrowableController ####

We cannot require all throwables to be converted to our own classes, so this class exists as a controller to add new features to throwables for use with Catcher.

```php
namespace MensBeam\Catcher;

class ThrowableController {
    public function __construct(\Throwable $throwable);

    public function getErrorType(): ?string;
    public function getFrames(int $argFrameLimit = \PHP_INT_MAX): array;
    public function getPrevious(): ?ThrowableController;
    public function getThrowable(): \Throwable;
}
```

#### MensBeam\Catcher\ThrowableController::getErrorType ####

Returns the error type of a `MensBeam\Catcher\Error`, meaning a human-friendly representation of the error code (eg: _"Fatal Error"_, _"Warning"_, _"Notice"_) or null if the throwable isn't an `Error`.

#### MensBeam\Catcher\ThrowableController::getFrames ####

Returns the frames for the throwable as an array with deduplication and fixes all taken care of

#### MensBeam\Catcher\ThrowableController::getPrevious ####

Returns the previous `ThrowableController` if there is one

#### MensBeam\Catcher\ThrowableController::getThrowable ####

Returns the throwable controlled by this class instance


### MensBeam\Catcher\PlainTextHandler ###

```php
namespace MensBeam\Catcher;

class PlainTextHandler extends Handler {
    public const CONTENT_TYPE = 'text/plain';

    protected string $_timeFormat = '[H:i:s]';
}
```

#### Options ####

_timeFormat_: Same as in `Handler`, but the default changes to _"[H:i:s]"_.