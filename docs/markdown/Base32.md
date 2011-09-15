Abstract class implementing base-32 encoding and decoding.

`Base32` is an abstract base class.

## Synopsis

```php
uses('base32');
```

## Note

Instances of the Base32 class are never created; all methods are static.

## Public Static Methods

* `[[Base32::encode]]()`: Encode an integer as base-32
* `[[Base32::decode]]()`: Decode a base-32 string and return the value as an integer

