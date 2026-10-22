<?php

namespace OzdemirBurak\JsonCsv\Tests;

use OzdemirBurak\JsonCsv\File\Csv;
use OzdemirBurak\JsonCsv\File\Json;
use OzdemirBurak\JsonCsv\Tests\Traits\TestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class JsonReverseTest extends TestCase
{
    use TestTrait;

    protected string $ext = 'json';

    private function checkReverseConversion(string $file, string $join = '_'): void
    {
        $pathCsvOut = $this->tempPath('csv');
        $this->initJson($file)->setConversionKey('join', $join)->convertAndSave($pathCsvOut);

        $pathJsonOut = $this->tempPath('json');
        (new Csv($pathCsvOut))->setConversionKey('join', $join)->setConversionKey('numeric', true)
            ->convertAndSave($pathJsonOut);

        $this->assertJsonFileEqualsJsonFile($this->path($file, 'json'), $pathJsonOut);
    }

    #[Group('json-reverse-test')]
    public function testCountries(): void
    {
        $this->checkReverseConversion('countries');
    }

    #[Group('json-reverse-test')]
    public function testPeople(): void
    {
        $this->checkReverseConversion('people', '-');
    }

    #[Group('json-reverse-test')]
    public function testProperties(): void
    {
        $this->checkReverseConversion('properties');
    }

    #[Group('json-reverse-test')]
    public function testStats(): void
    {
        // Records keyed by id become rows without the ids, so compare with the values only
        $csv = (new Csv())->fromString($this->initJson('stats')->convert())->setConversionKey('numeric', true);
        $expected = array_values((array) json_decode((string) file_get_contents($this->path('stats', 'json')), true));
        $this->assertSame($expected, $csv->toArray());
    }

    #[Group('json-reverse-test')]
    public function testBackslashesRoundTripWithDefaults(): void
    {
        $data = [['a' => 'C:\\dir\\', 'b' => 'p\\"q'], ['a' => 'x', 'b' => 'y']];
        $csv = (new Json())->fromString(json_encode($data))->convert();
        $this->assertEquals($data, json_decode((new Csv())->fromString($csv)->convert(), true));
    }
}
