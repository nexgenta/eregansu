Create a new `[[RDFDocument]]` given an RDF/XML `[[DOMElement]]`.

## Synopsis

<code>uses('rdf');</code>

<code>public static function <i>RDFDocument</i> <b>[[RDF]]::documentFromDOM</b>(<i>DOMElement</i> <i>[in]</i> <b>$dom</b>, <i>string</i> <i>[in,optional]</i> <b>$location</b> = null)</code>

## Description

Construct a new `[[RDFDocument]]` given the root element of an RDF/XML
document, such as that returned by dom_import_simplexml().

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
      <td><code>$dom</code>
      <td><i>in</i></td>
      <td>DOMElement</td>
      <td>
The root element of the RDF/XML document
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
  </tbody>
</table>

## Return Value

On success, returns a new `[[RDFDocument]]` instance.

