Read a row from the CSV file without mapping columns to fields.

## Synopsis

<code>uses('csv-import');</code>

<code>public function <i>array</i> <b>[[CSVImport]]::rowFlat</b>()</code>

## Return Value

An indexed array of values read from the file, or `null` if
the end of file is reached.

