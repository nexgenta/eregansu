Determine whether a string is a valid UUID or not

## Synopsis

<code>uses('uuid');</code>

<code>public static function <i>string</i> <b>[[UUID]]::isUUID</b>(<i>string</i> <i>[in]</i> <b>$str</b>)</code>

## Description

`UUID::isUUID()` tests whether a string consists of a valid UUID.

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
      <td><code>$str</code>
      <td><i>in</i></td>
      <td>string</td>
      <td>
The string that is potentially a UUID.
      </td>
    </tr>
  </tbody>
</table>

## Return Value

If `$str` is a UUID, then the return value is `$str`,
otherwise `null` is returned.

