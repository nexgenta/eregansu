Decode a BER-encoded ASN.1 structure.

## Synopsis

<code>uses('asn1');</code>

<code>public static function <i>array</i> <b>[[ASN1]]::decodeBER</b>(<i>string</i> <i>[in]</i> <b>$binary</b>)</code>

## Description

`ASN1::decodeBER()` provides limited support for decoding a binary ASN.1
structure which was encoded using the Basic Encoding Rules (BER), such
as X.509 certificate data.
Each member of the returned array is itself an associative array with
the following fields: `$type`, `$pc`, `$class`, `$len`,
and `$data` (note that `$data` is base-64 encoded).
For entries which are sequences, the `$sequence` member is an
indexed array of structures.
For entries which are bit-strings, the `$value` member is set to the
value of the bit-string, and the `$unused` member is set to the
number of leading unused bits in the string.

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
      <td><code>$binary</code>
      <td><i>in</i></td>
      <td>string</td>
      <td>
The BER-encoded ASN.1 structure
      </td>
    </tr>
  </tbody>
</table>

## Return Value

An indexed array representing the ASN.1 structure.

