Open the session data, so that changes can be made to it

## Synopsis

<code>public function <b>[[Session]]::begin</b>(<b>$req</b> = null)</code>

## Description

Session::begin() prepares the session data for modifications. Once the
modifications have been completed, you should call Session::commit().
Session::begin() and Session::commit() are re-entrant: provided every
call to Session::begin() has a matching call to Session::commit(), all
except the outermost calls to Session::begin() and Session::commit() will
have no effect.

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
      <td><code>$req</code>
      <td><i></i></td>
      <td></td>
      <td>

      </td>
    </tr>
  </tbody>
</table>

