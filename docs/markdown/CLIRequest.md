Implementation of the Request class for command-line (`cli`) requests.

`CLIRequest` is a class derived from [[Request]].

## Synopsis

```php
$req = Request::requestForSAPI('cli');
```

## Description

An instance of `CLIRequest` is returned by `[[Request::requestForSAPI]]()`
if the current (or explicitly specified) SAPI is `cli`.

## Public Methods

* `[[CLIRequest::redirect]]()`: Redirect a request to another location.

