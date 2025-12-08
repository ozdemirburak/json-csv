<?php

namespace OzdemirBurak\JsonCsv\File;

use OzdemirBurak\JsonCsv\AbstractFile;
use RuntimeException;

class Json extends AbstractFile
{
    protected array $conversion = [
        'extension' => 'csv',
        'type' => 'text/csv',
        'delimiter' => ',',
        'enclosure' => '"',
        'escape' => '\\',
        'join' => '_',
        'null' => null,
        'utf8_encoding' => false,
    ];

    public function convert(): string
    {
        $data = json_decode($this->data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON data: ' . json_last_error_msg());
        }
        if ($data === null || $data === []) {
            return '';
        }
        // Handle single object (associative array without nested arrays of objects)
        if ($this->isAssociativeArray($data) && !$this->containsArrayOfObjects($data)) {
            return $this->toCsvString([$this->flatten($data)]);
        }
        // Handle array of scalars (not objects) - convert to single row with indexed keys
        if (!$this->isAssociativeArray($data) && !$this->containsObjects($data)) {
            return $this->toCsvString([$this->flatten($data)]);
        }
        // Handle array of objects
        $flattened = array_map($this->flatten(...), $data);
        if ($flattened === []) {
            return '';
        }
        $default = $this->getArrayOfNulls($flattened);
        $merged = array_map(
            static fn(array $d): array => array_merge($default, $d),
            $flattened
        );
        return $this->toCsvString($merged);
    }

    protected function toCsvString(array $data): string
    {
        if ($data === [] || current($data) === false) {
            return '';
        }
        $f = fopen('php://temp', 'wb');
        if ($f === false) {
            throw new RuntimeException('Failed to open temporary stream');
        }
        if ($this->conversion['utf8_encoding']) {
            fwrite($f, "\xEF\xBB\xBF");
        }
        $this->putCsv($f, array_keys(current($data)));
        foreach ($data as $row) {
            $this->putCsv($f, $row);
        }
        rewind($f);
        $csv = stream_get_contents($f);
        fclose($f);
        return $csv !== false ? $csv : '';
    }

    protected function flatten(array $array, string $prefix = '', array $result = []): array
    {
        foreach ($array as $key => $value) {
            $newKey = $prefix . $key;
            if (is_array($value)) {
                $result = $this->flatten($value, $newKey . $this->conversion['join'], $result);
            } else {
                $result[$newKey] = $value;
            }
        }
        return $result;
    }

    protected function getArrayOfNulls(array $flattened): array
    {
        if ($flattened === []) {
            return [];
        }
        $flattened = array_values($flattened);
        $keys = array_keys(array_merge(...$flattened));
        return array_fill_keys($keys, $this->conversion['null']);
    }

    /**
     * @param resource $handle
     */
    private function putCsv($handle, array $fields): int|false
    {
        return fputcsv(
            $handle,
            $fields,
            $this->conversion['delimiter'],
            $this->conversion['enclosure'],
            $this->conversion['escape']
        );
    }

    private function isAssociativeArray(array $data): bool
    {
        if ($data === []) {
            return false;
        }
        return array_keys($data) !== range(0, count($data) - 1);
    }

    private function containsArrayOfObjects(array $array): bool
    {
        foreach ($array as $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    // Check if it's an array containing objects (arrays)
                    if (is_array($item)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function containsObjects(array $array): bool
    {
        foreach ($array as $value) {
            if (is_array($value)) {
                return true;
            }
        }
        return false;
    }
}
