Add an element to the breadcrumb array.

## Synopsis

<code>uses('request');</code>

<code>public function <b>[[Request]]::addCrumb</b>(<i>mixed</i> <i>[in]</i> <b>$info</b>, <i>string</i> <i>[in,optional]</i> <b>$key</b> = null)</code>

## Description

`Request::addCrumb()` adds a new element to the breadcrumb array (`[[Request::$crumb]]`), optionally with an associated key.
The `$info` parameter may be either the name of the current page or an array containing at
least a name element. The link element of the array is used as the URI associated
with this entry in the breadcrumb. If the link element is absent, or the `$info` parameter
was a string, it is set to the value of the `[[Request::$pageUri]]` property.
If $key is specified, the breadcrumb information is associated with the given value. Subsequent
calls to `Request::addCrumb()` specifying the same value for `$key` will overwrite the previously-specified
information (preserving the original order).
If `$key` is not specified, a numeric key will be generated automatically.

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
      <td><code>$info</code>
      <td><i>in</i></td>
      <td>mixed</td>
      <td>
Either the name of the current page as should be presented to a user, or an array containing breadcrumb information.
      </td>
    </tr>
    <tr>
      <td><code>$key</code>
      <td><i>in,optional</i></td>
      <td>string</td>
      <td>
An optional key which the breadcrumb information will be associated with.
      </td>
    </tr>
  </tbody>
</table>

