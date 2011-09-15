Generate a fully-qualified URI for a namespace URI and local name.

## Synopsis

<code>public static function <i>string</i> <b>[[XMLNS]]::fqname</b>(<i>mixed</i> <i>[in]</i> <b>$namespaceURI</b>, <i>string</i> <i>[in,optional]</i> <b>$local</b> = null)</code>

## Note

If `$namespaceURI` is a `DOMNode`, `$local` must be `null`. If `$namespaceURI` is a string, `$local` must not be `null`.

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
      <td><code>$namespaceURI</code>
      <td><i>in</i></td>
      <td>mixed</td>
      <td>
A string containing a namespace URI, or
a DOMNode instance whose fully-qualified node name should be returned.
      </td>
    </tr>
    <tr>
      <td><code>$local</code>
      <td><i>in,optional</i></td>
      <td>string</td>
      <td>
The local part to combine with
<code>$namespaceURI</code>.
      </td>
    </tr>
  </tbody>
</table>

## Return Value

On success, returns a fully-qualified URI.

