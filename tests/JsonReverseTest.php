<?php

namespace OzdemirBurak\JsonCsv\Tests;

use OzdemirBurak\JsonCsv\Tests\Traits\TestTrait;
use PHPUnit\Framework\TestCase;

class JsonReverseTest extends TestCase
{
    use TestTrait;

    /**
     * @param string $file
     * @param string $join
     */
    private function checkReverseConversion($file, $join = '_')
    {
        $pathCsvOut = $this->path($file . '.out', 'csv');
        $jsonConverter = $this->initJson($file);
        $jsonConverter->setConversionKey('join', $join);
        $jsonConverter->convertAndSave($pathCsvOut);
        $csvConverter = $this->initCsv($file . '.out');
        $csvConverter->setConversionKey('join', $join);
        $csvConverter->setConversionKey('numbers', 'numbers');
        $csvConverter->convertAndSave($pathJsonOut = $this->path($file . '.out', 'json'));
        try {
            $this->assertJsonFileEqualsJsonFile($this->path($file, 'json'), $pathJsonOut);
        } finally {
            unlink($pathCsvOut);
            $this->assertFileNotExists($pathCsvOut);
            unlink($pathJsonOut);
            $this->assertFileNotExists($pathJsonOut);
        }
    }

    /**
     * @group json-conversion-test
     */
    public function testWhatever()
    {
        $this->checkReverseConversion('example');
    }

    /**
     * @group json-conversion-test
     */
    public function testPeople()
    {
        $this->checkReverseConversion('people', '-');
    }

    /**
     * @group json-conversion-test
     */
    public function testProperties()
    {
        $this->checkReverseConversion('properties');
    }

    /**
     * @TODO resolve issues which cause this test to fail
     * @group json-conversion-failing-test
     */
    public function testStats()
    {
        $this->markTestSkipped();
        $this->checkReverseConversion('stats');
    }
}
