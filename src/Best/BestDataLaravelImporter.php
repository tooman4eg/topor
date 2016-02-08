<?php namespace Topor\Best;

abstract class DataLaravelImporter
{
    protected $current_rows_chunk = [];
    protected $current_row = [];
    protected $rows_imported = 0;

    /**
     * @var Chunk number from BEST server
     */
    public $current_best_chunk;
    public $is_last_best_chunk;

    abstract function getTableName();
    abstract function convertRow($row);

    function importFile($file)
    {
        $this->current_rows_chunk = [];
        $this->current_row = [];
        $this->rows_imported = 0;

        ini_set('memory_limit', '512M');
        gc_collect_cycles();

        $xml_parser = xml_parser_create("UTF-8");

        xml_set_element_handler(
            $xml_parser,
            [$this, "startElement"],
            [$this, "endElement"]
        );

        if (!($fp = fopen($file, "r"))) {
            throw new \Exception("Невозможно произвести чтение XML");
        }

        while ($data = fread($fp, 4096 * 50)) {
            if (!xml_parse($xml_parser, $data, feof($fp))) {
                throw new \Exception(sprintf(
                "Ошибка XML: %s [%d:%d] in:\n%s",
                xml_error_string(xml_get_error_code($xml_parser)),
                xml_get_current_line_number($xml_parser),
                xml_get_current_column_number($xml_parser),
                $data
            ));
            }

            \DB::table($this->getTableName())->insert($this->current_rows_chunk);
            $this->rows_imported += count($this->current_rows_chunk);
            $this->current_rows_chunk = [];
        }
        xml_parser_free($xml_parser);
        if (!$this->rows_imported) {
            throw new \Exception('Can\'t import any item from file '.$file);
        }
        return $this->rows_imported;
    }

    function truncate()
    {
        \DB::table($this->getTableName())->truncate();
        return $this;
    }

    function importString($string)
    {
        $this->current_rows_chunk = [];
        $this->current_row = [];
        $this->rows_imported = 0;

        ini_set('memory_limit', '512M');
        gc_collect_cycles();

        $xml_parser = xml_parser_create("UTF-8");

        xml_set_element_handler(
            $xml_parser,
            [$this, "startElement"],
            [$this, "endElement"]
        );

        if (!xml_parse($xml_parser, $string)) {
            throw new \Exception(sprintf(
                "Ошибка XML: %s [%d:%d] in:\n%s",
                xml_error_string(xml_get_error_code($xml_parser)),
                xml_get_current_line_number($xml_parser),
                xml_get_current_column_number($xml_parser),
                $string
            ));
        }

        \DB::table($this->getTableName())->insert($this->current_rows_chunk);
        $this->rows_imported += count($this->current_rows_chunk);
        $this->current_rows_chunk = [];

        xml_parser_free($xml_parser);
        return $this->rows_imported;
    }

    protected function startElement($parser, $name, $attrs)
    {
        if ('COLUMN' == $name) {
            $this->current_row[$attrs['COL']] = $attrs['VALUE'];
        }

        if ('DATA-REPLY' == $name) {
            $this->current_best_chunk = $attrs['CHUNK-NUMBER'];
            $this->is_last_best_chunk = $attrs['LAST-CHUNK'];
        }
    }

    protected function endElement($parser, $name)
    {
        if ('ROW' == $name) {
            if ($row = $this->convertRow($this->current_row)) {
                $this->current_rows_chunk[] = $row;
            }
            $this->current_row = [];
        }
    }
}
