<?php

namespace OzdemirBurak\JsonCsv\Tests;

use OzdemirBurak\JsonCsv\Tests\Traits\TestTrait;
use PHPUnit\Framework\TestCase;

class JsonTest extends TestCase
{
    use TestTrait;

    /**
     * @var string
     */
    protected $ext =  'csv';

    /**
     * @group json-basic-test
     */
    public function testFileReading()
    {
        $this->assertEquals('countries', ($json = $this->initJson())->getFilename());
        $this->assertContains('"common": "Turkey"', $json->getData());
    }

    /**
     * @group json-conversion-test
     */
    public function testPeople()
    {
        $this->checkConversion('people');
    }

    /**
     * @group json-conversion-test
     */
    public function testProperties()
    {
        $this->checkConversion('properties');
    }

    /**
     * @group json-conversion-test
     */
    public function testStats()
    {
        $this->checkConversion('stats');
    }

    /**
     * @group json-conversion-download-save-test
     */
    public function testConversionAndDownload()
    {
        $this->initJson()->convertAndDownload(null, false);
        $this->expectOutputRegex('/Turkey,"Republic of Turkey",Türkiye,783562,39,35\\n/');
    }

    /**
     * @group json-conversion-download-save-test
     */
    public function testConversionAndSave()
    {
        $path = $this->path('iris', 'countries');
        $this->initJson()->convertAndSave($path);
        $this->assertFileExists($path);
        $this->assertContains("Turkey,\"Republic of Turkey\",Türkiye,783562,39,35\n", file_get_contents($path));
        unlink($path);
        $this->assertFileNotExists($path);
    }
}
