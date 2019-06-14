## What is this?

sfnt2woff is a PHP class for converting OTF/TTF files to WOFF.

## Requirements

* PHP 5.4+
* ZLIB extension

## Usage

Convert a font to WOFF:

    $sfnt2woff = new sfnt2woff();
    $sfnt = file_get_contents("font.ttf");
    $sfnt2woff->import($sfnt);
    $woff = $sfnt2woff->export();
    file_put_contents("font.woff", $woff);

Import OTF/TTF font file:

    $sfnt2woff->import($sfnt);

Disable the integrity test:

    $sfnt2woff->strict = false;

Set the WOFF file version:

    $sfnt2woff->version_major = 1;
    $sfnt2woff->version_minor = 1;

Export the WOFF font file:

    $woff = $sfnt2woff->export();
