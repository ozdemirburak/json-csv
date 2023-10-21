<?php

namespace OzdemirBurak\JsonCsv\Tests\Traits;

use OzdemirBurak\JsonCsv\File\Csv;
use OzdemirBurak\JsonCsv\File\Json;

trait TestTrait
{
    /**
     * @param string $file
     */
    private function checkConversion($file)
    {
        $method = 'init' . str_replace('Test', '', substr(strrchr(\get_class($this), '\\'), 1));
        $this->assertStringEqualsFile($this->path($file, $this->ext), $this->$method($file)->convert());
    }

    /**
     * @param        $file
     * @param string $extension
     *
     * @return string
     */
    private function path($file, $extension = 'csv'): string
    {
        return __DIR__ . '/../data/' . $file . '.' . $extension;
    }

    /**
     * @param string $file
     *
     * @return \OzdemirBurak\JsonCsv\File\Csv
     */
    private function initCsv($file = 'iris'): Csv
    {
        if ($file !== null) {
            return new Csv($this->path($file, 'csv'));
        }
        return new Csv();
    }

    /**
     * @param string|null $file
     *
     * @return \OzdemirBurak\JsonCsv\File\Json
     */
    private function initJson($file = 'countries'): Json
    {
        if ($file !== null) {
            return new Json($this->path($file, 'json'));
        }
        return new Json();
    }
}
