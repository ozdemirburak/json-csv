<?php

namespace OzdemirBurak\JsonCsv\Tests;

use OzdemirBurak\JsonCsv\File\Csv;
use OzdemirBurak\JsonCsv\Tests\Traits\TestTrait;
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
        $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $conversion = $this->initCsv()->setConversionKey('options', $options);
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

    #[Group('csv-conversion-download-save-test')]
    public function testConversionAndDownload(): void
    {
        $this->initCsv()->convertAndDownload(null, false);
        $this->expectOutputRegex('/{"SL":"6.3","SW":"3.3","PL":"6.0","PW":"2.5","Name":"Iris-virginica"}/');
    }

    #[Group('csv-conversion-download-save-test')]
    public function testConversionAndSave(): void
    {
        $path = $this->path('iris.out', 'json');
        $this->initCsv()->convertAndSave($path);
        $this->assertFileExists($path);
        $json = '{"SL":"6.3","SW":"3.3","PL":"6.0","PW":"2.5","Name":"Iris-virginica"}';
        $this->assertStringContainsString($json, file_get_contents($path));
        unlink($path);
        $this->assertFileDoesNotExist($path);
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
        $csv = "user_name,user_email,address_city\nJohn,john@test.com,NYC\n";
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
        $csv->fromString("user.name,user.email\nJohn,john@test.com\n");
        $csv->setConversionKey('join', '.');
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
        $csv = "a_b_c_d\nvalue\n";
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
    public function testGetDataMethod(): void
    {
        $csv = new Csv();
        $csv->fromString("a,b\n1,2\n");
        $this->assertEquals("a,b\n1,2\n", $csv->getData());
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
        $csv = "a_b_c_d_e\nvalue\n";
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
    public function testMixedEmptyAndNonEmpty(): void
    {
        $csv = "a,b,c\n1,,3\n";
        $result = (new Csv())->fromString($csv)->convert();
        $decoded = json_decode($result, true);
        $this->assertEquals('1', $decoded[0]['a']);
        $this->assertArrayNotHasKey('b', $decoded[0]);
        $this->assertEquals('3', $decoded[0]['c']);
    }

    #[Group('csv-edge-cases')]
    public function testJsonUnescapedSlashes(): void
    {
        $csv = new Csv();
        $csv->fromString("url\nhttps://example.com/path\n");
        $csv->setConversionKey('options', JSON_UNESCAPED_SLASHES);
        $result = $csv->convert();
        $this->assertStringContainsString('https://example.com/path', $result);
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
}
