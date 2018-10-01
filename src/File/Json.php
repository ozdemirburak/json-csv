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
        $data_decoded = json_decode($this->data, true);
        $data_flattened = array_map(function ($d) {
            return $this->flatten($d);
        }, $data_decoded);

        $keys = [];
        foreach ($data_flattened as $entry_flattened) {
            foreach ($entry_flattened as $key => $value) {
                $keys[$key] = true;
            }
        }

        $data_unified = [];
        foreach ($data_flattened as $entry_flattened) {
            $entry_unified = [];
            foreach ($keys as $key => $foo) {
                $entry_unified[$key] = array_key_exists($key, $entry_flattened) ? $entry_flattened[$key] : null;
            }
            $data_unified[] = $entry_unified;
        }

        return $this->toCsvString($data_unified);
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
