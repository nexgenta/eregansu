Encode an integer as base-32

## Synopsis

<code>uses('base32');</code>

<code>public static function <i>string</i> <b>[[Base32]]::encode</b>(<i>int</i> <i>[in]</i> <b>$input</b>)</code>

## Description

Encodes an integer as a base-32 value, that is, a value where each digit
has 32 possible values (0-9, a-x). The letters 'i', 'l', 'o', 'y' and
'z' are not included in the alphabet.

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
      <td>int</td>
      <td>
The number to encode
      </td>
    </tr>
  </tbody>
</table>

## Return Value

A string containing the value of `$input` encoded as base-32

