This is the Eregansu library, which provides a collection of
loosely-connected classes and functions which make up the core of the
platform.

The common initialisation code is invoked by including common.php. Following
that, code can either call the uses() function or require_once() specific
files to obtain the functionality they need.

The code here is self-contained (it doesn’t depend on other parts of Eregansu)
and interdependencies are kept to a minimum. For example, the database layer
in db.php can be used with little difficulty outside of an Eregansu-based
application (it has been used, for example, within a WordPress plug-in).
