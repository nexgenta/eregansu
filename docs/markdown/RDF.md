Utility methods for instantiating RDF documents.

`RDF` is an abstract class derived from [[XMLNS]].

## Synopsis

```php
uses('rdf');
```

## Public Static Methods

* `[[RDF::documentFromDOM]]()`: Create a new `[[RDFDocument]]` given an RDF/XML `[[DOMElement]]`.
* `[[RDF::tripleSetFromDOM]]()`: Create a new set of triples from an RDF/XML DOMElement
* `[[RDF::documentFromXMLString]]()`: Create a new `[[RDFDocument]]` given a string containin an RDF/XML
document.
* `[[RDF::tripleSetFromXMLString]]()`
* `[[RDF::documentFromFile]]()`
* `[[RDF::documentFromURL]]()`
* `[[RDF::tripleSetFromURL]]()`
* `[[RDF::ns]]()`
* `[[RDF::instanceForClass]]()`
* `[[RDF::barePredicates]]()`
* `[[RDF::uriPredicates]]()`

