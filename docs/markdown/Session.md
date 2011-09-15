Session handling

`Session` is a base class.

## Description

The Session class implements basic session handling, based in part upon
PHP‘s own session support.
The Session class overloads property access, such that values stored against
the session are represented as properties of the Session class.
Before making changes to session data, you must call Session::begin(). When
changes have been completed, you must call Session::commit() to write the
session data back to the underlying storage (for example, files on disk).
The Request class implements automatic support for the Session class: a
session is lazily attached (using Session::sessionForRequest()) when the
Request::$session property is first accessed.
The following named constants may be defined prior to a session being attached
which affect the behaviour of the Session class:
- \c SESSION_COOKIE_NAME: The name of the session cookie (defaults to \c sid)
- \c SESSION_COOKIE_DOMAIN: The domain name used for the session cookie (defaults to being unset)
- \c SESSION_PARAM_NAME: The name of the URL parameter which may contain a session ID (defaults to \c sid)
- \c SESSION_FIELD_NAME: The name of the form field which may contain a session ID (defaults to \c sid)

## Public Static Methods

* `[[Session::sessionForRequest]]()`: Return a Session instance associated with a a given request

## Public Methods

* `[[Session::commit]]()`: Commit changes to the session data
* `[[Session::begin]]()`: Open the session data, so that changes can be made to it

