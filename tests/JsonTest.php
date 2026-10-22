<?php

namespace OzdemirBurak\JsonCsv\Tests;

use OzdemirBurak\JsonCsv\File\Csv;
use OzdemirBurak\JsonCsv\File\Json;
use OzdemirBurak\JsonCsv\Tests\Traits\TestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;
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
        $conversion = $this->initJson()->setConversionKey('bom', true)->getConversion();
        $this->assertTrue($conversion['bom']);
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
        $path = $this->tempPath('csv');
        $expected = $this->initJson()->convert();
        $this->assertSame(strlen($expected), $this->initJson()->convertAndSave($path));
        $this->assertStringEqualsFile($path, $expected);
        $this->assertStringContainsString("Türkiye,\"Republic of Türkiye\",Türkiye", $expected);
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
        $this->assertSame("name,value\nTest,\n", $result);
    }

    #[Group('json-edge-cases')]
    public function testBooleanValues(): void
    {
        $json = '{"name": "Test", "active": true, "deleted": false}';
        $result = (new Json())->fromString($json)->convert();
        $this->assertSame("name,active,deleted\nTest,1,\n", $result);
    }

    #[Group('json-edge-cases')]
    public function testDeeplyNestedJson(): void
    {
        // When a top-level associative array contains nested objects,
        // each top-level key's value becomes a row, with the key stripped
        $json = '{"level1": {"level2": {"level3": {"value": "deep"}}}}';
        $result = (new Json())->fromString($json)->convert();
        $this->assertSame("level2.level3.value\ndeep\n", $result);
    }

    #[Group('json-edge-cases')]
    public function testSpecialCharactersInValues(): void
    {
        $json = '{"name": "Test, with comma", "description": "Has \"quotes\""}';
        $result = (new Json())->fromString($json)->convert();
        $this->assertSame("name,description\n\"Test, with comma\",\"Has \"\"quotes\"\"\"\n", $result);
    }

    #[Group('json-edge-cases')]
    public function testCustomDelimiter(): void
    {
        $json = new Json();
        $json->fromString('{"a": 1, "b": 2}');
        $json->setConversionKey('delimiter', ';');
        $result = $json->convert();
        $this->assertSame("a;b\n1;2\n", $result);
    }

    #[Group('json-edge-cases')]
    public function testCustomJoinCharacter(): void
    {
        $json = new Json();
        $json->fromString('{"parent": {"child": "value"}}');
        $json->setConversionKey('join', '_');
        $result = $json->convert();
        $this->assertSame("parent_child\nvalue\n", $result);
    }

    #[Group('json-edge-cases')]
    public function testUtf8Encoding(): void
    {
        $json = new Json();
        $json->fromString('{"name": "日本語"}');
        $json->setConversionKey('bom', true)->getConversion();
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
        $this->assertSame("tags.0,tags.1,tags.2\n1,2,3\n", $result);
    }

    #[Group('json-edge-cases')]
    public function testMixedArrayObjects(): void
    {
        $json = '[{"a": 1}, {"b": 2}]';
        $result = (new Json())->fromString($json)->convert();
        $this->assertSame("a,b\n1,\n,2\n", $result);
    }

    #[Group('json-edge-cases')]
    public function testSingleObject(): void
    {
        $json = '{"name": "Single", "value": 42}';
        $result = (new Json())->fromString($json)->convert();
        $this->assertSame("name,value\nSingle,42\n", $result);
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
        $this->assertSame("0,1\nfirst,second\n", $result);
    }

    #[Group('json-edge-cases')]
    public function testNewlinesInValues(): void
    {
        $json = '{"text": "line1\nline2"}';
        $result = (new Json())->fromString($json)->convert();
        $this->assertSame("text\n\"line1\nline2\"\n", $result);
    }

    #[Group('json-edge-cases')]
    public function testLargeNumbers(): void
    {
        $json = '{"small": 1, "large": 9999999999999999, "float": 3.14159265359}';
        $result = (new Json())->fromString($json)->convert();
        $this->assertSame("small,large,float\n1,9999999999999999,3.14159265359\n", $result);
    }

    #[Group('json-edge-cases')]
    public function testEmptyStringValues(): void
    {
        $json = '{"name": "", "value": "test"}';
        $result = (new Json())->fromString($json)->convert();
        $this->assertSame("name,value\n,test\n", $result);
    }

    #[Group('json-edge-cases')]
    public function testMixedNestedAndFlat(): void
    {
        $json = '[{"id": 1, "meta": {"type": "A"}}, {"id": 2, "meta": {"type": "B"}}]';
        $result = (new Json())->fromString($json)->convert();
        $this->assertSame("id,meta.type\n1,A\n2,B\n", $result);
    }

    #[Group('json-edge-cases')]
    public function testObjectsWithDifferentKeys(): void
    {
        $json = '[{"a": 1, "b": 2}, {"b": 3, "c": 4}]';
        $result = (new Json())->fromString($json)->convert();
        $this->assertSame("a,b,c\n1,2,\n,3,4\n", $result);
    }

    #[Group('json-edge-cases')]
    public function testCustomNullValue(): void
    {
        $json = new Json();
        $json->fromString('[{"a": 1}, {"b": 2}]');
        $json->setConversionKey('null', 'N/A');
        $result = $json->convert();
        $this->assertSame("a,b\n1,N/A\nN/A,2\n", $result);
    }

    #[Group('json-edge-cases')]
    public function testCustomEnclosure(): void
    {
        $json = new Json();
        $json->fromString('{"name": "Test, with comma"}');
        $json->setConversionKey('enclosure', "'");
        $result = $json->convert();
        $this->assertSame("name\n'Test, with comma'\n", $result);
    }

    #[Group('json-edge-cases')]
    public function testUnicodeEmoji(): void
    {
        $json = '{"emoji": "Hello 👋 World 🌍"}';
        $result = (new Json())->fromString($json)->convert();
        $this->assertSame("emoji\n\"Hello 👋 World 🌍\"\n", $result);
    }

    #[Group('json-edge-cases')]
    public function testArrayOfArrays(): void
    {
        // When top-level object contains array of arrays, the top-level key is stripped
        $json = '{"matrix": [[1, 2], [3, 4]]}';
        $result = (new Json())->fromString($json)->convert();
        $this->assertSame("0.0,0.1,1.0,1.1\n1,2,3,4\n", $result);
    }

    #[Group('json-edge-cases')]
    public function testFluentInterface(): void
    {
        $result = (new Json())
            ->fromString('{"a": 1}')
            ->convert();
        $this->assertSame("a\n1\n", $result);
    }

    #[Group('json-edge-cases')]
    public function testSpecialCharsInKeys(): void
    {
        $json = '{"key with space": 1, "key-with-dash": 2}';
        $result = (new Json())->fromString($json)->convert();
        $this->assertSame("\"key with space\",key-with-dash\n1,2\n", $result);
    }

    #[Group('json-edge-cases')]
    public function testTopLevelScalarArray(): void
    {
        $json = '["apple", "banana", "cherry"]';
        $result = (new Json())->fromString($json)->convert();
        $this->assertSame("0,1,2\napple,banana,cherry\n", $result);
    }

    #[Group('json-bug-fixes')]
    public function testTopLevelScalarThrowsRuntimeException(): void
    {
        $this->expectException(RuntimeException::class);
        (new Json())->fromString('"abc"')->convert();
    }

    #[Group('json-bug-fixes')]
    public function testMixedScalarRowsThrowRuntimeException(): void
    {
        $this->expectException(RuntimeException::class);
        (new Json())->fromString('[{"a": 1}, 5]')->convert();
    }

    #[Group('json-bug-fixes')]
    public function testObjectWithScalarAndArrayOfObjectsIsSingleRow(): void
    {
        $json = '{"name": "x", "tags": [{"a": 1}, {"a": 2}]}';
        $result = (new Json())->fromString($json)->convert();
        $this->assertEquals("name,tags.0.a,tags.1.a\nx,1,2\n", $result);
    }

    #[Group('json-bug-fixes')]
    public function testNumericKeysKeepTheirColumns(): void
    {
        $result = (new Json())->fromString('[{"2020": 1}, {"2021": 2}]')->convert();
        $this->assertEquals("2020,2021\n1,\n,2\n", $result);
        $result = (new Json())->fromString('[[1, 2], [3, 4]]')->convert();
        $this->assertEquals("0,1\n1,2\n3,4\n", $result);
    }

    #[Group('json-bug-fixes')]
    public function testCustomNullValueAppliesToExplicitNulls(): void
    {
        $json = (new Json())->fromString('[{"a": null, "b": 1}, {"b": 2}]');
        $json->setConversionKey('null', 'N/A');
        $this->assertEquals("a,b\nN/A,1\nN/A,2\n", $json->convert());
    }

    #[Group('json-bug-fixes')]
    public function testBigIntegersAndFloatsKeepPrecision(): void
    {
        $json = '[{"id": 12345678901234567890, "f": 0.1234567890123456}]';
        $result = (new Json())->fromString($json)->convert();
        $this->assertEquals("id,f\n12345678901234567890,0.1234567890123456\n", $result);
    }

    #[Group('json-bug-fixes')]
    public function testRowsWithoutKeysGiveEmptyOutput(): void
    {
        $this->assertEquals('', (new Json())->fromString('[{}, {}]')->convert());
        $this->assertEquals('', (new Json())->fromString('[[]]')->convert());
    }

    #[Group('json-validation')]
    public function testFlattenedKeyCollisionThrows(): void
    {
        $this->expectException(RuntimeException::class);
        (new Json())->fromString('[{"a.b": 1, "a": {"b": 2}}]')->convert();
    }

    #[Group('json-features')]
    public function testSanitizeEscapesFormulaCells(): void
    {
        $json = (new Json())->fromString(
            '[{"=x": "=1+1", "b": "+a", "c": "-b", "d": "@c", "e": "ok", '
            . '"f": -5, "g": -0.5, "h": -12345678901234567890}]'
        );
        $numbers = ',-5,-0.5,-12345678901234567890' . "\n";
        $this->assertEquals("=x,b,c,d,e,f,g,h\n=1+1,+a,-b,@c,ok" . $numbers, $json->convert());
        $json->setConversionKey('sanitize', true);
        $this->assertEquals("'=x,b,c,d,e,f,g,h\n'=1+1,'+a,'-b,'@c,ok" . $numbers, $json->convert());
    }

    #[Group('json-features')]
    public function testJsonLinesInput(): void
    {
        $json = (new Json())->fromString("{\"a\": 1}\n\n{\"a\": 2, \"b\": {\"c\": 3}}\r\n");
        $json->setConversionKey('lines', true);
        $this->assertEquals("a,b.c\n1,\n2,3\n", $json->convert());
    }

    #[Group('json-features')]
    public function testJsonLinesInvalidLineThrows(): void
    {
        $json = (new Json())->fromString("{\"a\": 1}\n{oops}\n");
        $json->setConversionKey('lines', true);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('line 2');
        $json->convert();
    }

    #[Group('json-v2')]
    public function testUtf8BomInJsonInputIsIgnored(): void
    {
        $this->assertSame("a\n1\n", (new Json())->fromString("\xEF\xBB\xBF[{\"a\":1}]")->convert());
        $lines = (new Json())->setConversionKey('lines', true)->fromString("\xEF\xBB\xBF{\"a\":1}\n");
        $this->assertSame("a\n1\n", $lines->convert());
    }

    #[Group('json-v2')]
    public function testEmptyOrWhitespaceJsonGivesEmptyOutput(): void
    {
        $this->assertSame('', (new Json())->fromString('')->convert());
        $this->assertSame('', (new Json())->fromString(" \n")->convert());
    }

    #[Group('json-v2')]
    public function testNumberOutOfRangeThrows(): void
    {
        $this->expectException(RuntimeException::class);
        (new Json())->fromString('[{"a":1e400,"b":2}]')->convert();
    }

    #[Group('json-v2')]
    public function testSingleEmptyCellIsEnclosedAndNotABlankLine(): void
    {
        $csv = (new Json())->fromString('[{"a":"x"},{"a":""},{"a":null},{"a":"y"}]')->convert();
        $this->assertSame("a\nx\n\"\"\n\"\"\ny\n", $csv);
        $this->assertSame("\"\"\n1\n", (new Json())->fromString('[{"":1}]')->convert());
    }

    #[Group('json-v2')]
    public function testOnlyEmptyNestedContainersGiveEmptyOutput(): void
    {
        $this->assertSame('', (new Json())->fromString('{"a":{}}')->convert());
        $this->assertSame('', (new Json())->fromString('{"a":[],"b":{}}')->convert());
    }

    #[Group('json-v2')]
    public function testJsonLinesScalarLinesThrow(): void
    {
        $this->expectException(RuntimeException::class);
        (new Json())->setConversionKey('lines', true)->fromString("1\n2\n")->convert();
    }

    #[Group('json-v2')]
    public function testFloatOutputDoesNotDependOnSerializePrecision(): void
    {
        $previous = ini_set('serialize_precision', '17');
        try {
            $this->assertSame("a\n0.1\n", (new Json())->fromString('[{"a":0.1}]')->convert());
            $csv = (new Csv())->setConversionKey('numeric', true)->fromString("a\n0.1\n");
            $this->assertSame('[{"a":0.1}]', $csv->convert());
            // The conversion restores the setting
            $this->assertSame('17', ini_get('serialize_precision'));
        } finally {
            ini_set('serialize_precision', (string) $previous);
        }
    }

    #[Group('json-v2')]
    #[DataProvider('invalidOptions')]
    public function testInvalidOptionsThrow(string $key, mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Json())->setConversionKey($key, $value);
    }

    public static function invalidOptions(): array
    {
        return [
            'unknown key' => ['delimeter', ';'],
            'delimiter equals enclosure' => ['delimiter', '"'],
            'newline delimiter' => ['delimiter', "\n"],
            'carriage return enclosure' => ['enclosure', "\r"],
            'escape equals delimiter' => ['escape', ','],
            'bool option with string' => ['sanitize', 'yes'],
            'extension is not an option' => ['extension', 'txt'],
            'two-character delimiter' => ['delimiter', ',,'],
            'empty enclosure' => ['enclosure', ''],
            'two-character escape' => ['escape', '\\\\'],
            'empty join' => ['join', ''],
            'unknown rows value' => ['rows', 'all'],
            'space enclosure' => ['enclosure', ' '],
            'tab enclosure' => ['enclosure', "\t"],
            'old option name' => ['utf8_encoding', true],
        ];
    }

    #[Group('json-v2')]
    public function testFromArray(): void
    {
        $json = Json::fromArray([['a' => 1, 'b' => ['c' => true]], ['a' => 2]]);
        $this->assertSame("a,b.c\n1,1\n2,\n", $json->convert());
    }

    #[Group('json-v2')]
    public function testConvertAndSaveThrowsOnFailure(): void
    {
        $this->expectException(RuntimeException::class);
        @(new Json())->fromString('{"a":1}')->convertAndSave('/nonexistent-dir/x.csv');
    }

    #[Group('json-v2')]
    public function testConvertAndSaveReturnsBytes(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'jsoncsv');
        try {
            $this->assertSame(4, (new Json())->fromString('{"a":1}')->convertAndSave($path));
            $this->assertStringEqualsFile($path, "a\n1\n");
        } finally {
            unlink($path);
        }
    }

    #[Group('json-v2')]
    public function testDownloadHeaders(): void
    {
        $this->assertSame([
            'Content-Disposition' => 'attachment; filename="countries.csv"; filename*=UTF-8\'\'countries.csv',
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Length' => '12',
        ], $this->initJson()->getDownloadHeaders(null, 12));
        $headers = (new Json())->getDownloadHeaders('Türkiye "x"', 0);
        $this->assertSame(
            'attachment; filename="T__rkiye _x_.csv"; filename*=UTF-8\'\'T%C3%BCrkiye%20%22x%22.csv',
            $headers['Content-Disposition']
        );
        $disposition = (new Json())->getDownloadHeaders('', 0)['Content-Disposition'];
        $this->assertStringContainsString('filename="download.csv"', $disposition);
    }

    #[Group('json-v2')]
    public function testFromArrayWithObjectThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DateTimeImmutable');
        Json::fromArray([['a' => new \DateTimeImmutable()]])->convert();
    }

    #[Group('json-v2-features')]
    public function testDefaultJoinIsDot(): void
    {
        $this->assertSame("a.b,c\n1,2\n", (new Json())->fromString('[{"a":{"b":1},"c":2}]')->convert());
    }

    #[Group('json-v2-features')]
    public function testBooleanOptions(): void
    {
        $json = (new Json())->fromString('[{"a":true,"b":false}]');
        $this->assertSame("a,b\n1,\n", $json->convert());
        $json->setConversionKey('true', 'true')->setConversionKey('false', 'false');
        $this->assertSame("a,b\ntrue,false\n", $json->convert());
        $this->assertSame("\"\"\n\"\"\n", (new Json())->fromString('[{"":false}]')->convert());
    }

    #[Group('json-v2-features')]
    public function testRowsOption(): void
    {
        $keyed = '{"u1":{"a":1},"u2":{"a":2}}';
        $this->assertSame("u1.a,u2.a\n1,2\n", (new Json())->fromString($keyed)->convert());
        $this->assertSame("a\n1\n2\n", (new Json())->fromString($keyed)->setConversionKey('rows', 'each')->convert());
        $nested = '[{"a":1},{"a":2}]';
        $single = (new Json())->fromString($nested)->setConversionKey('rows', 'single');
        $this->assertSame("0.a,1.a\n1,2\n", $single->convert());
    }

    #[Group('json-v2-features')]
    public function testRowsEachWithScalarThrows(): void
    {
        $this->expectException(RuntimeException::class);
        (new Json())->fromString('{"a":1,"b":{"c":2}}')->setConversionKey('rows', 'each')->convert();
    }

    #[Group('json-v2-features')]
    public function testInvalidRowsValueThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Json())->setConversionKey('rows', 'all');
    }

    #[Group('json-v2-features')]
    public function testJsonLinesSplitOnlyOnLineFeed(): void
    {
        // A bare carriage return is JSON whitespace, not a line break
        $json = (new Json())->setConversionKey('lines', true)->fromString("{\"a\":\r1}\r\n{\"a\":2}");
        $this->assertSame("a\n1\n2\n", $json->convert());
    }

    #[Group('json-v2-features')]
    #[DataProvider('convertFileCases')]
    public function testConvertFileGivesSameOutputAsConvert(string $input, bool $lines): void
    {
        $in = $this->tempPath($lines ? 'jsonl' : 'json');
        file_put_contents($in, $input);
        $out = $this->tempPath('csv');
        $json = (new Json())->setConversionKey('lines', $lines)->setConversionKey('bom', true);
        $expected = $json->fromString($input)->convert();
        $this->assertSame(strlen($expected), $json->convertFile($in, $out));
        $this->assertStringEqualsFile($out, $expected);
    }

    public static function convertFileCases(): array
    {
        $lines = '';
        for ($i = 0; $i < 3000; $i++) {
            $row = ['id' => $i, 'v' => str_repeat('x', $i % 50), 'n' => $i % 7 ? ['k' => $i] : null];
            $lines .= json_encode($row) . "\n";
        }
        return [
            'json' => ['[{"a":1,"b":{"c":"x"}},{"a":2,"d":true}]', false],
            'json lines' => ["\xEF\xBB\xBF{\"a\":1}\r\n\n{\"b\":{\"c\":2}}\n", true],
            'large json lines' => [$lines, true],
            'empty json lines' => ["\n\n", true],
        ];
    }

    #[Group('json-v2-features')]
    public function testConvertFileDoesNotLeavePartialOutput(): void
    {
        $in = $this->tempPath('jsonl');
        file_put_contents($in, "{\"a\":1}\n{oops}\n");
        $out = $this->tempPath('csv');
        try {
            (new Json())->setConversionKey('lines', true)->convertFile($in, $out);
            $this->fail('No exception');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('line 2', $e->getMessage());
        }
        $this->assertFileDoesNotExist($out);
        $this->assertSame([], glob($out . '*') ?: []);
    }

    #[Group('json-v2-features')]
    public function testConversionRestoresGcAndPrecision(): void
    {
        $precision = ini_get('serialize_precision');
        (new Json())->fromString('[{"a":1.5}]')->convert();
        $this->assertTrue(gc_enabled());
        gc_disable();
        try {
            (new Json())->fromString('[{"a":1.5}]')->convert();
            $this->assertFalse(gc_enabled());
        } finally {
            gc_enable();
        }
        $this->assertSame($precision, ini_get('serialize_precision'));
    }

    #[Group('json-v2-final')]
    public function testUtf16InputGivesClearError(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('UTF-8');
        (new Json())->fromString("\xFF\xFE[\x00]\x00")->convert();
    }

    #[Group('json-v2-review')]
    public function testDelimiterEqualToEscapeThrowsInBothOrders(): void
    {
        $json = (new Json())->setConversionKey('escape', ';');
        $this->expectException(InvalidArgumentException::class);
        $json->setConversionKey('delimiter', ';');
    }

    #[Group('json-v2-review')]
    public function testFloatOptionValues(): void
    {
        $json = (new Json())->fromString('[{"a":null,"b":true}]')
            ->setConversionKey('null', 1.5)
            ->setConversionKey('true', 2.5);
        $this->assertSame("a,b\n1.5,2.5\n", $json->convert());
    }

    #[Group('json-v2-review')]
    public function testLinesDoesNotAffectFromArray(): void
    {
        $json = Json::fromArray(['a' => 1, 'b' => 2])->setConversionKey('lines', true);
        $this->assertSame("a,b\n1,2\n", $json->convert());
    }

    #[Group('json-v2-review')]
    public function testSaveToStreamWrapper(): void
    {
        $this->expectOutputString("a\n1\n");
        $this->assertSame(4, (new Json())->fromString('{"a":1}')->convertAndSave('php://output'));
    }

    #[Group('json-v2-review')]
    public function testSaveKeepsSymlinkAndPermissions(): void
    {
        $target = $this->tempPath('csv');
        file_put_contents($target, 'old');
        chmod($target, 0640);
        $link = $this->tempPath('csv');
        symlink($target, $link);
        (new Json())->fromString('{"a":1}')->convertAndSave($link);
        $this->assertTrue(is_link($link));
        $this->assertStringEqualsFile($target, "a\n1\n");
        clearstatcache();
        $this->assertSame(0640, fileperms($target) & 0777);
        (new Json())->fromString('{"a":2}')->convertAndSave($target);
        clearstatcache();
        $this->assertSame(0640, fileperms($target) & 0777);
        $this->assertStringEqualsFile($target, "a\n2\n");
    }

    #[Group('json-v2-review')]
    public function testFailedConvertFileKeepsExistingOutput(): void
    {
        $in = $this->tempPath('json');
        file_put_contents($in, '{oops}');
        $out = $this->tempPath('csv');
        file_put_contents($out, 'existing');
        try {
            (new Json())->convertFile($in, $out);
            $this->fail('No exception');
        } catch (RuntimeException) {
            $this->assertStringEqualsFile($out, 'existing');
        }
    }
}
