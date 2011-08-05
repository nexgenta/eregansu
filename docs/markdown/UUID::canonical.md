Return the canonical form of a UUID string (i.e., no braces, no dashes, all lower-case)

## Synopsis

<code>uses('uuid');</code>

<code>public static function <i>string</i> <b>[[UUID]]::canonical</b>(<i>string</i> <i>[in]</i> <b>$uuid</b>)</code>

## Description

`UUID::canonical()` accepts a string representation of a UUID (for example, as returned by
`[[UUID::generate]]()`) and returns the canonical form of the UUID: that is, all-lowercase, and with
any braces and dashes removed.
For example, the canonical form of the UUID string ``}
would be `'eae58635b82642a99b033a3ac8a2cc29'`.

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
A string representation of a UUID.
      </td>
    </tr>
  </tbody>
</table>

## Return Value

The canonical form of the UUID, or `null` if `$uuid` is not a valid UUID string.

