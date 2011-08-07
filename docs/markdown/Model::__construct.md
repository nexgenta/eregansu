Construct an instance of `[[Model]]`.

## Synopsis

<code>uses('model');</code>

<code>public function <b>[[Model]]::__construct</b>(<i>array</i> <i>[in]</i> <b>$args</b>)</code>

## Description

If `$args['db']` is a string of nonzero length, `[[Model::$dbIri]]` will be
set to its value, and `[[Model::$db]]` will be assigned the result of passing
it to `[[DBCore::connect]]()` in order to establish a database connection.

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
      <td><code>$args</code>
      <td><i>in</i></td>
      <td>array</td>
      <td>
Initialisation parameters.
      </td>
    </tr>
  </tbody>
</table>

