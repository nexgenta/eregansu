Import data from a CSV file

`CSVImport` is a base class.

## Synopsis

```php
uses('csv-import');

$importer = new CSVImport('/path/to/file.csv');
```

## Description

The `CSVImport` class provides the ability to import data from
CSV files.

## Public Methods

* `[[CSVImport::__construct]]()`: Initialise a `CSVImport` instance.
* `[[CSVImport::readFields]]()`: Read the list of field names from a CSV file.
* `[[CSVImport::setFields]]()`: Specify an explicit column-to-field mapping.
* `[[CSVImport::rewind]]()`: Move the file pointer back to the beginning of the file.
* `[[CSVImport::rowFlat]]()`: Read a row from the CSV file without mapping columns to fields.
* `[[CSVImport::row]]()`: Read a row from the CSV file.

