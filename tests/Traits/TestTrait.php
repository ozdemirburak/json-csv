<?php

namespace OzdemirBurak\JsonCsv\Tests\Traits;

use OzdemirBurak\JsonCsv\File\Csv;
use OzdemirBurak\JsonCsv\File\Json;

trait TestTrait
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $this->tempFiles = [];
    }

    private function tempPath(string $extension): string
    {
        $path = sys_get_temp_dir() . '/json-csv-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $this->tempFiles[] = $path;
        return $path;
    }

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
