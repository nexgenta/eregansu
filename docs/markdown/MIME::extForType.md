Return the preferred file extension for a specified MIME type

## Synopsis

<code>uses('mime');</code>

<code>public static function <b>[[MIME]]::extForType</b>(<i>string</i> <i>[in]</i> <b>$type</b>)</code>

## Description

`MIME::extForType()` returns the preferred file extension, if any, for a
given MIME type. For example, the preferred extension string for the
text/plain type is .txt.
If a file extension mapping exists, it will be returned with a leading
dot. If no file extension mapping exists, an empty string will be
returned.

## Example

* [mimetest.php](http://github.com/nexgenta/eregansu/blob/master/mimetest.php)

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
      <td><code>$type</code>
      <td><i>in</i></td>
      <td>string</td>
      <td>
The MIME type to resolve to an extension
      </td>
    </tr>
  </tbody>
</table>

## Return Value

string The preferred file extension for `$type`, or an empty string if no mapping exists.

