<?php
    class sfnt2woff {
        const VERSION_MAJOR          = 0;
        const VERSION_MINOR          = 1;

        const SIZEOF_SFNT_OFFSET     = 12;
        const SIZEOF_SFNT_ENTRY      = 16;
        const SIZEOF_WOFF_HEADER     = 44;
        const SIZEOF_WOFF_ENTRY      = 20;

        const WOFF_SIGNATURE         = 0x774F4646;

        public $strict               = true;

        private $sfnt_offset         = array();
        private $sfnt_tables         = array();
        private $woff_tables         = array();

        private $woff_flavor         = 0;
        private $woff_length         = 0;
        private $woff_numtables      = 0;
        private $woff_totalsfntsize  = 0;
        private $woff_metaoffset     = 0;
        private $woff_metalength     = 0;
        private $woff_metaoriglength = 0;
        private $woff_privoffset     = 0;
        private $woff_privlength     = 0;

        public function import($sfnt) {
            if (self::SIZEOF_SFNT_OFFSET > strlen($sfnt))
                throw new Exception("File is invalid.");

            $sfnt_offset = unpack("H8flavor/nnumTables", $sfnt);
            $sfnt_tables = array();
            $table_count = $sfnt_offset["numTables"];

            for ($i = 0; $i < $table_count; $i++) {
                $offset = self::SIZEOF_SFNT_OFFSET + ($i * self::SIZEOF_SFNT_ENTRY);

                if (($offset + self::SIZEOF_SFNT_ENTRY) > strlen($sfnt))
                    throw new Exception("File ended unexpectedly.");

                $sfnt_tables[$i] = unpack("a4tag/H8checkSum/H8offset/H8length", $sfnt, $offset);
                $sfnt_tables[$i]["offset"] = hexdec($sfnt_tables[$i]["offset"]);
                $sfnt_tables[$i]["length"] = hexdec($sfnt_tables[$i]["length"]);

                $sfnt_tables[$i]["tableData"] = substr($sfnt,
                                                       $sfnt_tables[$i]["offset"],
                                                       $sfnt_tables[$i]["length"]);
            }

            $this->sfnt_offset = $sfnt_offset;
            $this->sfnt_tables = $sfnt_tables;
            $this->woff_tables = array();
        }

        public function export() {
            $woff_export = "";
            $woff_tables = array();
            $sfnt_tables = $this->sort_tables_by_offset($this->sfnt_tables);
            $table_count = count($sfnt_tables);
            $woff_offset = self::SIZEOF_WOFF_HEADER + ($table_count * self::SIZEOF_WOFF_ENTRY);
            $sfnt_offset = self::SIZEOF_SFNT_OFFSET + ($table_count * self::SIZEOF_SFNT_ENTRY);

            for ($i = 0; $i < $table_count; $i++) {
                $sfnt_orig = $sfnt_tables[$i]["tableData"];
                $sfnt_comp = $this->compress($sfnt_orig);

                if (strlen($sfnt_comp) >= strlen($sfnt_orig))
                    $sfnt_comp = $sfnt_orig;

                $woff_tables[$i] = array(
                    "tag"          => $sfnt_tables[$i]["tag"],
                    "offset"       => $woff_offset,
                    "compLength"   => strlen($sfnt_comp),
                    "origLength"   => strlen($sfnt_orig),
                    "calcChecksum" => $this->calc_checksum($sfnt_orig),
                    "origChecksum" => $sfnt_tables[$i]["checkSum"],
                    "tableData"    => $this->pad_table($sfnt_comp)
                );

                $woff_offset = $woff_offset + strlen($woff_tables[$i]["tableData"]);
                $sfnt_offset = $sfnt_offset + strlen($this->pad_table($sfnt_orig));
            }

            if ($this->strict)
                $this->test_integrity($woff_tables);

            $this->woff_tables         = $woff_tables;
            $this->woff_flavor         = $this->sfnt_offset["flavor"];
            $this->woff_length         = $woff_offset;
            $this->woff_numtables      = $table_count;
            $this->woff_totalsfntsize  = $sfnt_offset;
            $this->woff_metaoffset     = 0;
            $this->woff_metaoriglength = 0;
            $this->woff_privoffset     = 0;
            $this->woff_privlength     = 0;

            $this->append_woff_header($woff_export);
            $this->append_woff_directory($woff_export);
            $this->append_woff_tables($woff_export);

            return $woff_export;
        }

        public function get_sfnt_metadata() {
            $tables = $this->sfnt_tables;

            foreach ($tables as &$table)
                unset($table["tableData"]);

            return $tables;
        }

        public function get_woff_metadata() {
            $tables = $this->woff_tables;

            foreach ($tables as &$table)
                unset($table["tableData"]);

            return $tables;
        }

        private function compress($data) {
            return gzcompress($data, 6, ZLIB_ENCODING_DEFLATE);
        }

        private function pad_table($data) {
            return str_pad($data, (ceil(strlen($data) / 4) * 4), "\0", STR_PAD_RIGHT);
        }

        private function calc_checksum($data) {
            $data = $this->pad_table($data);
            $size = ceil(strlen($data) / 4);
            $sum = 0;

            for ($i = 0; $i < $size; $i++) {
                $array = unpack("Nunit", $data, $i * 4);
                $sum = $sum + $array["unit"];
            }

            return str_pad(dechex($sum), 8, "0", STR_PAD_LEFT);
        }

        private function test_integrity($tables) {
            foreach ($tables as $table) {
                $comp_checksum = $table["calcChecksum"];
                $orig_checksum = $table["origChecksum"];

                if ($table["tag"] == "head")
                    continue;

                if ($comp_checksum !== $orig_checksum)
                    throw new Exception("Checksum mismatch in table data.");
            }
        }

        private function sort_tables_by_tag($tables) {
            usort($tables, function($a, $b) {
                return substr_compare($a["tag"], $b["tag"], 0);
            });
            return $tables;
        }

        private function sort_tables_by_offset($tables) {
            usort($tables, function($a, $b) {
                if ($a["offset"] == $b["offset"])
                    return 0;

                return ($a["offset"] < $b["offset"]) ? -1 : 1 ;
            });
            return $tables;
        }

        private function append_woff_header(&$data) {
            $data.= pack("N1H8N1n1n1N1n1n1N1N1N1N1N1",
                self::WOFF_SIGNATURE,
                $this->woff_flavor,
                $this->woff_length,
                $this->woff_numtables,
                0,
                $this->woff_totalsfntsize,
                self::VERSION_MAJOR,
                self::VERSION_MINOR,
                $this->woff_metaoffset,
                $this->woff_metalength,
                $this->woff_metaoriglength,
                $this->woff_privoffset,
                $this->woff_privlength
            );
        }

        private function append_woff_directory(&$data) {
            $woff_tables = $this->sort_tables_by_tag($this->woff_tables);

            foreach ($woff_tables as $woff_table)
                $data.= pack("a4N1N1N1H8",
                    $woff_table["tag"],
                    $woff_table["offset"],
                    $woff_table["compLength"],
                    $woff_table["origLength"],
                    $woff_table["origChecksum"]
                );
        }

        private function append_woff_tables(&$data) {
            $woff_tables = $this->sort_tables_by_offset($this->woff_tables);

            foreach ($woff_tables as $woff_table)
                $data.= $woff_table["tableData"];
        }
    }
