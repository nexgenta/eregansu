# Eregansu Platform

## Interfaces

* `[[IAuthEngine]]`: Interface implemented by authentication engines.
* `[[IRequestProcessor]]`: The interface implemented by all classes which can process requests.
* `[[ICommandLine]]`: Interface implemented by command-line routable classes.

## Classes

* `[[LoginPage]]`
* `[[AuthError]]`: Exception class whose instances are thrown when an authentication exception
occurs.
* `[[Auth]]`
* `[[BuiltinAuth]]`
* `[[CliHelp]]`: Implements the default 'help' command-line route
* `[[CliSetup]]`
* `[[TerminalErrorException]]`
* `[[Error]]`
* `[[IdentityModule]]`
* `[[Identity]]`: Identity management.
* `[[IdentityFile]]`: Support for static identity data in an XML file.
* `[[IdentityDirectory]]`: Identity/authorisation database using an LDAP directory server.
* `[[Model]]`: Base class for data models.
* `[[Module]]`
* `[[OpenIDAuth]]`
* `[[Page]]`: Templated web page generation.
* `[[PosixAuth]]`: Implementation of the `posix:` pseudo-authentication scheme.
* `[[RDFStore]]`: Object store implementation with facilities for storage of instances of
`[[RDFInstance]]`.
* `[[RDFStoredObject]]`
* `[[Loader]]`: Route module loader.
* `[[Routable]]`: Base class for all Eregansu-provided routable instances.
* `[[Redirect]]`: Perform a redirect when a route is requested.
* `[[Router]]`: A routable class capable of passing a request to child routes.
* `[[App]]`: A routable class which encapsulates an application.
* `[[DefaultApp]]`: The default application class.
* `[[HostnameRouter]]`: Route requests to a particular app based upon a domain name.
* `[[Proxy]]`: Routable class designed to support presenting views of data objects.
* `[[CommandLine]]`: Encapsulation of a command-line interface handler.
* `[[StoreModule]]`
* `[[Storable]]`: Base class for encapsulations of stored objects.
* `[[StorableSet]]`: Base class for datasets whose rows are instances of `[[Storable]]`
* `[[StaticStorableSet]]`: Implementation of a `[[StorableSet]]` which uses a static list of objects.
* `[[DBStorableSet]]`: Implementation of a `[[StorableSet]]` which is driven by the results of a
database query.
* `[[Store]]`: Base class for complex object stores.
* `[[Template]]`: Eregansu web page templating.

