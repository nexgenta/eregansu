Exception class whose instances are thrown when an authentication exception
occurs.

`AuthError` is a class derived from [[Exception]].

## Synopsis

```php
uses('auth');

throw new AuthError($engine, $message, $reason);
```

## Public Methods

* `[[AuthError::__construct]]()`

