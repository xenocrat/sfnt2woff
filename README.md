## What is this?

sfnt2woff is a PHP class for converting OTF/TTF files to WOFF.

## Requirements

* PHP 8.0+
* ZLIB extension

## Usage

Convert a font to WOFF:

    $sfnt2woff = new \xenocrat\sfnt2woff();
    $sfnt = file_get_contents("font.ttf");
    $sfnt2woff->import($sfnt);
    $woff = $sfnt2woff->export();
    file_put_contents("font.woff", $woff);

Import OTF/TTF font file:

    $sfnt2woff->import($sfnt);

Disable the integrity test:

    $sfnt2woff->strict = false;

Set the compression level (1-9):

    $sfnt2woff->compression_level = 9;

Set the WOFF file version:

    $sfnt2woff->version_major = 1;
    $sfnt2woff->version_minor = 1;

Set the extended metadata block:

    $xml = simplexml_load_file("example.xml");
    $sfnt2woff->set_meta($xml);

Set the private data block:

    $string = sha1("example");
    $sfnt2woff->set_priv($string);

Export the WOFF font file:

    $woff = $sfnt2woff->export();
