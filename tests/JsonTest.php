<?php

namespace OzdemirBurak\JsonCsv\Tests;

use OzdemirBurak\JsonCsv\File\Json;
use OzdemirBurak\JsonCsv\Tests\Traits\TestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class JsonTest extends TestCase
{
    use TestTrait;

    protected string $ext = 'csv';
    protected string $jsonString = '{"name": "John", "age": 30}';

    #[Group('json-basic-test')]
    public function testFileReading(): void
    {
        $json = $this->initJson();
        $this->assertEquals('countries', $json->getFilename());
        $this->assertStringContainsString('"common": "Türkiye"', $json->getData());
    }

    #[Group('json-basic-test')]
    public function testSetter(): void
    {
        $conversion = $this->initJson()->setConversionKey('utf8_encoding', true);
        $this->assertTrue($conversion['utf8_encoding']);
    }

    #[Group('json-basic-test')]
    public function testFromString(): void
    {
        $json = $this->initJson(null)->fromString($this->jsonString);
        $this->assertEquals($this->jsonString, $json->getData());
    }

    #[Group('json-conversion-test')]
    public function testFromStringConversion(): void
    {
        $expected = "name,age\nJohn,30\n";
        $actual = $this->initJson(null)->fromString($this->jsonString)->convert();
        $this->assertEquals($expected, $actual);
    }

    #[Group('json-conversion-test')]
    public function testPeople(): void
    {
        $this->checkConversion('people');
    }

    #[Group('json-conversion-test')]
    public function testProperties(): void
    {
        $this->checkConversion('properties');
    }

    #[Group('json-conversion-test')]
    public function testStats(): void
    {
        $this->checkConversion('stats');
    }

    #[Group('json-conversion-download-save-test')]
    public function testConversionAndDownload(): void
    {
        $this->initJson()->convertAndDownload(null, false);
        $this->expectOutputRegex('/Türkiye,"Republic of Türkiye",Türkiye,783562,39,35\n/');
    }

    #[Group('json-conversion-download-save-test')]
    public function testConversionAndSave(): void
    {
        $path = $this->path('countries.out', 'csv');
        $this->initJson()->convertAndSave($path);
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringContainsString("Türkiye,\"Republic of Türkiye\",Türkiye", $content);
        unlink($path);
        $this->assertFileDoesNotExist($path);
    }

    #[Group('json-edge-cases')]
    public function testEmptyArray(): void
    {
        $result = (new Json())->fromString('[]')->convert();
        $this->assertEquals('', $result);
    }

    #[Group('json-edge-cases')]
    public function testEmptyObject(): void
    {
        $result = (new Json())->fromString('{}')->convert();
        $this->assertEquals('', $result);
    }

    #[Group('json-edge-cases')]
    public function testInvalidJson(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON data');
        (new Json())->fromString('invalid json')->convert();
    }

    #[Group('json-edge-cases')]
    public function testNullValues(): void
    {
        $json = '{"name": "Test", "value": null}';
        $result = (new Json())->fromString($json)->convert();
        $this->assertStringContainsString('name,value', $result);
        $this->assertStringContainsString('Test,', $result);
    }

    #[Group('json-edge-cases')]
    public function testBooleanValues(): void
    {
        $json = '{"name": "Test", "active": true, "deleted": false}';
        $result = (new Json())->fromString($json)->convert();
        $this->assertStringContainsString('name,active,deleted', $result);
        $this->assertStringContainsString('Test,1,', $result);
    }

    #[Group('json-edge-cases')]
    public function testDeeplyNestedJson(): void
    {
        // When a top-level associative array contains nested objects,
        // each top-level key's value becomes a row, with the key stripped
        $json = '{"level1": {"level2": {"level3": {"value": "deep"}}}}';
        $result = (new Json())->fromString($json)->convert();
        $this->assertStringContainsString('level2_level3_value', $result);
        $this->assertStringContainsString('deep', $result);
    }

    #[Group('json-edge-cases')]
    public function testSpecialCharactersInValues(): void
    {
        $json = '{"name": "Test, with comma", "description": "Has \"quotes\""}';
        $result = (new Json())->fromString($json)->convert();
        $this->assertStringContainsString('name,description', $result);
    }

    #[Group('json-edge-cases')]
    public function testCustomDelimiter(): void
    {
        $json = new Json();
        $json->fromString('{"a": 1, "b": 2}');
        $json->setConversionKey('delimiter', ';');
        $result = $json->convert();
        $this->assertStringContainsString('a;b', $result);
    }

    #[Group('json-edge-cases')]
    public function testCustomJoinCharacter(): void
    {
        $json = new Json();
        $json->fromString('{"parent": {"child": "value"}}');
        $json->setConversionKey('join', '.');
        $result = $json->convert();
        $this->assertStringContainsString('parent.child', $result);
    }

    #[Group('json-edge-cases')]
    public function testUtf8Encoding(): void
    {
        $json = new Json();
        $json->fromString('{"name": "日本語"}');
        $json->setConversionKey('utf8_encoding', true);
        $result = $json->convert();
        // Check for BOM
        $this->assertStringStartsWith("\xEF\xBB\xBF", $result);
        $this->assertStringContainsString('日本語', $result);
    }

    #[Group('json-edge-cases')]
    public function testArrayOfPrimitives(): void
    {
        $json = '{"tags": [1, 2, 3]}';
        $result = (new Json())->fromString($json)->convert();
        $this->assertStringContainsString('tags_0,tags_1,tags_2', $result);
        $this->assertStringContainsString('1,2,3', $result);
    }

    #[Group('json-edge-cases')]
    public function testMixedArrayObjects(): void
    {
        $json = '[{"a": 1}, {"b": 2}]';
        $result = (new Json())->fromString($json)->convert();
        $this->assertStringContainsString('a,b', $result);
    }

    #[Group('json-edge-cases')]
    public function testSingleObject(): void
    {
        $json = '{"name": "Single", "value": 42}';
        $result = (new Json())->fromString($json)->convert();
        $this->assertStringContainsString('name,value', $result);
        $this->assertStringContainsString('Single,42', $result);
    }

    #[Group('json-edge-cases')]
    public function testFileNotReadable(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File not readable');
        new Json('/nonexistent/path/file.json');
    }

    #[Group('json-edge-cases')]
    public function testNumericStringKeys(): void
    {
        // Note: PHP's json_decode converts {"0": "first", "1": "second"} to a numeric array
        // So this becomes an array of scalars, flattened with numeric indices
        $json = '{"0": "first", "1": "second"}';
        $result = (new Json())->fromString($json)->convert();
        $this->assertStringContainsString('0,1', $result);
        $this->assertStringContainsString('first,second', $result);
    }

    #[Group('json-edge-cases')]
    public function testNewlinesInValues(): void
    {
        $json = '{"text": "line1\nline2"}';
        $result = (new Json())->fromString($json)->convert();
        $this->assertStringContainsString('text', $result);
    }

    #[Group('json-edge-cases')]
    public function testLargeNumbers(): void
    {
        $json = '{"small": 1, "large": 9999999999999999, "float": 3.14159265359}';
        $result = (new Json())->fromString($json)->convert();
        $this->assertStringContainsString('small,large,float', $result);
        $this->assertStringContainsString('3.14159265359', $result);
    }

    #[Group('json-edge-cases')]
    public function testEmptyStringValues(): void
    {
        $json = '{"name": "", "value": "test"}';
        $result = (new Json())->fromString($json)->convert();
        $this->assertStringContainsString('name,value', $result);
    }

    #[Group('json-edge-cases')]
    public function testMixedNestedAndFlat(): void
    {
        $json = '[{"id": 1, "meta": {"type": "A"}}, {"id": 2, "meta": {"type": "B"}}]';
        $result = (new Json())->fromString($json)->convert();
        $this->assertStringContainsString('id,meta_type', $result);
        $this->assertStringContainsString('1,A', $result);
        $this->assertStringContainsString('2,B', $result);
    }

    #[Group('json-edge-cases')]
    public function testObjectsWithDifferentKeys(): void
    {
        $json = '[{"a": 1, "b": 2}, {"b": 3, "c": 4}]';
        $result = (new Json())->fromString($json)->convert();
        $this->assertStringContainsString('a,b,c', $result);
    }

    #[Group('json-edge-cases')]
    public function testCustomNullValue(): void
    {
        $json = new Json();
        $json->fromString('[{"a": 1}, {"b": 2}]');
        $json->setConversionKey('null', 'N/A');
        $result = $json->convert();
        $this->assertStringContainsString('N/A', $result);
    }

    #[Group('json-edge-cases')]
    public function testCustomEnclosure(): void
    {
        $json = new Json();
        $json->fromString('{"name": "Test, with comma"}');
        $json->setConversionKey('enclosure', "'");
        $result = $json->convert();
        $this->assertStringContainsString("'Test, with comma'", $result);
    }

    #[Group('json-edge-cases')]
    public function testUnicodeEmoji(): void
    {
        $json = '{"emoji": "Hello 👋 World 🌍"}';
        $result = (new Json())->fromString($json)->convert();
        $this->assertStringContainsString('👋', $result);
        $this->assertStringContainsString('🌍', $result);
    }

    #[Group('json-edge-cases')]
    public function testArrayOfArrays(): void
    {
        // When top-level object contains array of arrays, the top-level key is stripped
        $json = '{"matrix": [[1, 2], [3, 4]]}';
        $result = (new Json())->fromString($json)->convert();
        $this->assertStringContainsString('0_0,0_1,1_0,1_1', $result);
        $this->assertStringContainsString('1,2,3,4', $result);
    }

    #[Group('json-edge-cases')]
    public function testGetDataMethod(): void
    {
        $json = new Json();
        $json->fromString('{"test": 1}');
        $this->assertEquals('{"test": 1}', $json->getData());
    }

    #[Group('json-edge-cases')]
    public function testFluentInterface(): void
    {
        $result = (new Json())
            ->fromString('{"a": 1}')
            ->convert();
        $this->assertStringContainsString('a', $result);
        $this->assertStringContainsString('1', $result);
    }

    #[Group('json-edge-cases')]
    public function testSpecialCharsInKeys(): void
    {
        $json = '{"key with space": 1, "key-with-dash": 2}';
        $result = (new Json())->fromString($json)->convert();
        $this->assertStringContainsString('key with space', $result);
        $this->assertStringContainsString('key-with-dash', $result);
    }

    #[Group('json-edge-cases')]
    public function testTopLevelScalarArray(): void
    {
        $json = '["apple", "banana", "cherry"]';
        $result = (new Json())->fromString($json)->convert();
        $this->assertStringContainsString('0,1,2', $result);
        $this->assertStringContainsString('apple,banana,cherry', $result);
    }
}
