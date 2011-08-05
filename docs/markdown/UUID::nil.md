Return the null UUID as a string

## Synopsis

<code>uses('uuid');</code>

<code>public static function <b>[[UUID]]::nil</b>()</code>

## Description

`UUID::nil()` returns a string containing the null UUID.
It is the equivalent of calling <code>`[[UUID::generate]]()`(`UUID::NONE`);</code>

## Example

* [examples/uuids.php](http://github.com/nexgenta/eregansu/blob/master/examples/uuids.php)

## Return Value

string The null UUID. i.e., `00000000-0000-0000-0000-000000000000`.

