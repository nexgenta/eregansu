Decode a base-32 string and return the value as an integer

## Synopsis

<code>uses('base32');</code>

<code>public static function <i>int</i> <b>[[Base32]]::decode</b>(<i>string</i> <i>[in]</i> <b>$input</b>)</code>

## Description

Accepts a base-32-encoded string as encoded by `[[Base32::encode]]()` and
returns its integer value.

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
      <td><code>$input</code>
      <td><i>in</i></td>
      <td>string</td>
      <td>
A base-32 encoded value
      </td>
    </tr>
  </tbody>
</table>

## Return Value

The integer value represented by `$input`

