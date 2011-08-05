`Auth` is an abstract base class.

## Synopsis

```php
uses('auth');
```

## Public Static Methods

* `[[Auth::authEngineForIRI]]()`: Create an instance of an authentication system given an IRI.
* `[[Auth::authEngineForToken]]()`
* `[[Auth::authEngineForScheme]]()`

## Public Methods

* `[[Auth::__construct]]()`
* `[[Auth::verifyAuth]]()`
* `[[Auth::verifyToken]]()`
* `[[Auth::callback]]()`
* `[[Auth::refreshUserData]]()`
* `[[Auth::retrieveUserData]]()`

