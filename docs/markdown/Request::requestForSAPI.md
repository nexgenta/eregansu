Return an instance of a Request class for a specified SAPI.

## Synopsis

<code>uses('request');</code>

<code>public static function <i>Request</i> <b>[[Request]]::requestForSAPI</b>(<i>string</i> <i>[in,optional]</i> <b>$sapi</b> = null)</code>

## Description

Requests are encapsulated as one of several descendants of the `[[Request]]`
class, depending upon the SAPI in use.
If no SAPI name is specified when calling `Request::requestForSAPI()`, the current
SAPI name as identified by PHP (using php_sapi_name) will be used.
Additionally, if `REQUEST_CLASS` is defined and no SAPI name is specified, an instance
of the class named by `REQUEST_CLASS` will be created instead of the default for the
current SAPI.

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
      <td><code>$sapi</code>
      <td><i>in,optional</i></td>
      <td>string</td>
      <td>
The name of the SAPI to return an instance for
      </td>
    </tr>
  </tbody>
</table>

## Return Value

An instance of a request class matching the specified or default SAPI.

