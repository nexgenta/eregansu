# Eregansu Core Library

## Tasks

* [[ASN.1 decoding|ASN.1-decoding]]
* [[Encoding and decoding base-32 values|Encoding-and-decoding-base-32-values]]
* [[Processing requests|Processing-requests]]
* [[Reading CSV files|Reading-CSV-files]]
* [[Generating UUIDs|Generating-UUIDs]]
* [[Manipulating UUIDs|Manipulating-UUIDs]]

## Interfaces

* `[[ISerialisable]]`: The ISerialisable interface is implemented by classes which can serialise
themselves.
* `[[DataSet]]`
* `[[IDBCore]]`
* `[[IObservable]]`
* `[[ISearchEngine]]`
* `[[ISearchIndexer]]`

## Classes

* `[[ASN1]]`: ASN.1 decoding support.
* `[[Base32]]`: Abstract class implementing base-32 encoding and decoding.
* `[[CLIRequest]]`: Implementation of the Request class for command-line (`cli`) requests.
* `[[CSVImport]]`: Import data from a CSV file
* `[[Curl]]`
* `[[CurlCache]]`
* `[[CurlHeaders]]`
* `[[EregansuDateTime]]`
* `[[LDAP]]`: LDAP database support.
* `[[LDAPSet]]`
* `[[MySQLSchema]]`
* `[[MySQLTable]]`
* `[[MySQL]]`
* `[[MySQLSet]]`
* `[[SQLite3Schema]]`
* `[[SQLite3Table]]`
* `[[SQLite3DB]]`
* `[[SQLite3Set]]`
* `[[DBException]]`: Class encapsulating database-related exceptions.
* `[[DBSystemException]]`
* `[[DBNetworkException]]`
* `[[DBRollbackException]]`
* `[[DBCore]]`
* `[[DBDataSet]]`
* `[[DBIndex]]`
* `[[DBType]]`
* `[[DBCol]]`
* `[[DBSchema]]`
* `[[DBTable]]`
* `[[Form]]`: HTML form generation and handling
* `[[MIME]]`: The `MIME` class provides facilities for mapping file extensions to
MIME types and vice versa and obtaining human-readable descriptions
from MIME types.
* `[[Observers]]`
* `[[RDFDocument]]`
* `[[RDFTripleSet]]`
* `[[RDFTriple]]`
* `[[RDFInstanceBase]]`
* `[[RDFComplexLiteral]]`
* `[[RDFURI]]`
* `[[RDFXMLLiteral]]`
* `[[RDFSet]]`
* `[[RDF]]`: Utility methods for instantiating RDF documents.
* `[[RDFInstance]]`
* `[[RDFDateTime]]`
* `[[RDFString]]`
* `[[RDFXMLStreamParser]]`
* `[[RedlandBase]]`
* `[[RedlandWorld]]`
* `[[RedlandStorage]]`
* `[[RedlandModel]]`
* `[[RedlandParser]]`
* `[[RedlandRDFXMLParser]]`
* `[[RedlandNode]]`
* `[[RedlandSerializer]]`
* `[[RedlandTurtleSerializer]]`
* `[[RedlandN3Serializer]]`
* `[[RedlandRDFXMLSerializer]]`
* `[[RedlandJSONSerializer]]`
* `[[RedlandJSONTriplesSerializer]]`
* `[[RedlandNTriplesSerializer]]`
* `[[Request]]`: Encapsulation of a request from a client.
* `[[HTTPRequest]]`: Encapsulation of an HTTP request.
* `[[DbpediaLiteSearch]]`
* `[[XapianSearch]]`
* `[[XapianIndexer]]`
* `[[SearchEngine]]`
* `[[SearchIndexer]]`
* `[[GenericWebSearch]]`
* `[[Session]]`: Session handling
* `[[TransientSession]]`: Descendant of the Session class which has no persistent storage capabilities.
* `[[URL]]`
* `[[UUID]]`: Abstract class containing UUID manipulation facilities.
* `[[XMLParser]]`
* `[[XMLNS]]`: Placeholder class for XML namespace constants.

