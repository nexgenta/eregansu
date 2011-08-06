Object store implementation with facilities for storage of instances of
`[[RDFInstance]]`.

`RDFStore` is a class derived from [[Store]].

## Synopsis

```php
uses('rdfstore');
```

## Description

RDFStore extends Store to store RDF graphs using the JSON encoding
described at http://n2.talis.com/wiki/RDF_JSON_Specification

## Public Methods

* `[[RDFStore::ingestRDF]]()`
* `[[RDFStore::subjectOfObject]]()`
* `[[RDFStore::objectAsArray]]()`

