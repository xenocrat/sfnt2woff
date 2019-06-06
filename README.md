sfnt2woff
=========

Usage:

    $sfnt2woff = new sfnt2woff();
    $sfnt2woff->import(file_get_contents("font.ttf"));
    $woff = $sfnt2woff->export();
    file_put_contents("font.woff", $woff);
