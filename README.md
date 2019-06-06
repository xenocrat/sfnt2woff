## What is this?

sfnt2woff is a PHP class for converting OTF/TFF files to WOFF.

## Usage

    $sfnt2woff = new sfnt2woff();
    $sfnt = file_get_contents("font.ttf");
    $sfnt2woff->import($sfnt);
    $woff = $sfnt2woff->export();
    file_put_contents("font.woff", $woff);

## Requirements

* PHP 5.4+
