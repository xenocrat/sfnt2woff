## What is this?

sfnt2woff is a PHP class for converting OTF/TTF files to WOFF 1.0 and 2.0.

## Requirements

* PHP 8.0+
* ZLIB extension for WOFF 1.0 export
* [Brotli extension](https://github.com/kjdev/php-ext-brotli) for WOFF 2.0 export

## Usage

Convert a font to WOFF 1.0:

    $sfnt2woff = new \xenocrat\sfnt2woff();
    $sfnt = file_get_contents("font.ttf");
    $sfnt2woff->sfnt_import($sfnt);
    $woff = $sfnt2woff->woff1_export();
    file_put_contents("font.woff", $woff);

Convert a font to WOFF 2.0:

    $sfnt2woff = new \xenocrat\sfnt2woff();
    $sfnt = file_get_contents("font.ttf");
    $sfnt2woff->sfnt_import($sfnt);
    $woff = $sfnt2woff->woff2_export();
    file_put_contents("font.woff2", $woff);

## Methods

### `sfnt_import`

#### Description

``` php
public sfnt2woff::sfnt_import(
    string $sfnt
): void
```

This function imports SFNT data from a TTF or OTF font source.

#### Parameters

* _sfnt_

  The SFNT data.

### `woff1_export`

#### Description

``` php
public sfnt2woff::woff1_export(
    int $compression_level = -1,
    bool $verify_checksums = true
): string
```

This function exports SFNT data in WOFF 1.0 format.

#### Parameters

* _compression\_level_

  The compression level, from 0 (minimum) to 9 (maximum).

* _verify\_checksums_

  Whether or not to verify the table checksums before export.

#### Return Values

Returns a complete WOFF 1.0 file as a string of data.

### `woff2_export`

#### Description

``` php
public sfnt2woff::woff2_export(
    int $compression_level = -1,
    bool $verify_checksums = true
): string
```

This function exports SFNT data in WOFF 2.0 format.

#### Parameters

* _compression\_level_

  The compression level, from 0 (minimum) to 11 (maximum).

* _verify\_checksums_

  Whether or not to verify the table checksums before export.

#### Return Values

Returns a complete WOFF 2.0 file as a string of data.

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
