<?php

namespace OzdemirBurak\JsonCsv\File;

use OzdemirBurak\JsonCsv\AbstractFile;

class Csv extends AbstractFile
{
    /**
     * @var array
     */
    protected $conversion = ['extension' => 'json', 'type' => 'application/json', 'options' => 0, 'delimiter' => ',', 'enclosure' => '"', 'escape' => '\\'];

    /**
     * @return string
     */
    public function convert() : string
    {
        $data = explode("\n", $this->data);
        $keys = str_getcsv(array_shift($data), $this->conversion['delimiter'], $this->conversion['enclosure'], $this->conversion['escape']);
        return json_encode(array_map(function ($line) use ($keys) {
            return array_combine($keys, str_getcsv($line, $this->conversion['delimiter'], $this->conversion['enclosure'], $this->conversion['escape']));
        }, $data), $this->conversion['options']);
    }
}
