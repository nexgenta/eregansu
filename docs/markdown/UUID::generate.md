Generate a new UUID

## Synopsis

<code>uses('uuid');</code>

<code>public static function <i>string</i> <b>[[UUID]]::generate</b>(<i>int</i> <i>[in,optional]</i> <b>$kind</b> = self, <i>string</i> <i>[in,optional]</i> <b>$namespace</b> = null, <i>string</i> <i>[in,optional]</i> <b>$name</b> = null)</code>

## Description

`UUID::generate()` generates a new UUID according to [RFC 4122](http://www.ietf.org/rfc/rfc4122.txt) (equivalent to
ITU-T Rec. X.667, ISO/IEC 9834-8:2005).
If the kind of UUID specified by `$kind` cannot be generated
because it is not supported, a random (v4) UUID will be generated instead (in other
words, the `$kind` parameter is a hint).
If the kind of UUID specified by `$kind` cannot be generated
because one or both of `$namespace` and `$name`
are not valid, an error occurs and `null` is returned.

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
      <td><code>$kind</code>
      <td><i>in,optional</i></td>
      <td>int</td>
      <td>
The kind of UUID to generate.
      </td>
    </tr>
    <tr>
      <td><code>$namespace</code>
      <td><i>in,optional</i></td>
      <td>string</td>
      <td>
For MD5 (v3) and SHA1 (v5) UUIDs, the namespace which contains <code>$name</code>.
      </td>
    </tr>
    <tr>
      <td><code>$name</code>
      <td><i>in,optional</i></td>
      <td>string</td>
      <td>
For MD5 (v3) and SHA1 (v5) UUIDs, the identifier used to generate the UUID.
      </td>
    </tr>
  </tbody>
</table>

## Return Value

A new UUID, or `null` if an error occurs.

