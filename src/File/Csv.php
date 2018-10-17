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
        'escape' => '\\'
    ];

    /**
     * @return string
     */
    public function convert(): string
    {
        $data = $this->parseData();
        $keys = $this->parseCsv(array_shift($data));
        return json_encode(array_map(function ($line) use ($keys) {
            return array_combine($keys, $this->parseCsv($line));
        }, $data), $this->conversion['options']);
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
