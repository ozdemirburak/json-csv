<?php

namespace OzdemirBurak\JsonCsv\Tests\Traits;

use OzdemirBurak\JsonCsv\File\Csv;
use OzdemirBurak\JsonCsv\File\Json;

trait TestTrait
{
    private function checkConversion(string $file): void
    {
        $method = 'init' . str_replace('Test', '', substr(strrchr(static::class, '\\'), 1));
        $this->assertStringEqualsFile($this->path($file, $this->ext), $this->$method($file)->convert());
    }

    private function path(string $file, string $extension = 'csv'): string
    {
        return __DIR__ . '/../data/' . $file . '.' . $extension;
    }

    private function initCsv(?string $file = 'iris'): Csv
    {
        if ($file !== null) {
            return new Csv($this->path($file, 'csv'));
        }

        return new Csv();
    }

    private function initJson(?string $file = 'countries'): Json
    {
        if ($file !== null) {
            return new Json($this->path($file, 'json'));
        }

        return new Json();
    }
}
