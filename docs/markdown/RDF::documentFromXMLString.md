Create a new `[[RDFDocument]]` given a string containin an RDF/XML
document.

## Synopsis

<code>uses('rdf');</code>

<code>public static function <i>RDFDocument</i> <b>[[RDF]]::documentFromXMLString</b>(<i>string</i> <i>[in]</i> <b>$document</b>, <i>string</i> <i>[in,optional]</i> <b>$location</b> = null, <b>$curl</b> = null)</code>

## Description

Parses the RDF/XML contained within `$document` and passes the
resulting DOM tree to `[[RDF::documentFromDOM]]()`, returning the resulting
`[[RDFDocument]]`.

## Parameters

<table>
  <thead>
    <tr>
      <th>Name</th>
      <th>Direction</th>
      <th>Type</th>
      <th>Description</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><code>$document</code>
      <td><i>in</i></td>
      <td>string</td>
      <td>
The string containing the RDF/XML document.
      </td>
    </tr>
    <tr>
      <td><code>$location</code>
      <td><i>in,optional</i></td>
      <td>string</td>
      <td>
The canonical source URL of the
document.
      </td>
    </tr>
    <tr>
      <td><code>$curl</code>
      <td><i></i></td>
      <td></td>
      <td>

      </td>
    </tr>
  </tbody>
</table>

## Return Value

On success, returns a new `[[RDFDocument]]` instance.

