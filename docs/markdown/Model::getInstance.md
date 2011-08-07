Obtains an instance of one of `[[Model]]`'s descendants.

## Synopsis

<code>uses('model');</code>

<code>public static function <i>Model</i> <b>[[Model]]::getInstance</b>(<i>array</i> <i>[in,optional]</i> <b>$args</b> = null)</code>

## Description

If `$args['class']` is not set, `Model::getInstance()` will immediately
return `null`.
Otherwise, an instance of the named class will be obtained, and its
[[constructorModel::__construct]] will be invoked, passing `$args`.
Descendants should override `Model::getInstance()` to set `$args['class']` to
the name of the class if it's not set.
Descendants should, if possible, ensure that `$args['db']` is set to
a database connection IRI which can be passed to `[[DBCore::connect]]()`.
The combination of `$args['class']` and `$args['db']` are used to
construct a key into the shared instance list. When a new instance is
constructed, it is stored with this key in the list. If an entry with
the key is already present, it will be returned and no new instance
will be created.

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
      <td><i>in,optional</i></td>
      <td>array</td>
      <td>
Initialisation parameter array.
      </td>
    </tr>
  </tbody>
</table>

## Return Value

On success, returns an instance of a descendant of `[[Model]]`.

