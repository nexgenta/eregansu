Support for static identity data in an XML file.

`IdentityFile` is a class derived from [[Identity]].

## Synopsis

```php
uses('id');
```

## Description

This class implements a read-only identity database read from an XML file.
Note that users authenticating using the 'builtin:' and 'posix:' schemes
will always pass identity checks, because the authentication layers are
capable of providing the required details themselves. These authentication
schemes will function even if no identity system is in use at all (that is,
IDENTITY_IRI is not defined).

## Public Methods

* `[[IdentityFile::__construct]]()`
* `[[IdentityFile::uuidFromIRI]]()`
* `[[IdentityFile::identityFromUUID]]()`
* `[[IdentityFile::createIdentity]]()`

