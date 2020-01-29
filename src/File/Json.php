<?php

namespace OzdemirBurak\JsonCsv\File;

use OzdemirBurak\JsonCsv\AbstractFile;

class Json extends AbstractFile
{
    /**
     * @var array
     */
    protected $conversion = [
        'extension' => 'csv',
        'type' => 'text/csv',
        'delimiter' => ',',
        'enclosure' => '"',
        'escape' => '\\',
        'join' => '_',
        'null' => null,
        'utf8_encoding' => false
    ];

    /**
     * @return string
     */
    public function convert(): string
    {
        $flattened = array_map(function ($d) {
            return $this->flatten($d);
        }, json_decode($this->data, true));
        // create an array with all of the keys where each has a null value
        $default = $this->getArrayOfNulls($flattened);
        // merge default with the actual data so that non existent keys will have null values
        return $this->toCsvString(array_map(function ($d) use ($default) {
            return array_merge($default, $d);
        }, $flattened));
    }

    /**
     * @param array $data
     *
     * @return string
     */
    protected function toCsvString(array $data): string
    {
        $f = fopen('php://temp', 'wb');
        if ($this->conversion['utf8_encoding']) {
            fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF));
        }
        $this->putCsv($f, array_keys(current($data)));
        array_walk($data, function ($row) use (&$f) {
            $this->putCsv($f, $row);
        });
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
    protected function flatten(array $array = [], $prefix = '', array $result = []): array
    {
        foreach ($array as $key => $value) {
            if (\is_array($value)) {
                $result = array_merge($result, $this->flatten($value, $prefix . $key . $this->conversion['join']));
            } else {
                $result[$prefix . $key] = $value;
            }
        }
        return $result;
    }

    /**
     * @param $flattened
     *
     * @return array
     */
    protected function getArrayOfNulls($flattened): array
    {
        $flattened = array_values($flattened);
        $keys = array_keys(array_merge(...$flattened));
        return array_fill_keys($keys, $this->conversion['null']);
    }

    /**
     * @param $handle
     * @param $fields
     *
     * @return bool|int
     */
    private function putCsv($handle, $fields)
    {
        return fputcsv(
            $handle,
            $fields,
            $this->conversion['delimiter'],
            $this->conversion['enclosure'],
            $this->conversion['escape']
        );
    }
}
