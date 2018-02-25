<?php

namespace OzdemirBurak\JsonCsv\Tests;

use OzdemirBurak\JsonCsv\File\Csv;
use PHPUnit\Framework\TestCase;

class CsvTest extends TestCase
{
    /**
     * @group csv-basic-test
     */
    public function testFileReading()
    {
        $this->assertEquals('iris', ($csv = $this->init())->getFilename());
        $this->assertContains('6.3,3.3,6.0,2.5,Iris-virginica', $csv->getData());
    }

    /**
     * @group csv-basic-test
     */
    public function testSetter()
    {
        $conversion = $this->init()->setConversionKey('options', $options = JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $this->assertEquals($options, $conversion['options']);
    }

    /**
     * @group csv-conversion-test
     */
    public function testConversion()
    {
        $this->assertContains('{"SepalLength":"6.3","SepalWidth":"3.3","PetalLength":"6.0","PetalWidth":"2.5","Name":"Iris-virginica"}', $this->init()->convert());
    }

    /**
     * @group csv-conversion-test
     */
    public function testConversionAndSave()
    {
        $this->init()->convertAndSave($path = __DIR__ . '/data/iris.json');
        $this->assertFileExists($path);
        $this->assertContains('{"SepalLength":"6.3","SepalWidth":"3.3","PetalLength":"6.0","PetalWidth":"2.5","Name":"Iris-virginica"}', file_get_contents($path));
        unlink($path);
        $this->assertFileNotExists($path);
    }

    /**
     * @group csv-conversion-test
     */
    public function testConversionAndDownload()
    {
        $this->init()->convertAndDownload(null, false);
        $this->expectOutputRegex('/{"SepalLength":"6.3","SepalWidth":"3.3","PetalLength":"6.0","PetalWidth":"2.5","Name":"Iris-virginica"}/');
    }

    /**
     * @return \OzdemirBurak\JsonCsv\File\Csv
     */
    private function init() : Csv
    {
        return new Csv(__DIR__ . '/data/iris.csv');
    }
}
