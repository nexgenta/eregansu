Formats a UUID as an IRI

## Synopsis

<code>uses('uuid');</code>

<code>public static function <i>string</i> <b>[[UUID]]::iri</b>(<i>string</i> <i>[in]</i> <b>$uuid</b>)</code>

## Description

`UUID::iri()` converts a string representation of a UUID to an IRI
(Internationalized Resource Identifier), specifically a UUID URN.
For example, the null UUID converted to an IRI would be `urn:uuid:00000000-0000-0000-0000-000000000000`.

## Example

* [examples/uuids.php](http://github.com/nexgenta/eregansu/blob/master/examples/uuids.php)

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
      <td><code>$uuid</code>
      <td><i>in</i></td>
      <td>string</td>
      <td>
A string representation of a UUID
      </td>
    </tr>
  </tbody>
</table>

## Return Value

The IRI representation of `$uuid`, or `null` if `$uuid` is not a valid UUID string.

