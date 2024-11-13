<?php

namespace OzdemirBurak\JsonCsv\Tests;

use OzdemirBurak\JsonCsv\Tests\Traits\TestTrait;
use PHPUnit\Framework\TestCase;
use OzdemirBurak\JsonCsv\Exceptions\InvalidCsvException;

class CsvTest extends TestCase
{
    use TestTrait;

    /**
     * @var string
     */
    protected $ext =  'json';

    /**
     * @var string
     */
    protected $csvString = "name,age\nBuddha,80\n";

    /**
     * @group csv-basic-test
     */
    public function testFileReading()
    {
        $this->assertEquals('iris', ($csv = $this->initCsv())->getFilename());
        $this->assertStringContainsString('6.3,3.3,6.0,2.5,Iris-virginica', $csv->getData());
    }

    /**
     * @group csv-basic-test
     */
    public function testSetter()
    {
        $options = JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES;
        $conversion = $this->initCsv()->setConversionKey('options', $options);
        $this->assertEquals($options, $conversion['options']);
    }

    /**
     * @group csv-basic-test
     */
    public function testFromString()
    {
        $csv = $this->initCsv(null)->fromString($this->csvString);
        $this->assertEquals($this->csvString, $csv->getData());
    }

    /**
     * @group csv-basic-test
     */
    public function testEmptyCsvString()
    {
        $csv = $this->initCsv(null)->fromString("");
        $this->assertEquals("", $csv->getData());
    }
    /**
     * @group csv-conversion-test
     */
    public function testFromStringConversion()
    {
        $expected = '[{"name":"Buddha","age":"80"}]';
        $actual = $this->initCsv()->fromString($this->csvString)->convert();
        $this->assertEquals($expected, $actual);
    }

    /**
     * @group csv-conversion-download-save-test
     */
    public function testConversionAndDownload()
    {
        $this->initCsv()->convertAndDownload(null, false);
        $this->expectOutputRegex('/{"SL":"6.3","SW":"3.3","PL":"6.0","PW":"2.5","Name":"Iris-virginica"}/');
    }

    /**
     * @group csv-conversion-download-save-test
     */
    public function testConversionAndSave()
    {
        $path = $this->path('iris', 'json');
        $this->initCsv()->convertAndSave($path);
        $this->assertFileExists($path);
        $json = '{"SL":"6.3","SW":"3.3","PL":"6.0","PW":"2.5","Name":"Iris-virginica"}';
        $this->assertStringContainsString($json, file_get_contents($path));
        unlink($path);
        $this->assertFileDoesNotExist($path);
    }
}
