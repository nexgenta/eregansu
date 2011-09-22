Abstract class containing UUID manipulation facilities.

`UUID` is an abstract base class.

## Synopsis

```php
uses('uuid');
```

## Description

The `UUID` class contains facilities for generating and manipulating
Universally Unique Identifiers (UUIDs), according to
[RFC 4122](http://www.ietf.org/rfc/rfc4122.txt) (equivalent to
ITU-T Rec. X.667, ISO/IEC 9834-8:2005).

## Note

Instances of the UUID class are never created; all methods are static.

## Example

* [examples/uuids.php](http://github.com/nexgenta/eregansu/blob/master/examples/uuids.php)

## Public Static Methods

* `[[UUID::generate]]()`: Generate a new UUID
* `[[UUID::nil]]()`: Return the null UUID as a string
* `[[UUID::isUUID]]()`: Determine whether a string is a valid UUID or not
* `[[UUID::canonical]]()`: Return the canonical form of a UUID string (i.e., no braces, no dashes, all lower-case)
* `[[UUID::iri]]()`: Formats a UUID as an IRI
* `[[UUID::formatted]]()`: Format a UUID in the traditional aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee form
* `[[UUID::parse]]()`: Parse a string containing a UUID and return an array representing its value.
* `[[UUID::unparse]]()`: Constructs a UUID string given an array as returned by UUID::parse()

