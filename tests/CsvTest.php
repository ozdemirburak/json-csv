<?php

namespace OzdemirBurak\JsonCsv\Tests;

use OzdemirBurak\JsonCsv\File\Csv;
use OzdemirBurak\JsonCsv\Tests\Traits\TestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CsvTest extends TestCase
{
    use TestTrait;

    protected string $ext = 'json';
    protected string $csvString = "name,age\nJohn,30\n";

    #[Group('csv-basic-test')]
    public function testFileReading(): void
    {
        $csv = $this->initCsv();
        $this->assertEquals('iris', $csv->getFilename());
        $this->assertStringContainsString('6.3,3.3,6.0,2.5,Iris-virginica', $csv->getData());
    }

    #[Group('csv-basic-test')]
    public function testSetter(): void
    {
        $options = JSON_PRETTY_PRINT;
        $conversion = $this->initCsv()->setConversionKey('options', $options)->getConversion();
        $this->assertEquals($options, $conversion['options']);
    }

    #[Group('csv-basic-test')]
    public function testFromString(): void
    {
        $csv = $this->initCsv(null)->fromString($this->csvString);
        $this->assertEquals($this->csvString, $csv->getData());
    }

    #[Group('csv-basic-test')]
    public function testEmptyCsvString(): void
    {
        $csv = $this->initCsv(null)->fromString('');
        $this->assertEquals('', $csv->getData());
    }

    #[Group('csv-conversion-test')]
    public function testFromStringConversion(): void
    {
        $expected = '[{"name":"John","age":"30"}]';
        $actual = $this->initCsv(null)->fromString($this->csvString)->convert();
        $this->assertEquals($expected, $actual);
    }

    #[Group('csv-conversion-test')]
    public function testIris(): void
    {
        $this->checkConversion('iris');
    }

    #[Group('csv-conversion-download-save-test')]
    public function testConversionAndDownload(): void
    {
        $this->initCsv()->convertAndDownload(null, false);
        $this->expectOutputRegex('/{"SL":"6.3","SW":"3.3","PL":"6.0","PW":"2.5","Name":"Iris-virginica"}/');
    }

    #[Group('csv-conversion-download-save-test')]
    public function testConversionAndSave(): void
    {
        $path = $this->tempPath('json');
        $expected = $this->initCsv()->convert();
        $this->assertSame(strlen($expected), $this->initCsv()->convertAndSave($path));
        $this->assertStringEqualsFile($path, $expected);
        $this->assertStringEqualsFile($this->path('iris', 'json'), $expected);
    }

    #[Group('csv-edge-cases')]
    public function testEmptyInput(): void
    {
        $result = (new Csv())->fromString('')->convert();
        $this->assertEquals('[]', $result);
    }

    #[Group('csv-edge-cases')]
    public function testHeaderOnly(): void
    {
        $result = (new Csv())->fromString("name,age\n")->convert();
        $this->assertEquals('[]', $result);
    }

    #[Group('csv-edge-cases')]
    public function testQuotedValues(): void
    {
        $csv = "name,description\n\"John Doe\",\"A \"\"quoted\"\" value\"\n";
        $result = (new Csv())->fromString($csv)->convert();
        $decoded = json_decode($result, true);
        $this->assertEquals('John Doe', $decoded[0]['name']);
        $this->assertEquals('A "quoted" value', $decoded[0]['description']);
    }

    #[Group('csv-edge-cases')]
    public function testCommasInQuotedValues(): void
    {
        $csv = "name,address\nJohn,\"123 Main St, Suite 100\"\n";
        $result = (new Csv())->fromString($csv)->convert();
        $decoded = json_decode($result, true);
        $this->assertEquals('123 Main St, Suite 100', $decoded[0]['address']);
    }

    #[Group('csv-edge-cases')]
    public function testNewlinesInQuotedValues(): void
    {
        $csv = "name,bio\nJohn,\"Line 1\nLine 2\"\n";
        $result = (new Csv())->fromString($csv)->convert();
        $decoded = json_decode($result, true);
        $this->assertEquals("Line 1\nLine 2", $decoded[0]['bio']);
    }

    #[Group('csv-edge-cases')]
    public function testWindowsLineEndings(): void
    {
        $csv = "name,age\r\nJohn,30\r\n";
        $result = (new Csv())->fromString($csv)->convert();
        $decoded = json_decode($result, true);
        $this->assertEquals('John', $decoded[0]['name']);
        $this->assertEquals('30', $decoded[0]['age']);
    }

    #[Group('csv-edge-cases')]
    public function testOldMacLineEndings(): void
    {
        $csv = "name,age\rJohn,30\r";
        $result = (new Csv())->fromString($csv)->convert();
        $decoded = json_decode($result, true);
        $this->assertEquals('John', $decoded[0]['name']);
    }

    #[Group('csv-edge-cases')]
    public function testEmptyCells(): void
    {
        $csv = "a,b,c\n1,,3\n";
        $result = (new Csv())->fromString($csv)->convert();
        $decoded = json_decode($result, true);
        $this->assertEquals('1', $decoded[0]['a']);
        $this->assertEquals('3', $decoded[0]['c']);
        $this->assertArrayNotHasKey('b', $decoded[0]);
    }

    #[Group('csv-edge-cases')]
    public function testNestedKeys(): void
    {
        $csv = "user.name,user.email,address.city\nJohn,john@test.com,NYC\n";
        $result = (new Csv())->fromString($csv)->convert();
        $decoded = json_decode($result, true);
        $this->assertEquals('John', $decoded[0]['user']['name']);
        $this->assertEquals('john@test.com', $decoded[0]['user']['email']);
        $this->assertEquals('NYC', $decoded[0]['address']['city']);
    }

    #[Group('csv-edge-cases')]
    public function testCustomDelimiter(): void
    {
        $csv = new Csv();
        $csv->fromString("a;b;c\n1;2;3\n");
        $csv->setConversionKey('delimiter', ';');
        $result = $csv->convert();
        $decoded = json_decode($result, true);
        $this->assertEquals('1', $decoded[0]['a']);
        $this->assertEquals('2', $decoded[0]['b']);
        $this->assertEquals('3', $decoded[0]['c']);
    }

    #[Group('csv-edge-cases')]
    public function testCustomJoinCharacter(): void
    {
        $csv = new Csv();
        $csv->fromString("user_name,user_email\nJohn,john@test.com\n");
        $csv->setConversionKey('join', '_');
        $result = $csv->convert();
        $decoded = json_decode($result, true);
        $this->assertEquals('John', $decoded[0]['user']['name']);
    }

    #[Group('csv-edge-cases')]
    public function testNumericEnabled(): void
    {
        $csv = new Csv();
        $csv->fromString("id,price,name\n1,19.99,Widget\n");
        $csv->setConversionKey('numeric', true);
        $result = $csv->convert();
        $decoded = json_decode($result, true);
        $this->assertSame(1, $decoded[0]['id']);
        $this->assertSame(19.99, $decoded[0]['price']);
        $this->assertSame('Widget', $decoded[0]['name']);
    }

    #[Group('csv-edge-cases')]
    public function testNumericDisabled(): void
    {
        $csv = new Csv();
        $csv->fromString("id,price\n1,19.99\n");
        $result = $csv->convert();
        $decoded = json_decode($result, true);
        $this->assertSame('1', $decoded[0]['id']);
        $this->assertSame('19.99', $decoded[0]['price']);
    }

    #[Group('csv-edge-cases')]
    public function testJsonEncodeOptions(): void
    {
        $csv = new Csv();
        $csv->fromString("name\nTest\n");
        $csv->setConversionKey('options', JSON_PRETTY_PRINT);
        $result = $csv->convert();
        $this->assertStringContainsString("\n", $result);
    }

    #[Group('csv-edge-cases')]
    public function testUnicodeValues(): void
    {
        $csv = "name,city\n日本語,東京\n";
        $csv_obj = new Csv();
        $csv_obj->fromString($csv);
        $csv_obj->setConversionKey('options', JSON_UNESCAPED_UNICODE);
        $result = $csv_obj->convert();
        $this->assertStringContainsString('日本語', $result);
        $this->assertStringContainsString('東京', $result);
    }

    #[Group('csv-edge-cases')]
    public function testMultipleRows(): void
    {
        $csv = "id,name\n1,Alice\n2,Bob\n3,Charlie\n";
        $result = (new Csv())->fromString($csv)->convert();
        $decoded = json_decode($result, true);
        $this->assertCount(3, $decoded);
        $this->assertEquals('Alice', $decoded[0]['name']);
        $this->assertEquals('Bob', $decoded[1]['name']);
        $this->assertEquals('Charlie', $decoded[2]['name']);
    }

    #[Group('csv-edge-cases')]
    public function testDeeplyNestedKeys(): void
    {
        $csv = "a.b.c.d\nvalue\n";
        $result = (new Csv())->fromString($csv)->convert();
        $decoded = json_decode($result, true);
        $this->assertEquals('value', $decoded[0]['a']['b']['c']['d']);
    }

    #[Group('csv-edge-cases')]
    public function testFileNotReadable(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File not readable');
        new Csv('/nonexistent/path/file.csv');
    }

    #[Group('csv-edge-cases')]
    public function testTrailingNewline(): void
    {
        $csv = "name\nJohn\n\n";
        $result = (new Csv())->fromString($csv)->convert();
        $decoded = json_decode($result, true);
        $this->assertCount(1, $decoded);
    }

    #[Group('csv-edge-cases')]
    public function testNoTrailingNewline(): void
    {
        $csv = "name\nJohn";
        $result = (new Csv())->fromString($csv)->convert();
        $decoded = json_decode($result, true);
        $this->assertCount(1, $decoded);
        $this->assertEquals('John', $decoded[0]['name']);
    }

    #[Group('csv-edge-cases')]
    public function testMismatchedColumns(): void
    {
        $csv = "a,b,c\n1,2\n";
        $result = (new Csv())->fromString($csv)->convert();
        $decoded = json_decode($result, true);
        $this->assertEquals('1', $decoded[0]['a']);
        $this->assertEquals('2', $decoded[0]['b']);
        $this->assertArrayNotHasKey('c', $decoded[0]);
    }

    #[Group('csv-edge-cases')]
    public function testExtraColumns(): void
    {
        $csv = "a,b\n1,2,3\n";
        $result = (new Csv())->fromString($csv)->convert();
        $decoded = json_decode($result, true);
        $this->assertEquals('1', $decoded[0]['a']);
        $this->assertEquals('2', $decoded[0]['b']);
    }

    #[Group('csv-edge-cases')]
    public function testSingleQuoteEnclosure(): void
    {
        $csv = new Csv();
        $csv->fromString("name,value\n'hello','world'\n");
        $csv->setConversionKey('enclosure', "'");
        $result = $csv->convert();
        $decoded = json_decode($result, true);
        $this->assertEquals('hello', $decoded[0]['name']);
        $this->assertEquals('world', $decoded[0]['value']);
    }

    #[Group('csv-edge-cases')]
    public function testLargeNumbers(): void
    {
        $csv = new Csv();
        $csv->fromString("id,amount\n1,9999999999999999\n");
        $csv->setConversionKey('numeric', true);
        $result = $csv->convert();
        $decoded = json_decode($result, true);
        $this->assertSame(1, $decoded[0]['id']);
    }

    #[Group('csv-edge-cases')]
    public function testFloatNumbers(): void
    {
        $csv = new Csv();
        $csv->fromString("value\n3.14159\n");
        $csv->setConversionKey('numeric', true);
        $result = $csv->convert();
        $decoded = json_decode($result, true);
        $this->assertSame(3.14159, $decoded[0]['value']);
    }

    #[Group('csv-edge-cases')]
    public function testNegativeNumbers(): void
    {
        $csv = new Csv();
        $csv->fromString("value\n-42\n");
        $csv->setConversionKey('numeric', true);
        $result = $csv->convert();
        $decoded = json_decode($result, true);
        $this->assertSame(-42, $decoded[0]['value']);
    }

    #[Group('csv-edge-cases')]
    public function testFluentInterface(): void
    {
        $result = (new Csv())
            ->fromString("name\nTest\n")
            ->convert();
        $decoded = json_decode($result, true);
        $this->assertEquals('Test', $decoded[0]['name']);
    }

    #[Group('csv-edge-cases')]
    public function testEmojiValues(): void
    {
        $csv = "emoji\nHello 👋 World 🌍\n";
        $result = (new Csv())->fromString($csv)->convert();
        $decoded = json_decode($result, true);
        $this->assertStringContainsString('👋', $decoded[0]['emoji']);
        $this->assertStringContainsString('🌍', $decoded[0]['emoji']);
    }

    #[Group('csv-edge-cases')]
    public function testTabDelimiter(): void
    {
        $csv = new Csv();
        $csv->fromString("name\tvalue\nTest\t123\n");
        $csv->setConversionKey('delimiter', "\t");
        $result = $csv->convert();
        $decoded = json_decode($result, true);
        $this->assertEquals('Test', $decoded[0]['name']);
        $this->assertEquals('123', $decoded[0]['value']);
    }

    #[Group('csv-edge-cases')]
    public function testPipeDelimiter(): void
    {
        $csv = new Csv();
        $csv->fromString("name|value\nTest|123\n");
        $csv->setConversionKey('delimiter', '|');
        $result = $csv->convert();
        $decoded = json_decode($result, true);
        $this->assertEquals('Test', $decoded[0]['name']);
        $this->assertEquals('123', $decoded[0]['value']);
    }

    #[Group('csv-edge-cases')]
    public function testMultipleLevelsOfNesting(): void
    {
        $csv = "a.b.c.d.e\nvalue\n";
        $result = (new Csv())->fromString($csv)->convert();
        $decoded = json_decode($result, true);
        $this->assertEquals('value', $decoded[0]['a']['b']['c']['d']['e']);
    }

    #[Group('csv-edge-cases')]
    public function testSpecialCharsInHeader(): void
    {
        $csv = "name with space,key-with-dash\nval1,val2\n";
        $result = (new Csv())->fromString($csv)->convert();
        $decoded = json_decode($result, true);
        $this->assertEquals('val1', $decoded[0]['name with space']);
        $this->assertEquals('val2', $decoded[0]['key-with-dash']);
    }

    #[Group('csv-edge-cases')]
    public function testAllEmptyRow(): void
    {
        $csv = "a,b,c\n,,\n";
        $result = (new Csv())->fromString($csv)->convert();
        $decoded = json_decode($result, true);
        $this->assertEmpty($decoded);
    }

    #[Group('csv-edge-cases')]
    public function testJsonUnescapedSlashes(): void
    {
        $csv = new Csv();
        $csv->fromString("url\nhttps://example.com/path\n");
        $csv->setConversionKey('options', JSON_UNESCAPED_UNICODE);
        $this->assertSame('[{"url":"https:\/\/example.com\/path"}]', $csv->convert());
    }

    #[Group('csv-edge-cases')]
    public function testSingleColumn(): void
    {
        $csv = "name\nAlice\nBob\nCharlie\n";
        $result = (new Csv())->fromString($csv)->convert();
        $decoded = json_decode($result, true);
        $this->assertCount(3, $decoded);
        $this->assertEquals('Alice', $decoded[0]['name']);
        $this->assertEquals('Charlie', $decoded[2]['name']);
    }

    #[Group('csv-edge-cases')]
    public function testConsecutiveDelimiters(): void
    {
        $csv = "a,b,c\n1,,3\n4,5,\n";
        $result = (new Csv())->fromString($csv)->convert();
        $decoded = json_decode($result, true);
        $this->assertCount(2, $decoded);
    }

    #[Group('csv-bug-fixes')]
    public function testEscapedEnclosureDoesNotMergeRows(): void
    {
        $csv = (new Csv())->fromString("a,b\n\"x\\\"y\",z\nq,r\n");
        $csv->setConversionKey('escape', '\\');
        $decoded = json_decode($csv->convert(), true);
        $this->assertEquals([['a' => 'x\\"y', 'b' => 'z'], ['a' => 'q', 'b' => 'r']], $decoded);
    }

    #[Group('csv-bug-fixes')]
    public function testUtf8BomIsStripped(): void
    {
        $result = (new Csv())->fromString("\xEF\xBB\xBFa,b\n1,2\n")->convert();
        $this->assertEquals('[{"a":"1","b":"2"}]', $result);
    }

    #[Group('csv-bug-fixes')]
    public function testCarriageReturnInsideQuotesIsKept(): void
    {
        $result = (new Csv())->fromString("a\r\n\"x\ry\"\r\nz\r\n")->convert();
        $this->assertEquals([['a' => "x\ry"], ['a' => 'z']], json_decode($result, true));
    }

    #[Group('csv-bug-fixes')]
    public function testStrayQuoteInUnquotedFieldDoesNotMergeRows(): void
    {
        $csv = "name,height\nBob,5'10\"\nAl,6'\nCy,7'\n";
        $decoded = json_decode((new Csv())->fromString($csv)->convert(), true);
        $this->assertCount(3, $decoded);
        $this->assertEquals('5\'10"', $decoded[0]['height']);
    }

    #[Group('csv-bug-fixes')]
    public function testUnterminatedQuoteThrowsRuntimeException(): void
    {
        $this->expectException(RuntimeException::class);
        (new Csv())->fromString("a,b\n\"x,1\n2,3\n")->convert();
    }

    #[Group('csv-bug-fixes')]
    public function testLeadingBlankLinesBeforeHeaderAreSkipped(): void
    {
        $this->assertEquals('[{"a":"1","b":"2"}]', (new Csv())->fromString("\n\na,b\n1,2\n")->convert());
    }

    #[Group('csv-bug-fixes')]
    public function testNumericKeepsValuesThatAreNotSafeNumbers(): void
    {
        $csv = (new Csv())->fromString("a,b,c,d,e\n1e999,12345678901234567890,01234, 12,-0.5\n");
        $csv->setConversionKey('numeric', true);
        $this->assertEquals(
            '[{"a":"1e999","b":"12345678901234567890","c":"01234","d":" 12","e":-0.5}]',
            $csv->convert()
        );
    }

    #[Group('csv-bug-fixes')]
    public function testDirectoryPathThrowsRuntimeException(): void
    {
        $this->expectException(RuntimeException::class);
        new Csv(__DIR__);
    }

    #[Group('csv-bug-fixes')]
    public function testKeepEmptyOption(): void
    {
        $csv = (new Csv())->fromString("a,b\n,1\n,\n");
        $this->assertEquals('[{"b":"1"}]', $csv->convert());
        $csv->setConversionKey('keep_empty', true);
        $this->assertEquals('[{"a":"","b":"1"},{"a":"","b":""}]', $csv->convert());
    }

    #[Group('csv-validation')]
    #[DataProvider('headerCollisions')]
    public function testHeaderCollisionsThrow(string $csv): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('collision');
        (new Csv())->fromString($csv)->convert();
    }

    public static function headerCollisions(): array
    {
        return [
            'value and nested value' => ["a,a.b\n1,2\n"],
            'duplicate header' => ["a,a\n1,2\n"],
            'nested value first' => ["a.b,a\n1,2\n"],
            'in a later row' => ["a,a.b\n,\n1,2\n"],
        ];
    }

    #[Group('csv-validation')]
    public function testMultipleEmptyHeadersAreAllowed(): void
    {
        $this->assertEquals('[{"a":"1"}]', (new Csv())->fromString("a,,\n1,,\n")->convert());
    }

    #[Group('csv-features')]
    public function testJsonLinesOutput(): void
    {
        $csv = (new Csv())->fromString("a,b.c\n1,2\n3,4\n");
        $csv->setConversionKey('lines', true);
        $csv->setConversionKey('options', JSON_PRETTY_PRINT);
        $this->assertEquals("{\"a\":\"1\",\"b\":{\"c\":\"2\"}}\n{\"a\":\"3\",\"b\":{\"c\":\"4\"}}\n", $csv->convert());
    }

    #[Group('csv-validation')]
    public function testParentAndChildHeadersInDifferentRows(): void
    {
        $csv = (new Csv())->fromString("c,c.d\n1,\n,2\n");
        $this->assertEquals('[{"c":"1"},{"c":{"d":"2"}}]', $csv->convert());
        $csv->setConversionKey('keep_empty', true);
        $this->assertEquals('[{"c":"1"},{"c":{"d":"2"}}]', $csv->convert());
    }

    #[Group('csv-v2')]
    public function testJsonLinesEmptyOutput(): void
    {
        $this->assertSame('', (new Csv())->setConversionKey('lines', true)->fromString('')->convert());
        $this->assertSame('', (new Csv())->setConversionKey('lines', true)->fromString("a\n")->convert());
    }

    #[Group('csv-v2')]
    public function testEmptyHeaderNamesDoNotLoseValues(): void
    {
        $csv = (new Csv())->setConversionKey('keep_empty', true)->fromString(",\n1,\n");
        $this->assertSame('[{"":"1"}]', $csv->convert());
        $this->expectException(RuntimeException::class);
        (new Csv())->fromString("a,,\n1,2,3\n")->convert();
    }

    #[Group('csv-v2')]
    public function testNumericHeadersGiveObjects(): void
    {
        $this->assertSame('[{"0":"x","1":"y"},{"1":"y"}]', (new Csv())->fromString("0,1\nx,y\n,y\n")->convert());
    }

    #[Group('csv-v2')]
    public function testVerticalTabBeforeEnclosure(): void
    {
        $this->assertSame([['a' => "x\ny", 'b' => '2']], (new Csv())->fromString("a,b\n\v\"x\ny\",2\n")->toArray());
    }

    #[Group('csv-v2')]
    public function testWhitespaceOnlyHeaderIsNotSkipped(): void
    {
        $this->assertSame('[{" ":"x"}]', (new Csv())->fromString(" \nx\n")->convert());
    }

    #[Group('csv-v2')]
    public function testNumericKeepsValuesThatWouldChange(): void
    {
        $csv = (new Csv())->setConversionKey('numeric', true)
            ->fromString("a,b,c,d,e,f,g\n+0123,+5,1e-400,9007199254740993.5,1.5,1e3,0.0\n");
        $this->assertSame(
            [[
                'a' => '+0123', 'b' => '+5', 'c' => '1e-400', 'd' => '9007199254740993.5',
                'e' => 1.5, 'f' => 1000.0, 'g' => 0.0,
            ]],
            $csv->toArray()
        );
    }

    #[Group('csv-v2')]
    public function testToArray(): void
    {
        $this->assertSame([['a' => '1', 'b' => ['c' => '2']]], (new Csv())->fromString("a,b.c\n1,2\n")->toArray());
    }

    #[Group('csv-v2')]
    public function testJsonLinesDownloadHeaders(): void
    {
        $csv = (new Csv())->setConversionKey('lines', true);
        $this->assertSame('application/jsonl; charset=utf-8', $csv->getDownloadHeaders('x', 0)['Content-Type']);
        $disposition = $csv->getDownloadHeaders('x', 0)['Content-Disposition'];
        $this->assertStringContainsString('filename="x.jsonl"', $disposition);
    }

    #[Group('csv-v2-features')]
    public function testDefaultJoinIsDot(): void
    {
        $csv = (new Csv())->fromString("first_name,a.b\nx,1\n");
        $this->assertSame('[{"first_name":"x","a":{"b":"1"}}]', $csv->convert());
    }

    #[Group('csv-v2-features')]
    #[DataProvider('jsonOptionCases')]
    public function testOutputMatchesJsonEncode(int $options): void
    {
        $csv = (new Csv())->setConversionKey('options', $options)->fromString("0,1,a.b\nx,y,1\n,,2\n");
        $expected = [(object) ['0' => 'x', '1' => 'y', 'a' => ['b' => '1']], (object) ['a' => ['b' => '2']]];
        $this->assertSame(json_encode($expected, $options), $csv->convert());
        $headerOnly = (new Csv())->setConversionKey('options', $options)->fromString("a\n");
        $this->assertSame(json_encode([], $options), $headerOnly->convert());
    }

    public static function jsonOptionCases(): array
    {
        return [
            'none' => [0],
            'pretty' => [JSON_PRETTY_PRINT],
            'force object' => [JSON_FORCE_OBJECT],
            'pretty force object' => [JSON_PRETTY_PRINT | JSON_FORCE_OBJECT],
        ];
    }

    #[Group('csv-v2-features')]
    #[DataProvider('convertFileCases')]
    public function testConvertFileGivesSameOutputAsConvert(string $input, int $options, bool $lines): void
    {
        $in = $this->tempPath('csv');
        file_put_contents($in, $input);
        $out = $this->tempPath('json');
        $csv = (new Csv())->setConversionKey('options', $options)->setConversionKey('lines', $lines);
        $expected = $csv->fromString($input)->convert();
        $this->assertSame(strlen($expected), $csv->convertFile($in, $out, 7));
        $this->assertStringEqualsFile($out, $expected);
    }

    public static function convertFileCases(): array
    {
        $tricky = "\xEF\xBB\xBFa,b.c\r\n\"x\r\ny\",\"q\"\"\"\"\"\r\n\"\",z\r1,2";
        $large = "id,text\n";
        for ($i = 0; $i < 3000; $i++) {
            $large .= $i . ',"' . str_repeat("line\n", $i % 4) . '"' . "\n";
        }
        return [
            'tricky' => [$tricky, 0, false],
            'tricky pretty' => [$tricky, JSON_PRETTY_PRINT, false],
            'tricky force object' => [$tricky, JSON_FORCE_OBJECT | JSON_PRETTY_PRINT, false],
            'tricky lines' => [$tricky, 0, true],
            'large' => [$large, 0, false],
            'header only' => ["a,b\n", 0, false],
            'empty lines' => ['', 0, true],
        ];
    }

    #[Group('csv-v2-features')]
    public function testConvertFileDoesNotLeavePartialOutput(): void
    {
        $in = $this->tempPath('csv');
        file_put_contents($in, "a\n1\n\"unterminated\n");
        $out = $this->tempPath('json');
        try {
            (new Csv())->convertFile($in, $out);
            $this->fail('No exception');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }
        $this->assertFileDoesNotExist($out);
        $this->assertSame([], glob($out . '*') ?: []);
    }

    #[Group('csv-v2-final')]
    public function testDefaultJsonOptionsKeepUnicodeAndSlashes(): void
    {
        $this->assertSame('[{"a":"日本 a/b"}]', (new Csv())->fromString("a\n日本 a/b\n")->convert());
        $escaped = (new Csv())->setConversionKey('options', 0)->fromString("a\n日本 a/b\n");
        $this->assertSame('[{"a":"\u65e5\u672c a\/b"}]', $escaped->convert());
    }

    #[Group('csv-v2-final')]
    #[DataProvider('utf16Inputs')]
    public function testUtf16InputGivesClearError(string $input): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('UTF-8');
        (new Csv())->fromString($input)->convert();
    }

    public static function utf16Inputs(): array
    {
        return [
            'UTF-16 LE' => ["\xFF\xFEa\x00\n\x00"],
            'UTF-16 BE' => ["\xFE\xFF\x00a\x00\n"],
            'invalid UTF-8' => ["a\n\xFF\n"],
        ];
    }

    #[Group('csv-v2-review')]
    public function testDepthErrorHasNoUtf8Hint(): void
    {
        try {
            (new Csv())->fromString(str_repeat('a.', 600) . "b\n1\n")->convert();
            $this->fail('No exception');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('UTF-8', $e->getMessage());
        }
    }

    #[Group('csv-v2-review')]
    public function testEscapedEnclosureBeforeNewlineInQuotedField(): void
    {
        $csv = (new Csv())->setConversionKey('escape', '\\')->fromString("a,b\n\"x\\\"\ny\",z\n");
        $this->assertSame([['a' => "x\\\"\ny", 'b' => 'z']], $csv->toArray());
    }

    #[Group('csv-v2-review')]
    public function testConvertFileWithTinyChunks(): void
    {
        $input = "\xEF\xBB\xBFa,b\r\n\"x\r\ny\",\"q\"\"\"\r\n1,2";
        $in = $this->tempPath('csv');
        file_put_contents($in, $input);
        $out = $this->tempPath('json');
        $expected = (new Csv())->fromString($input)->convert();
        foreach ([1, 2, 3, 4, 5] as $chunkSize) {
            (new Csv())->convertFile($in, $out, $chunkSize);
            $this->assertStringEqualsFile($out, $expected, "chunk size $chunkSize");
        }
    }
}
