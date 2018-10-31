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
     * @return string
     */
    public function convert(): string
    {
        $data = $this->parseData();
        $keys = $this->parseCsv(array_shift($data));
        $splitKeys = array_map(function ($key) {
            return explode($this->conversion['join'], $key);
        }, $keys);
        return json_encode(array_map(function ($line) use ($splitKeys) {
            return $this->getJsonObject($line, $splitKeys);
        }, $data), $this->conversion['options']);
    }

    /**
     * @param       $line
     * @param       $splitKeys
     * @param array $jsonObject
     *
     * @return array
     */
    private function getJsonObject($line, $splitKeys, array $jsonObject = []): array
    {
        $values = $this->parseCsv($line);
        for ($valueIndex = 0, $count = \count($values); $valueIndex < $count; $valueIndex++) {
            if ($values[$valueIndex] === '') {
                continue;
            }
            $this->setJsonValue($splitKeys[$valueIndex], 0, $jsonObject, $values[$valueIndex]);
        }
        return $jsonObject;
    }

    /**
     * @param $splitKey
     * @param $splitKeyIndex
     * @param $jsonObject
     * @param $value
     */
    private function setJsonValue($splitKey, $splitKeyIndex, &$jsonObject, $value): void
    {
        $keyPart = $splitKey[$splitKeyIndex];
        if (\count($splitKey) > $splitKeyIndex + 1) {
            if (!array_key_exists($keyPart, $jsonObject)) {
                $jsonObject[$keyPart] = [];
            }
            $this->setJsonValue($splitKey, $splitKeyIndex+1, $jsonObject[$keyPart], $value);
        } else {
            if (is_numeric($value) && $this->conversion['numbers'] === 'numbers') {
                $value = 0 + $value;
            }
            $jsonObject[$keyPart] = $value;
        }
    }

    /**
     * @param $line
     *
     * @return array
     */
    private function parseCsv($line): array
    {
        return str_getcsv(
            $line,
            $this->conversion['delimiter'],
            $this->conversion['enclosure'],
            $this->conversion['escape']
        );
    }

    /**
     * @return array
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
