Consume the first request parameter as the name of a page.

## Synopsis

<code>uses('request');</code>

<code>public function <i>string</i> <b>[[Request]]::consume</b>()</code>

## Description

Moves the first parameter from `[[Request::$params]]` to the `[[Request::$page]]` array and
returns it.
This has the effect of indicating that the first element of `[[Request::$params]]` is the
name of a page or matches some other kind of defined route.
For example, the `[[Router]]` class will call `Request::consume()` when the first element of
`[[Request::$params]]` matches one of its routes and the adjustBase property of the
route is unset or `false`.
As a result of calling `Request::consume()`, `[[Request::$pageUri]]` will be updated
accordingly.

## Return Value

The first request parameter, or `null` if `[[Request::$params]]` is empty.

