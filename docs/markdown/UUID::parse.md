Parse a string containing a UUID and return an array representing its value.

## Synopsis

<code>uses('uuid');</code>

<code>public static function <i>array</i> <b>[[UUID]]::parse</b>(<i>string</i> <i>[in]</i> <b>$uuid</b>)</code>

## Description

`UUID::parse()` converts a string representation of a UUID to an array. The
array contains the following members:
- `time_low`
- `time_mid`
- `time_hi_and_version`
- `clock_seq_hi_and_reserved`
- `clock_seq_low`
- `node`
- `version`
- `variant`
The `version` member contains a UUID version number, for example `UUID::RANDOM`.
The `variant` member specifies the UUID variant, for example `UUID::DCE`.

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

An array representing the supplied UUID, or `null` if an error occurs.

