<?php

namespace OzdemirBurak\JsonCsv\File;

use OzdemirBurak\JsonCsv\AbstractFile;

class Csv extends AbstractFile
{
    /**
     * @var array
     */
    protected $conversion = [
        'extension' => 'json',
        'type' => 'application/json',
        'options' => 0,
        'delimiter' => ',',
        'enclosure' => '"',
        'escape' => '\\',
        'join' => '_',
        'numbers' => 'strings'
    ];

    /**
     * Converts CSV data to JSON.
     *
     * @return string JSON representation of CSV data.
     */
    public function convert(): string
    {
        $data = $this->parseData();
        $keys = $this->parseCsv(array_shift($data));
        $splitKeys = $this->splitKeys($keys);
        $jsonObjects = array_map([$this, 'convertLineToJson'], $data, array_fill(0, count($data), $splitKeys));
        $json = json_encode($jsonObjects, $this->conversion['options']);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('JSON encoding failed: ' . json_last_error_msg());
        }
        return $json;
    }

    /**
     * Splits keys based on the configured join delimiter.
     *
     * @param array $keys
     * @return array
     */
    private function splitKeys(array $keys): array
    {
        return array_map(function ($key) {
            return explode($this->conversion['join'], $key);
        }, $keys);
    }

    /**
     * Converts a CSV line to a JSON object.
     *
     * @param string $line
     * @param array $splitKeys
     * @return array
     */
    private function convertLineToJson(string $line, array $splitKeys): array
    {
        return $this->getJsonObject($this->parseCsv($line), $splitKeys);
    }

    /**
     * Creates a JSON object from a CSV line.
     *
     * @param array $values CSV values.
     * @param array $splitKeys Split keys.
     * @return array JSON object.
     */
    private function getJsonObject(array $values, array $splitKeys): array
    {
        $jsonObject = [];
        for ($valueIndex = 0, $count = count($values); $valueIndex < $count; $valueIndex++) {
            if ($values[$valueIndex] === '') {
                continue;
            }
            $this->setJsonValue($splitKeys[$valueIndex], 0, $jsonObject, $values[$valueIndex]);
        }
        return $jsonObject;
    }

    /**
     * Sets a value in a JSON object.
     *
     * @param array $splitKey Split key.
     * @param int   $splitKeyIndex Split key index.
     * @param array $jsonObject JSON object.
     * @param mixed $value Value.
     */
    private function setJsonValue(array $splitKey, int $splitKeyIndex, array &$jsonObject, $value): void
    {
        $keyPart = $splitKey[$splitKeyIndex];
        if (count($splitKey) > $splitKeyIndex + 1) {
            if (!array_key_exists($keyPart, $jsonObject)) {
                $jsonObject[$keyPart] = [];
            }
            $this->setJsonValue($splitKey, $splitKeyIndex + 1, $jsonObject[$keyPart], $value);
        } else {
            if (is_numeric($value) && $this->conversion['numbers'] === 'numbers') {
                $value = 0 + $value;
            }
            $jsonObject[$keyPart] = $value;
        }
    }

    /**
     * Parses a CSV line.
     *
     * @param string $line CSV line.
     * @return array Parsed CSV line.
     */
    private function parseCsv(string $line): array
    {
        return str_getcsv(
            $line,
            $this->conversion['delimiter'],
            $this->conversion['enclosure'],
            $this->conversion['escape']
        );
    }

    /**
     * Parses CSV data.
     *
     * @return array Parsed CSV data.
     */
    private function parseData(): array
    {
        $data = explode("\n", $this->data);
        if (end($data) === '') {
            array_pop($data);
        }
        return $data;
    }
}
