# Catcher

Catcher is a Throwable catcher and error handling library for PHP.

## Example

```php
$catcher = new Catcher(new PlainTextHandler([
    'outputBacktrace' => true,
    'backtraceArgFrameLimit' => 2
]));
```