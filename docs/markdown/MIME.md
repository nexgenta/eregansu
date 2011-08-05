The `MIME` class provides facilities for mapping file extensions to
MIME types and vice versa and obtaining human-readable descriptions
from MIME types.

`MIME` is an abstract base class.

## Synopsis

```php
uses('mime');
```

## Description

<note>Instances of the `MIME` class are never created; all methods are static.</note>

## Example

* [mimetest.php](http://github.com/nexgenta/eregansu/blob/master/mimetest.php)

## Public Static Methods

* `[[MIME::extForType]]()`: Return the preferred file extension for a specified MIME type
* `[[MIME::typeForExt]]()`: Return the MIME type matching a specified file extension
* `[[MIME::description]]()`: Return a human-readable description of a MIME type

