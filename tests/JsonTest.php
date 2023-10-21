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
     * @var string
     */
    protected $jsonString = '{"name": "Buddha", "age": 80}';

    /**
     * @group json-basic-test
     */
    public function testFileReading()
    {
        $this->assertEquals('countries', ($json = $this->initJson())->getFilename());
        $this->assertStringContainsString('"common": "Turkey"', $json->getData());
    }

    /**
     * @group json-basic-test
     */
    public function testSetter()
    {
        $conversion = $this->initJson()->setConversionKey('utf8_encoding', true);
        $this->assertEquals(true, $conversion['utf8_encoding']);
    }

    /**
     * @group json-basic-test
     */
    public function testFromString()
    {
        $json = $this->initJson(null)->fromString($this->jsonString);
        $this->assertEquals($this->jsonString, $json->getData());
    }

    /**
     * @group json-conversion-test
     */
    public function testFromStringConversion()
    {
        $expected = "name,age\nBuddha,80\n";
        $actual = $this->initJson()->fromString($this->jsonString)->convert();
        $this->assertEquals($expected, $actual);
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
        $this->assertStringContainsString("Turkey,\"Republic of Turkey\",Türkiye,783562,39,35\n", file_get_contents($path));
        unlink($path);
        $this->assertFileDoesNotExist($path);
    }
}
