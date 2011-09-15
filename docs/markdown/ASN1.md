ASN.1 decoding support.

`ASN1` is an abstract base class.

## Synopsis

```php
uses('asn1');

$structure = ASN1::decodeBER($blob);
```

## Description

The ASN1 class provides the ability to decode ASN.1 structures which
conform to the Basic Encoding Rules (BER).

## Public Static Methods

* `[[ASN1::decodeBER]]()`: Decode a BER-encoded ASN.1 structure.

