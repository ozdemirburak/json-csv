<?php

namespace OzdemirBurak\JsonCsv\Tests;

use OzdemirBurak\JsonCsv\File\Json;
use PHPUnit\Framework\TestCase;

class JsonTest extends TestCase
{
    /**
     * @group json-basic-test
     */
    public function testFileReading()
    {
        $this->assertEquals('countries', ($json = $this->init())->getFilename());
        $this->assertContains('"common": "Turkey"', $json->getData());
    }

    /**
     * @group json-conversion-test
     */
    public function testConversion()
    {
        $this->assertContains("name_common,name_official,name_native,area,latlng_0,latlng_1\n", $this->init()->convert());
    }

    /**
     * @group json-conversion-test
     */
    public function testConversionAndSave()
    {
        $this->init()->convertAndSave($path = __DIR__ . '/data/countries.csv');
        $this->assertFileExists($path);
        $this->assertContains("Turkey,\"Republic of Turkey\",Türkiye,783562,39,35\n", file_get_contents($path));
        unlink($path);
        $this->assertFileNotExists($path);
    }

    /**
     * @group json-conversion-test
     */
    public function testConversionAndDownload()
    {
        $this->init()->convertAndDownload(null, false);
        $this->expectOutputRegex('/Turkey,"Republic of Turkey",Türkiye,783562,39,35\\n/');
    }

    /**
     * @return \OzdemirBurak\JsonCsv\File\Json
     */
    private function init() : Json
    {
        return new Json(__DIR__ . '/data/countries.json');
    }
}
