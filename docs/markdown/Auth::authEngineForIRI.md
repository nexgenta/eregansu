Create an instance of an authentication system given an IRI.

## Synopsis

<code>uses('auth');</code>

<code>public static function <b>[[Auth]]::authEngineForIRI</b>(<i>string</i> <i>[in,out]</i> <b>$iri</b>, <i>string</i> <i>[out]</i> <b>$scheme</b>, <i>string</i> <i>[in]</i> <b>$defaultScheme</b> = null)</code>

## Description

The instance is returned by the call to `[[Auth::authEngineForScheme]]()`.
`$iri` will be modified to strip the scheme (if supplied), which will
be stored in `$scheme`. Thus, upon successful return, a fully-qualified
IRI can be constructed from `$scheme . ':' . $iri`

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
      <td><code>$iri</code>
      <td><i>in,out</i></td>
      <td>string</td>
      <td>
The IRI to match against
      </td>
    </tr>
    <tr>
      <td><code>$scheme</code>
      <td><i>out</i></td>
      <td>string</td>
      <td>
The authentication IRI scheme that was
determined
      </td>
    </tr>
    <tr>
      <td><code>$defaultScheme</code>
      <td><i>in</i></td>
      <td>string</td>
      <td>
The default authentication scheme to
use if none can be determined from <code>$iri</code>
      </td>
    </tr>
  </tbody>
</table>

