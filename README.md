## What is this?

sfnt2woff is a PHP class for converting OTF, TTF, and TTC files to WOFF 1.0 and 2.0.

## Requirements

* PHP 8.0+
* ZLIB extension for WOFF 1.0 export
* [Brotli extension](https://github.com/kjdev/php-ext-brotli) for WOFF 2.0 export

## Limitations

WOFF 2.0 export uses a null transformation: table data is not optimized.

## Usage

Convert a font to WOFF 1.0:

``` php
$sfnt2woff = new \xenocrat\sfnt2woff();
$sfnt = file_get_contents("font.ttf");
$sfnt2woff->sfnt_import($sfnt);
$woff = $sfnt2woff->woff1_export();
file_put_contents("font.woff", $woff);
```

Convert a font to WOFF 2.0:

``` php
$sfnt2woff = new \xenocrat\sfnt2woff();
$sfnt = file_get_contents("font.ttf");
$sfnt2woff->sfnt_import($sfnt);
$woff = $sfnt2woff->woff2_export();
file_put_contents("font.woff2", $woff);
```

## Methods

### `sfnt_import`

#### Description

``` php
public sfnt2woff::sfnt_import(
    string $sfnt,
    bool $verify_checksums = true
): void
```

Imports SFNT data from a TTF or OTF font source.

#### Parameters

* _sfnt_

  A complete TTF or OTF file as a string of data.

* _verify\_checksums_

  Whether or not to verify the table data checksums on import.

### `otfc_import`

#### Description

``` php
public sfnt2woff::otfc_import(
    string $otfc,
    bool $verify_checksums = true
): void
```

Imports TTC or OTC data from a TrueType or OpenType font collection.

#### Parameters

* _otfc_

  A complete TTC or OTC file as a string of data.

* _verify\_checksums_

  Whether or not to verify the table data checksums on import.

### `otfc_extract`

#### Description

``` php
public sfnt2woff::otfc_extract(
    int $index
): void
```

Extracts a font from a collection for export using `woff1_export` or `woff2_export`.

#### Parameters

* _index_

  The zero-based index of the font in a collection imported using `otfc_import`.

### `woff1_export`

#### Description

``` php
public sfnt2woff::woff1_export(
    int $compression_level = -1
): string
```

Exports SFNT data in WOFF 1.0 format.

#### Parameters

* _compression\_level_

  The compression level, from 0 (minimum) to 9 (maximum).

#### Return Values

Returns a complete WOFF 1.0 file as a string of data.

### `woff2_export`

#### Description

``` php
public sfnt2woff::woff2_export(
    int $compression_level = -1
): string
```

Exports SFNT data in WOFF 2.0 format.

#### Parameters

* _compression\_level_

  The compression level, from 0 (minimum) to 11 (maximum).

#### Return Values

Returns a complete WOFF 2.0 file as a string of data.

### `woffc_export`

#### Description

``` php
public sfnt2woff::woffc_export(
    int $compression_level = -1
): string
```

Exports TTC or OTC data in WOFF 2.0 font collection format.

#### Parameters

* _compression\_level_

  The compression level, from 0 (minimum) to 11 (maximum).

#### Return Values

Returns a complete WOFF 2.0 collection file as a string of data.

### `set_woff_version`

#### Description

``` php
public sfnt2woff::set_woff_version(
    int $major,
    int $major
): void
```

Set the major and minor version numbers for WOFF exports.

#### Parameters

* _major_

  The major version, an integer in the range 0-65535.

* _minor_

  The minor version, an integer in the range 0-65535.

### `get_woff_version`

#### Description

``` php
public sfnt2woff::get_woff_version(
): array
```

Get the major and minor version numbers for WOFF exports.

#### Return Values

Returns an array of two integers representing the major and minor version numbers.

### `set_woff_meta`

#### Description

``` php
public sfnt2woff::set_woff_meta(
    SimpleXMLElement $object
): void
```

Set the WOFF extended metadata block.

#### Parameters

* _object_

  A SimpleXMLElement object representing the XML metadata.

### `get_woff_meta`

#### Description

``` php
public sfnt2woff::get_woff_meta(
): object|false
```

Get the WOFF extended metadata block.

#### Return Values

Returns a SimpleXMLElement object representing the XML metadata, or `false` if no metadata block has been set.

### `set_woff_priv`

#### Description

``` php
public sfnt2woff::set_woff_priv(
    string $data
): void
```

Set the WOFF private data block.

#### Parameters

* _data_

  A string of data representing the private data block.

### `get_woff_priv`

#### Description

``` php
public sfnt2woff::get_woff_priv(
): string|false
```

Get the WOFF private data block.

#### Return Values

Returns a string of data representing the private data block, or `false` if no private data block has been set.

### `get_otfc_count`

#### Description

``` php
public sfnt2woff::get_otfc_count(
): int|false
```

Gets the count of fonts in a collection imported using `otfc_import`.

#### Return Values

Returns an integer, or `false` if no TTC or OTC data has been imported.
