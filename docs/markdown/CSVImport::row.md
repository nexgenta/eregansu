Read a row from the CSV file.

## Synopsis

<code>uses('csv-import');</code>

<code>public function <i>array</i> <b>[[CSVImport]]::row</b>()</code>

## Description

If a column-to-field mapping has either been provided or has been
read from a header row in the source file, the returned array will
be associative, otherwise it will be numerically-indexed.

## Return Value

An array of values read from the file, or `null` if
the end of file is reached.

