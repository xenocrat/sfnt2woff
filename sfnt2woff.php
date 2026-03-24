<?php
    namespace xenocrat;

    class sfnt2woff {
        const VERSION_MAJOR      = 4;
        const VERSION_MINOR      = 0;
        const VERSION_PATCH      = 0;
        const WOFF_RESERVED      = 0;

        const SFNT_FLAVOR_0100   = "00010000";
        const SFNT_FLAVOR_OTTO   = "4F54544F";
        const SFNT_FLAVOR_TRUE   = "74727565";
        const SFNT_FLAVOR_TTCF   = "74746366";

        const SFNT_OFFSET_SIZE   = 12;
        const SFNT_ENTRY_SIZE    = 16;

        const WOFF1_SIGNATURE    = 0x774F4646;
        const WOFF1_HEADER_SIZE  = 44;
        const WOFF1_ENTRY_SIZE   = 20;

        const WOFF2_SIGNATURE    = 0x774F4632;
        const WOFF2_HEADER_SIZE  = 48;

        const WOFF2_KNOWN_TABLES = array(
            "cmap" => 0,
            "head" => 1,
            "hhea" => 2,
            "hmtx" => 3,
            "maxp" => 4,
            "name" => 5,
            "OS/2" => 6,
            "post" => 7,
            "cvt"  => 8,
            "fpgm" => 9,
            "glyf" => 10,
            "loca" => 11,
            "prep" => 12,
            "CFF"  => 13,
            "VORG" => 14,
            "EBDT" => 15,
            "EBLC" => 16,
            "gasp" => 17,
            "hdmx" => 18,
            "kern" => 19,
            "LTSH" => 20,
            "PCLT" => 21,
            "VDMX" => 22,
            "vhea" => 23,
            "vmtx" => 24,
            "BASE" => 25,
            "GDEF" => 26,
            "GPOS" => 27,
            "GSUB" => 28,
            "EBSC" => 29,
            "JSTF" => 30,
            "MATH" => 31,
            "CBDT" => 32,
            "CBLC" => 33,
            "COLR" => 34,
            "CPAL" => 35,
            "SVG"  => 36,
            "sbix" => 37,
            "acnt" => 38,
            "avar" => 39,
            "bdat" => 40,
            "bloc" => 41,
            "bsln" => 42,
            "cvar" => 43,
            "fdsc" => 44,
            "feat" => 45,
            "fmtx" => 46,
            "fvar" => 47,
            "gvar" => 48,
            "hsty" => 49,
            "just" => 50,
            "lcar" => 51,
            "mort" => 52,
            "morx" => 53,
            "opbd" => 54,
            "prop" => 55,
            "trak" => 56,
            "Zapf" => 57,
            "Silf" => 58,
            "Glat" => 59,
            "Gloc" => 60,
            "Feat" => 61,
            "Sill" => 62
        );

        private $version_major   = self::VERSION_MAJOR;
        private $version_minor   = self::VERSION_MINOR;
        private $sfnt_offset     = array();
        private $sfnt_tables     = array();
        private $woff_tables     = array();
        private $woff_meta       = array();
        private $woff_priv       = array();

        public function sfnt_import(
            $sfnt
        ): void {
            if (!is_string($sfnt))
                throw new \InvalidArgumentException(
                    "File must be supplied as a string."
                );

            $sfnt_length = strlen($sfnt);
            $sfnt_tables = array();
            $woff_tables = array();

            if (self::SFNT_OFFSET_SIZE > $sfnt_length)
                throw new \RangeException(
                    "File does not contain SFNT data."
                );

            $sfnt_offset = unpack(
                "H8flavor/nnumTables",
                $sfnt
            );

            $table_count = $sfnt_offset["numTables"];

            for ($i = 0; $i < $table_count; $i++) {

                $offset = self::SFNT_OFFSET_SIZE + (
                    $i * self::SFNT_ENTRY_SIZE
                );

                $target = $offset + self::SFNT_ENTRY_SIZE;

                if ($target > $sfnt_length)
                    throw new \LengthException(
                        "File ended unexpectedly."
                    );

                $sfnt_tables[$i] = unpack(
                    "A4tag/H8checkSum/H8offset/H8length",
                    $sfnt,
                    $offset
                );

                $sfnt_tables[$i]["offset"] = hexdec(
                    $sfnt_tables[$i]["offset"]
                );

                $sfnt_tables[$i]["length"] = hexdec(
                    $sfnt_tables[$i]["length"]
                );

                $target = (
                    $sfnt_tables[$i]["offset"] +
                    $sfnt_tables[$i]["length"]
                );

                if ($target > $sfnt_length)
                    throw new \LengthException(
                        "File ended unexpectedly."
                    );

                $sfnt_tables[$i]["tableData"] = substr(
                    $sfnt,
                    $sfnt_tables[$i]["offset"],
                    $sfnt_tables[$i]["length"]
                );
            }

            $this->sfnt_offset = $sfnt_offset;
            $this->sfnt_tables = $sfnt_tables;
            $this->woff_tables = $woff_tables;
        }

        public function woff1_export(
            $compression_level = -1,
            $verify_checksums = true
        ): string {
            $woff_flavor = $this->sfnt_offset["flavor"];
            $woff_tables = array();

            $sfnt_tables = $this->sort_tables_by_offset(
                $this->sfnt_tables
            );

            $table_count = count($sfnt_tables);

            $woff_offset = self::WOFF1_HEADER_SIZE + (
                $table_count * self::WOFF1_ENTRY_SIZE
            );

            $sfnt_offset = self::SFNT_OFFSET_SIZE + (
                $table_count * self::SFNT_ENTRY_SIZE
            );

            if (empty($sfnt_tables))
                throw new \LengthException(
                    "No SFNT data to export."
                );

            for ($i = 0; $i < $table_count; $i++) {
                $sfnt_table_orig = $sfnt_tables[$i]["tableData"];

                $sfnt_table_comp = $this->gz_compress(
                    $sfnt_table_orig,
                    $compression_level
                );

                if (strlen($sfnt_table_comp) >= strlen($sfnt_table_orig))
                    $sfnt_table_comp = $sfnt_table_orig;

                $woff_tables[$i] = array(
                    "tag"          => $sfnt_tables[$i]["tag"],
                    "offset"       => $woff_offset,
                    "compLength"   => strlen($sfnt_table_comp),
                    "origLength"   => strlen($sfnt_table_orig),
                    "calcChecksum" => $this->calc_checksum($sfnt_table_orig),
                    "origChecksum" => $sfnt_tables[$i]["checkSum"],
                    "tableData"    => $this->pad_data($sfnt_table_comp)
                );

                $woff_offset+= strlen(
                    $woff_tables[$i]["tableData"]
                );

                $sfnt_offset+= strlen(
                    $this->pad_data($sfnt_table_orig)
                );
            }

            if ($verify_checksums)
                $this->verify_checksums($woff_tables);

            $woff_meta_offset      = 0;
            $woff_meta_length      = 0;
            $woff_meta_orig_length = 0;
            $woff_priv_offset      = 0;
            $woff_priv_length      = 0;
            $woff_meta_comp        = null;
            $woff_priv_data        = null;

            if (!empty($this->woff_meta)) {
                $woff_meta_orig = $this->woff_meta["data"];

                $woff_meta_comp = $this->gz_compress(
                    $woff_meta_orig,
                    $compression_level
                );

                $woff_meta_offset = $this->pad_offset($woff_offset);
                $woff_meta_length = strlen($woff_meta_comp);
                $woff_meta_orig_length = strlen($woff_meta_orig);
                $woff_offset = $woff_meta_offset + $woff_meta_length;
            }

            if (!empty($this->woff_priv)) {
                $woff_priv_data = $this->woff_priv["data"];
                $woff_priv_offset = $this->pad_offset($woff_offset);
                $woff_priv_length = strlen($woff_priv_data);
                $woff_offset = $woff_priv_offset + $woff_priv_length;
            }

            $woff_export = $this->create_woff1_header(
                $woff_flavor,
                $woff_offset,
                $table_count,
                $sfnt_offset,
                $woff_meta_offset,
                $woff_meta_length,
                $woff_meta_orig_length,
                $woff_priv_offset,
                $woff_priv_length
            );

            $woff_export.= $this->create_woff1_directory(
                $woff_tables
            );

            $woff_export.= $this->create_woff1_tables(
                $woff_tables
            );

            if (isset($woff_meta_comp)) {
                $woff_export = $this->pad_data($woff_export);
                $woff_export.= $woff_meta_comp;
            }

            if (isset($woff_priv_data)) {
                $woff_export = $this->pad_data($woff_export);
                $woff_export.= $woff_priv_data;
            }

            return $woff_export;
        }

        public function woff2_export(
            $compression_level = -1,
            $verify_checksums = true
        ): string {
            $woff_flavor = $this->sfnt_offset["flavor"];
            $woff_tables = array();
            $sfnt_tables = $this->sort_tables_by_glyf($this->sfnt_tables);
            $table_count = count($sfnt_tables);
            $woff_tables_orig = "";

            $sfnt_offset = self::SFNT_OFFSET_SIZE + (
                $table_count * self::SFNT_ENTRY_SIZE
            );

            if ($woff_flavor === self::SFNT_FLAVOR_TTCF)
                throw new \UnexpectedValueException(
                    "WOFF2 export of font collections is not supported."
                );

            if (empty($sfnt_tables))
                throw new \LengthException(
                    "No SFNT data to export."
                );

            for ($i = 0; $i < $table_count; $i++) {
                $sfnt_table_orig = $sfnt_tables[$i]["tableData"];
                $woff_tables_orig.= $sfnt_table_orig;

                $woff_tables[$i] = array(
                    "tag"          => $sfnt_tables[$i]["tag"],
                    "origLength"   => strlen($sfnt_table_orig),
                    "calcChecksum" => $this->calc_checksum($sfnt_table_orig),
                    "origChecksum" => $sfnt_tables[$i]["checkSum"],
                );

                $sfnt_offset+= strlen(
                    $this->pad_data($sfnt_table_orig)
                );
            }

            if ($verify_checksums)
                $this->verify_checksums($woff_tables);

            $woff_tables_comp = $this->br_compress(
                $woff_tables_orig,
                $compression_level,
                BROTLI_FONT
            );

            $woff_directory = $this->create_woff2_directory(
                $woff_tables
            );

            $woff_directory_length = strlen($woff_directory);
            $woff_tables_comp_length = strlen($woff_tables_comp);

            $woff_offset = (
                self::WOFF2_HEADER_SIZE +
                $woff_directory_length +
                $woff_tables_comp_length
            );

            $woff_meta_offset      = 0;
            $woff_meta_length      = 0;
            $woff_meta_orig_length = 0;
            $woff_priv_offset      = 0;
            $woff_priv_length      = 0;
            $woff_meta_comp        = null;
            $woff_priv_data        = null;

            if (!empty($this->woff_meta)) {
                $woff_meta_orig = $this->woff_meta["data"];

                $woff_meta_comp = $this->br_compress(
                    $woff_meta_orig,
                    $compression_level,
                    BROTLI_TEXT
                );

                $woff_meta_offset = $this->pad_offset($woff_offset);
                $woff_meta_length = strlen($woff_meta_comp);
                $woff_meta_orig_length = strlen($woff_meta_orig);
                $woff_offset = $woff_meta_offset + $woff_meta_length;
            }

            if (!empty($this->woff_priv)) {
                $woff_priv_data = $this->woff_priv["data"];
                $woff_priv_offset = $this->pad_offset($woff_offset);
                $woff_priv_length = strlen($woff_priv_data);
                $woff_offset = $woff_priv_offset + $woff_priv_length;
            }

            $woff_export = $this->create_woff2_header(
                $woff_flavor,
                $woff_offset,
                $table_count,
                $sfnt_offset,
                $woff_tables_comp_length,
                $woff_meta_offset,
                $woff_meta_length,
                $woff_meta_orig_length,
                $woff_priv_offset,
                $woff_priv_length
            );

            $woff_export.= ($woff_directory.$woff_tables_comp);

            if (isset($woff_meta_comp)) {
                $woff_export = $this->pad_data($woff_export);
                $woff_export.= $woff_meta_comp;
            }

            if (isset($woff_priv_data)) {
                $woff_export = $this->pad_data($woff_export);
                $woff_export.= $woff_priv_data;
            }

            return $woff_export;
        }

        public function get_woff_version(
        ): array {
            return array(
                $this->version_major,
                $this->version_minor
            );
        }

        public function set_woff_version(
            $major,
            $minor
        ): void {
            if (!is_int($major))
                throw new \InvalidArgumentException(
                    "Major version must be an integer."
                );

            if (!is_int($minor))
                throw new \InvalidArgumentException(
                    "Minor version must be an integer."
                );

            if ($major < 0 or $major > 65535)
                throw new \RangeException(
                    "Major version must be in the range 0-65535."
                );

            if ($minor < 0 or $minor > 65535)
                throw new \RangeException(
                    "Minor version must be in the range 0-65535."
                );

            $this->version_major = $major;
            $this->version_minor = $minor;
        }

        public function get_woff_meta(
        ): object|false {
            return empty($this->woff_meta) ?
                false :
                $this->woff_meta["object"] ;
        }

        public function set_woff_meta(
            $object
        ): void {
            if (!$object instanceof \SimpleXMLElement)
                throw new \InvalidArgumentException(
                    "Extended metadata must be a SimpleXMLElement."
                );

            $xml = $object->asXML();

            if ($xml === false)
                throw new \UnexpectedValueException(
                    "SimpleXMLElement failed to return well-formed XML."
                );

            $this->woff_meta["data"] = $xml;
            $this->woff_meta["object"] = $object;
        }

        public function get_woff_priv(
        ): string|false {
            return empty($this->woff_priv) ?
                false :
                $this->woff_priv["data"] ;
        }

        public function set_woff_priv(
            $string
        ): void {
            if (!is_string($string))
                throw new \InvalidArgumentException(
                    "Private data block must be a string."
                );

            if (strlen($string) === 0)
                throw new \LengthException(
                    "Private data block cannot be zero length."
                );

            $this->woff_priv["data"] = $string;
        }

        private function gz_compress(
            $data,
            $compression_level
        ): string {
            if (!function_exists("gzcompress"))
                throw new \BadFunctionCallException(
                    "ZLIB compression support is required."
                );

            if (!is_int($compression_level))
                throw new \InvalidArgumentException(
                    "ZLIB compression level must be an integer."
                );

            if ($compression_level < 0 or $compression_level > 9)
                $compression_level = 6;

            $comp = gzcompress(
                $data,
                $compression_level,
                ZLIB_ENCODING_DEFLATE
            );

            if ($comp === false)
                throw new \UnexpectedValueException(
                    "ZLIB compression failed."
                );

            return $comp;
        }

        private function br_compress(
            $data,
            $compression_level,
            $compression_mode
        ): string {
            if (!function_exists("brotli_compress"))
                throw new \BadFunctionCallException(
                    "Brotli compression support is required."
                );

            if (!is_int($compression_level))
                throw new \InvalidArgumentException(
                    "Brotli compression level must be an integer."
                );

            if (!is_int($compression_mode))
                throw new \InvalidArgumentException(
                    "Brotli compression mode must be an integer."
                );

            if ($compression_level < 0 or $compression_level > 11)
                $compression_level = BROTLI_COMPRESS_LEVEL_DEFAULT;

            $comp = brotli_compress(
                $data,
                $compression_level,
                $compression_mode
            );

            if ($comp === false)
                throw new \UnexpectedValueException(
                    "Brotli compression failed."
                );

            return $comp;
        }

        private function pad_data(
            $data
        ): string {
            $length = strlen($data);
            $mod = $length % 4;

            if (!$mod)
                return $data;

            return str_pad(
                $data,
                $length + (4 - $mod),
                "\0",
                STR_PAD_RIGHT
            );
        }

        private function pad_offset(
            $offset
        ): int {
            $mod = $offset % 4;
            return ($mod) ? $offset + (4 - $mod) : $offset ;
        }

        private function calc_checksum(
            $data
        ): string {
            $data = $this->pad_data($data);
            $size = strlen($data) / 4;
            $sum = 0;

            for ($i = 0; $i < $size; $i++) {
                # Unpack a uint32 to be added to the sum.
                $add = unpack("N", $data, $i * 4);

                # Add to sum and simulate uint32 overflow.
                $sum = (($sum + $add[1]) & 0xffffffff);
            }

            return str_pad(dechex($sum), 8, "0", STR_PAD_LEFT);
        }

        private function verify_checksums(
            $tables
        ): void {
            foreach ($tables as $table) {
                $comp_checksum = $table["calcChecksum"];
                $orig_checksum = $table["origChecksum"];

                if ($table["tag"] == "head")
                    continue;

                if ($comp_checksum !== $orig_checksum)
                    throw new \UnexpectedValueException(
                        "Checksum mismatch in table data."
                    );
            }
        }

        private function base128_encode(
            $int
        ): string {
            $size = 1;
            $num = "";

            for ($len = $int; $len >= 128; $len >>= 7)
                $size++;

            for ($i = 0; $i < $size; $i++) {
                $byte = (($int >> (7 * ($size - $i - 1))) & 0x7f);

                if ($i < $size - 1)
                    $byte |= 0x80;


                $num.= chr($byte);
            }

            return $num;
        }

        private function sort_tables_by_tag(
            $tables
        ): array {
            usort($tables, function($a, $b) {
                return substr_compare($a["tag"], $b["tag"], 0);
            });

            return $tables;
        }

        private function sort_tables_by_offset(
            $tables
        ): array {
            usort($tables, function($a, $b) {
                if ($a["offset"] == $b["offset"])
                    return 0;

                return ($a["offset"] < $b["offset"]) ? -1 : 1 ;
            });

            return $tables;
        }

        private function sort_tables_by_glyf(
            $tables
        ): array {
            $tables_glyf_loca = array();
            $tables_other_tag = array();

            foreach ($tables as $table) {
                switch ($table["tag"]) {
                    case "glyf":
                    case "loca":
                        $tables_glyf_loca[] = $table;
                        break;

                    default:
                        $tables_other_tag[] = $table;
                }
            }

            return array_merge(
                $tables_glyf_loca,
                $tables_other_tag
            );
        }

        private function create_woff1_header(
            $woff_flavor,
            $woff_length,
            $woff_num_tables,
            $woff_total_sfnt_size,
            $woff_meta_offset,
            $woff_meta_length,
            $woff_meta_orig_length,
            $woff_priv_offset,
            $woff_priv_length
        ): string {
            return pack(
                "N1H8N1n1n1N1n1n1N1N1N1N1N1",
                self::WOFF1_SIGNATURE,
                $woff_flavor,
                $woff_length,
                $woff_num_tables,
                self::WOFF_RESERVED,
                $woff_total_sfnt_size,
                $this->version_major,
                $this->version_minor,
                $woff_meta_offset,
                $woff_meta_length,
                $woff_meta_orig_length,
                $woff_priv_offset,
                $woff_priv_length
            );
        }

        private function create_woff1_directory(
            $tables
        ): string {
            $tables = $this->sort_tables_by_tag($tables);
            $data = "";

            foreach ($tables as $table)
                $data.= pack(
                    "A4N1N1N1H8",
                    $table["tag"],
                    $table["offset"],
                    $table["compLength"],
                    $table["origLength"],
                    $table["origChecksum"]
                );

            return $data;
        }

        private function create_woff1_tables(
            $tables
        ): string {
            $tables = $this->sort_tables_by_offset($tables);
            $data = "";

            foreach ($tables as $table)
                $data.= $table["tableData"];

            return $data;
        }

        private function create_woff2_header(
            $woff_flavor,
            $woff_length,
            $woff_num_tables,
            $woff_total_sfnt_size,
            $woff_total_comp_size,
            $woff_meta_offset,
            $woff_meta_length,
            $woff_meta_orig_length,
            $woff_priv_offset,
            $woff_priv_length
        ): string {
            return pack(
                "N1H8N1n1n1N1N1n1n1N1N1N1N1N1",
                self::WOFF2_SIGNATURE,
                $woff_flavor,
                $woff_length,
                $woff_num_tables,
                self::WOFF_RESERVED,
                $woff_total_sfnt_size,
                $woff_total_comp_size,
                $this->version_major,
                $this->version_minor,
                $woff_meta_offset,
                $woff_meta_length,
                $woff_meta_orig_length,
                $woff_priv_offset,
                $woff_priv_length
            );
        }

        private function create_woff2_directory(
            $tables
        ): string {
            $data = "";

            foreach ($tables as $table) {
                $flags = self::WOFF2_KNOWN_TABLES[$table["tag"]] ?? 63 ;

                switch ($flags) {
                    case 63:
                        $data.= pack("C1A4", $flags, $table["tag"]);
                        break;

                    case 10:
                    case 11:
                        $data.= pack("C1", $flags | 0xC0);
                        break;

                    default:
                        $data.= pack("C1", $flags);
                }

                $data.= $this->base128_encode($table["origLength"]);
            }

            return $data;
        }
    }
