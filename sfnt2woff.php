<?php
    namespace xenocrat;

    class sfnt2woff {
        const VERSION_MAJOR      = 4;
        const VERSION_MINOR      = 0;
        const VERSION_PATCH      = 0;

        const SIZEOF_SFNT_OFFSET = 12;
        const SIZEOF_SFNT_ENTRY  = 16;
        const SIZEOF_WOFF_HEADER = 44;
        const SIZEOF_WOFF_ENTRY  = 20;

        const WOFF_SIGNATURE     = 0x774F4646;
        const WOFF_RESERVED      = 0;

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

            if (self::SIZEOF_SFNT_OFFSET > $sfnt_length)
                throw new \RangeException(
                    "File does not contain SFNT data."
                );

            $sfnt_offset = unpack(
                "H8flavor/nnumTables",
                $sfnt
            );
            $table_count = $sfnt_offset["numTables"];

            for ($i = 0; $i < $table_count; $i++) {
                $offset = self::SIZEOF_SFNT_OFFSET + (
                    $i * self::SIZEOF_SFNT_ENTRY
                );
                $target = $offset + self::SIZEOF_SFNT_ENTRY;

                if ($target > $sfnt_length)
                    throw new \LengthException(
                        "File ended unexpectedly."
                    );

                $sfnt_tables[$i] = unpack(
                    "a4tag/H8checkSum/H8offset/H8length",
                    $sfnt,
                    $offset
                );
                $sfnt_tables[$i]["offset"] = hexdec($sfnt_tables[$i]["offset"]);
                $sfnt_tables[$i]["length"] = hexdec($sfnt_tables[$i]["length"]);
                $target = $sfnt_tables[$i]["offset"] + $sfnt_tables[$i]["length"];

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

        public function woff_export(
            $compression_level = -1,
            $test_integrity = true
        ): string {
            $woff_export = "";
            $woff_flavor = $this->sfnt_offset["flavor"];
            $woff_tables = array();
            $sfnt_tables = $this->sort_tables_by_offset($this->sfnt_tables);
            $table_count = count($sfnt_tables);
            $woff_offset = self::SIZEOF_WOFF_HEADER + (
                $table_count * self::SIZEOF_WOFF_ENTRY
            );
            $sfnt_offset = self::SIZEOF_SFNT_OFFSET + (
                $table_count * self::SIZEOF_SFNT_ENTRY
            );

            if (empty($sfnt_tables))
                throw new \LengthException(
                    "No SFNT data to export."
                );

            for ($i = 0; $i < $table_count; $i++) {
                $sfnt_orig = $sfnt_tables[$i]["tableData"];

                $sfnt_comp = $this->gz_compress(
                    $sfnt_orig,
                    $compression_level
                );

                if (strlen($sfnt_comp) >= strlen($sfnt_orig))
                    $sfnt_comp = $sfnt_orig;

                $woff_tables[$i] = array(
                    "tag"          => $sfnt_tables[$i]["tag"],
                    "offset"       => $woff_offset,
                    "compLength"   => strlen($sfnt_comp),
                    "origLength"   => strlen($sfnt_orig),
                    "calcChecksum" => $this->calc_checksum($sfnt_orig),
                    "origChecksum" => $sfnt_tables[$i]["checkSum"],
                    "tableData"    => $this->pad_data($sfnt_comp)
                );

                $woff_offset = $woff_offset + strlen(
                    $woff_tables[$i]["tableData"]
                );

                $sfnt_offset = $sfnt_offset + strlen(
                    $this->pad_data($sfnt_orig)
                );
            }

            if ($test_integrity)
                $this->test_integrity($woff_tables);

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

            $this->append_woff_header(
                $woff_export,
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

            $this->append_woff_directory($woff_export, $woff_tables);
            $this->append_woff_tables($woff_export, $woff_tables);
            $this->append_woff_meta($woff_export, $woff_meta_comp);
            $this->append_woff_priv($woff_export, $woff_priv_data);

            return $woff_export;
        }

        public function get_woff_meta(
        ): string|false {
            return empty($this->woff_meta) ?
                false :
                $this->woff_meta["data"] ;
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

            $this->version_major = $major;
            $this->version_minor = $minor;
        }

        public function get_woff_version(
        ): array {
            return array(
                $this->version_major,
                $this->version_minor
            );
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
                    "Extended metadata object failed to return XML."
                );

            $this->woff_meta["data"] = $xml;
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
                    "ZLIB support required."
                );

            if (!is_int($compression_level))
                throw new \InvalidArgumentException(
                    "Compression level must be an integer."
                );

            if ($compression_level < 1 or $compression_level > 9)
                $compression_level = 6;

            $comp = gzcompress(
                $data,
                $compression_level,
                ZLIB_ENCODING_DEFLATE
            );

            if ($data === false)
                throw new \UnexpectedValueException(
                    "ZLIB compression failed."
                );

            return $comp;
        }

        private function pad_data(
            $data
        ): string {
            return str_pad(
                $data,
                (ceil(strlen($data) / 4) * 4),
                "\0",
                STR_PAD_RIGHT
            );
        }

        private function pad_offset(
            $offset
        ): int {
            return ceil($offset / 4) * 4;
        }

        private function calc_checksum(
            $data
        ): string {
            $data = $this->pad_data($data);
            $size = ceil(strlen($data) / 4);
            $sum = 0;

            for ($i = 0; $i < $size; $i++) {
                # Unpack a uint32 to be added to the sum.
                $add = unpack("N", $data, $i * 4);

                # Add to sum and simulate uint32 overflow.
                $sum = (($sum + $add[1]) & 0xffffffff);
            }

            return str_pad(dechex($sum), 8, "0", STR_PAD_LEFT);
        }

        private function test_integrity(
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

        private function append_woff_header(
            &$data,
            $woff_flavor,
            $woff_length,
            $woff_num_tables,
            $woff_total_sfnt_size,
            $woff_meta_offset,
            $woff_meta_length,
            $woff_meta_orig_length,
            $woff_priv_offset,
            $woff_priv_length
        ): void {
            $data.= pack(
                "N1H8N1n1n1N1n1n1N1N1N1N1N1",
                self::WOFF_SIGNATURE,
                $woff_flavor,
                $woff_length,
                $woff_num_tables,
                self::WOFF_RESERVED,
                $woff_total_sfnt_size,
                (int) $this->version_major,
                (int) $this->version_minor,
                $woff_meta_offset,
                $woff_meta_length,
                $woff_meta_orig_length,
                $woff_priv_offset,
                $woff_priv_length
            );
        }

        private function append_woff_directory(
            &$data,
            $tables
        ): void {
            $tables = $this->sort_tables_by_tag($tables);

            foreach ($tables as $table)
                $data.= pack(
                    "a4N1N1N1H8",
                    $table["tag"],
                    $table["offset"],
                    $table["compLength"],
                    $table["origLength"],
                    $table["origChecksum"]
                );
        }

        private function append_woff_tables(
            &$data,
            $tables
        ): void {
            $tables = $this->sort_tables_by_offset($tables);

            foreach ($tables as $table)
                $data.= $table["tableData"];
        }

        private function append_woff_meta(
            &$data,
            $meta
        ): void {
            if (!isset($meta))
                return;

            $data = $this->pad_data($data);
            $data.= $meta;
        }

        private function append_woff_priv(
            &$data,
            $priv
        ): void {
            if (!isset($priv))
                return;

            $data = $this->pad_data($data);
            $data.= $priv;
        }
    }
