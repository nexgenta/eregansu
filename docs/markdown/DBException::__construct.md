The `[[DBException]]` constructor is responsible for initialising a new
database exception object.

## Synopsis

<code>uses('db');</code>

<code>public function <b>[[DBException]]::__construct</b>(<b>$errCode</b>, <b>$errMsg</b>, <b>$query</b>)</code>

## Description

The constructor will automatically populate the `[[DBException]]`
instance's properties and generate a complete exception message which is
passed along with `$errCode` to [Exception::__construct](http://www.php.net/manual/en/exception.construct.php).

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
      <td><code>$errCode</code>
      <td><i></i></td>
      <td></td>
      <td>

      </td>
    </tr>
    <tr>
      <td><code>$errMsg</code>
      <td><i></i></td>
      <td></td>
      <td>

      </td>
    </tr>
    <tr>
      <td><code>$query</code>
      <td><i></i></td>
      <td></td>
      <td>

      </td>
    </tr>
  </tbody>
</table>

