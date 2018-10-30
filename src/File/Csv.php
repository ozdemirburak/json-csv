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

        $jsonObjects = array_map(function ($line) use ($splitKeys) {
            $values = $this->parseCsv($line);
            $jsonObject = [];
            for ($valueIndex = 0; $valueIndex < count($values); $valueIndex++) {
                if ($values[$valueIndex] == "") {
                    continue;
                }
                $this->setJsonValue($splitKeys[$valueIndex], 0, $jsonObject, $values[$valueIndex]);
            }
            return $jsonObject;
        }, $data);

        return json_encode($jsonObjects, $this->conversion['options']);
    }

    private function setJsonValue($splitKey, $splitKeyIndex, &$jsonObject, $value)
    {
        $keyPart = $splitKey[$splitKeyIndex];

        if (count($splitKey) > $splitKeyIndex+1) {
            if (!array_key_exists($keyPart, $jsonObject)) {
                $jsonObject[$keyPart] = [];
            }
            $this->setJsonValue($splitKey, $splitKeyIndex+1, $jsonObject[$keyPart], $value);
        } else {
            if ($this->conversion['numbers'] == 'numbers' && is_numeric($value)) {
                $value = 0 + $value;
            }
            $jsonObject[$keyPart] = $value;
        }
    }

    private function parseCsv($line)
    {
        return str_getcsv(
            $line,
            $this->conversion['delimiter'],
            $this->conversion['enclosure'],
            $this->conversion['escape']
        );
    }

    private function parseData()
    {
        $data = explode("\n", $this->data);
        if (end($data) === '') {
            array_pop($data);
        }
        return $data;
    }
}
