Constructs a UUID string given an array as returned by UUID::parse()

## Synopsis

<code>uses('uuid');</code>

<code>public static function <i>string</i> <b>[[UUID]]::unparse</b>(<i>array</i> <i>[in]</i> <b>$info</b>)</code>

## Description

`UUID::unparse()` accepts an array representation of a UUID as returned by
`[[UUID::parse]]()` and returns a string representation of the same UUID.

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
      <td><code>$info</code>
      <td><i>in</i></td>
      <td>array</td>
      <td>
An array representation of a UUID
      </td>
    </tr>
  </tbody>
</table>

## Return Value

A string representing the supplied UUID

