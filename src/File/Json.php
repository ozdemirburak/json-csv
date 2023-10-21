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
        $data = json_decode($this->data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON data.');
        }
        if ($this->isAssociativeArray($data) && !$this->containsArray($data)) {
            return $this->toCsvString([$data]);
        }
        $flattened = array_map([$this, 'flatten'], $data);
        $default = $this->getArrayOfNulls($flattened);
        $merged = array_map(
            function ($d) use ($default) {
                return array_merge($default, $d);
            },
            $flattened
        );
        return $this->toCsvString($merged);
    }

    /**
     * @param array $data
     * @return string
     */
    protected function toCsvString(array $data): string
    {
        $f = fopen('php://temp', 'wb');
        if ($this->conversion['utf8_encoding']) {
            fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF));
        }
        $this->putCsv($f, array_keys(current($data)));
        array_walk($data, function ($row) use ($f) {
            $this->putCsv($f, $row);
        });
        rewind($f);
        $csv = stream_get_contents($f);
        fclose($f);
        return ! \is_bool($csv) ? $csv : '';
    }

    /**
     * @param array $array
     * @param string $prefix
     * @param array  $result
     * @return array
     */
    protected function flatten(array $array = [], string $prefix = '', array $result = []): array
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
     * @param array $flattened
     * @return array
     */
    protected function getArrayOfNulls(array $flattened): array
    {
        $flattened = array_values($flattened);
        $keys = array_keys(array_merge(...$flattened));
        return array_fill_keys($keys, $this->conversion['null']);
    }

    /**
     * @param resource $handle
     * @param array    $fields
     * @return bool|int
     */
    private function putCsv($handle, array $fields)
    {
        return fputcsv(
            $handle,
            $fields,
            $this->conversion['delimiter'],
            $this->conversion['enclosure'],
            $this->conversion['escape']
        );
    }

    /**
     * @param array $data
     * @return bool
     */
    private function isAssociativeArray(array $data): bool
    {
        return array_keys($data) !== range(0, count($data) - 1);
    }

    /**
     * Check if the file/data contains nested arrays
     *
     * @param $array
     *
     * @return bool
     */
    private function containsArray(array $array): bool
    {
        foreach ($array as $data) {
            if (is_iterable($data)) {
                foreach ($data as $d) {
                    if (is_array($d)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
}
