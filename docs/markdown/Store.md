Base class for complex object stores.

`Store` is a class derived from [[Model]].

## Synopsis

```php
uses('store');
```

## Public Static Methods

* `[[Store::getInstance]]()`

## Public Methods

* `[[Store::__construct]]()`
* `[[Store::uuidOfObject]]()`
* `[[Store::kindOfObject]]()`
* `[[Store::objectAsArray]]()`
* `[[Store::firstObject]]()`
* `[[Store::dataForEntry]]()`
* `[[Store::dataForUUID]]()`
* `[[Store::objectForUUID]]()`
* `[[Store::uuidForIri]]()`: Return the UUID of the object with the specified IRI, $iri.
* `[[Store::dataForIri]]()`
* `[[Store::objectForIri]]()`
* `[[Store::setData]]()`
* `[[Store::query]]()`
* `[[Store::object]]()`
* `[[Store::updateObjectWithUUID]]()`
* `[[Store::deleteObjectWithUUID]]()`
* `[[Store::storedTransaction]]()`
* `[[Store::pendingObjectsSet]]()`
* `[[Store::dirty]]()`
* `[[Store::markAllAsDirty]]()`

