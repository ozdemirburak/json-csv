<?php

namespace OzdemirBurak\JsonCsv\File;

use OzdemirBurak\JsonCsv\AbstractFile;

class Json extends AbstractFile
{
    /**
     * @var array
     */
    protected $conversion = ['extension' => 'csv', 'type' => 'text/csv', 'delimiter' => ',', 'enclosure' => '"', 'escape' => '\\'];

    /**
     * @return string
     */
    public function convert() : string
    {
        return $this->toCsvString(array_map(function ($d) {
            return $this->flatten($d);
        }, json_decode($this->data, true)));
    }

    /**
     * @param array $data
     *
     * @return string
     */
    protected function toCsvString(array $data) : string
    {
        $f = fopen('php://temp', 'w');
        fputcsv($f, array_keys(current($data)), $this->conversion['delimiter'], $this->conversion['enclosure'], $this->conversion['escape']);
        foreach ($data as $row) {
            fputcsv($f, $row, $this->conversion['delimiter'], $this->conversion['enclosure'], $this->conversion['escape']);
        }
        rewind($f);
        $csv = stream_get_contents($f);
        fclose($f);
        return ! \is_bool($csv) ? $csv : '';
    }

    /**
     * @param array  $array
     * @param string $prefix
     * @param array  $result
     *
     * @return array
     */
    protected function flatten(array $array = [], $prefix = '', array $result = []) : array
    {
        foreach ($array as $key => $value) {
            if (\is_array($value)) {
                $result = array_merge($result, $this->flatten($value, $prefix . $key . '_'));
            } else {
                $result[$prefix . $key] = $value;
            }
        }
        return $result;
    }
}
