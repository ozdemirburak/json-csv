<?php

namespace OzdemirBurak\JsonCsv\Tests;


use OzdemirBurak\JsonCsv\File\Json;
use PHPUnit\Framework\TestCase;

class JsonComplexTest extends TestCase
{
    const PATH_TO_CSV = __DIR__ . '/data/complex.csv';

    /**
     * @group json-basic-test
     */
    public function testFileReading()
    {
        $this->assertEquals('complex', ($json = $this->init())->getFilename());
        $this->assertNotEmpty($json->getData());
    }

    /**
     * @group json-conversion-test
     */
    public function testConversion()
    {
        $this->assertStringEqualsFile( self::PATH_TO_CSV, $this->init()->convert());
    }

    /**
     * @group json-conversion-test
     */
    public function testConversionAndSave()
    {
        $this->init()->convertAndSave($path = __DIR__ . '/data/complex_tmp.csv');
        $this->assertFileExists($path);
        $this->assertFileEquals( self::PATH_TO_CSV, $path);
        unlink($path);
        $this->assertFileNotExists($path);
    }

    /**
     * @group json-conversion-test
     */
    public function testConversionAndDownload()
    {
        $this->init()->convertAndDownload(null, false);
        $expectedContent = file_get_contents( self::PATH_TO_CSV );
        $this->expectOutputString($expectedContent);
    }

    /**
     * @return \OzdemirBurak\JsonCsv\File\Json
     */
    private function init() : Json
    {
        return new Json(__DIR__ . '/data/complex.json');
    }
}