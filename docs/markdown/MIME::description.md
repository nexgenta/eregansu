Return a human-readable description of a MIME type

## Synopsis

<code>uses('mime');</code>

<code>public static function <b>[[MIME]]::description</b>(<i>string</i> <i>[in]</i> <b>$type</b>)</code>

## Description

`MIME::description()` returns a human-readable description of a specified
MIME type.
For example, the description for video/mp4 might be MPEG 4 video.

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
The MIME type to obtain a description for
      </td>
    </tr>
  </tbody>
</table>

## Return Value

string A human-readable description for `$type`

