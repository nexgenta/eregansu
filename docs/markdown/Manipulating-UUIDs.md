# Manipulating UUIDs

* `[[UUID::isUUID]]()`: Determine whether a string is a valid UUID or not
* `[[UUID::canonical]]()`: Return the canonical form of a UUID string (i.e., no braces, no dashes, all lower-case)
* `[[UUID::iri]]()`: Formats a UUID as an IRI
* `[[UUID::formatted]]()`: Format a UUID in the traditional aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee form
* `[[UUID::parse]]()`: Parse a string containing a UUID and return an array representing its value.
* `[[UUID::unparse]]()`: Constructs a UUID string given an array as returned by UUID::parse()
