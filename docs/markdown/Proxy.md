Routable class designed to support presenting views of data objects.

`Proxy` is a class derived from [[Router]].

## Description

The `Proxy` class is a descendant of `[[Router]]` intended to be
used in situations where objects are retrieved via a `[[Model]]` and
presented according to the `[[Request]]`. That is, conceptually,
descendants of this class are responsible for proxying objects from storage
to presentation. `[[Page]]` and `[[CommandLine]]` are notable
descendants of `Proxy`.

## Public Methods

* `[[Proxy::__get]]()`

