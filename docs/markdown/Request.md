Encapsulation of a request from a client.

`Request` is an abstract base class.

## Synopsis

```php
uses('request');

$request = Request::requestForSAPI();
```

## Description

The Request class and its descendants represents a single request from a user
agent of some kind. The `Request` class itself is abstract: descendants of `Request`
are used to represent the various different kinds of request which can be
represented, depending upon the current SAPI. For example, an HTTP request
from a web browser is represented as an instance of the `[[HTTPRequest]]` class.
Upon initialisation of the platform, a `Request` class instance is instantiated by
calling `[[Request::requestForSAPI]]()` with no arguments, and the resulting instance is stored
in the `$request` global variable.

## Public Static Methods

* `[[Request::requestForSAPI]]()`: Return an instance of a Request class for a specified SAPI.

## Public Methods

* `[[Request::consume]]()`: Consume the first request parameter as the name of a page.
* `[[Request::consumeForApp]]()`: Move the first parameter from the request to the base array and return it.
* `[[Request::consumeObject]]()`
* `[[Request::addCrumb]]()`: Add an element to the breadcrumb array.
* `[[Request::write]]()`
* `[[Request::err]]()`
* `[[Request::flush]]()`
* `[[Request::header]]()`
* `[[Request::setCookie]]()`
* `[[Request::complete]]()`
* `[[Request::abort]]()`
* `[[Request::negotiate]]()`: Attempt to perform content negotiation.

