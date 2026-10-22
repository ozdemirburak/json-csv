# JSON to CSV and CSV to JSON Converter Library in PHP

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-github-actions]][link-github-actions]
[![Total Downloads][ico-downloads]][link-downloads]

The most basic CSV to JSON and JSON to CSV converter library in PHP without any dependencies.

## Install

Via Composer

```bash
composer require ozdemirburak/json-csv
```

Requires PHP 8.4 or higher.

## Usage

### JSON to CSV Converter

```php
use OzdemirBurak\JsonCsv\File\Json;

// From a file, a string or a PHP array
$json = new Json(__DIR__ . '/file.json');
$json = (new Json())->fromString('{"name": "John", "age": 30}');
$json = Json::fromArray([['name' => 'John', 'age' => 30]]);

// Set options (fluent), then convert to a CSV string
$csvString = $json->setConversionKey('bom', true)->convert();

// Convert and save, returns the number of bytes written
$json->convertAndSave(__DIR__ . '/file.csv');

// Convert and send as a download (calls exit() unless the second argument is false)
$json->convertAndDownload('export');

// Or get the download headers for a framework response
$headers = $json->getDownloadHeaders('export', strlen($csvString));

// Large JSON Lines files: convert file to file with constant memory
(new Json())->setConversionKey('lines', true)->convertFile(__DIR__ . '/big.jsonl', __DIR__ . '/big.csv');
```

Assume that the input JSON is something like below.

```json
[
  {
    "name": {
      "common": "Türkiye",
      "official": "Republic of Türkiye",
      "native": "Türkiye"
    },
    "area": 783562,
    "latlng": [39, 35]
  },
  {
    "name": {
      "common": "Japan",
      "official": "Japan",
      "native": "日本"
    },
    "area": 377975,
    "latlng": [36, 138]
  }
]
```

After the conversion, the resulting CSV data will look like below.

| name.common | name.official       | name.native | area   | latlng.0 | latlng.1 |
|-------------|---------------------|-------------|--------|----------|----------|
| Türkiye     | Republic of Türkiye | Türkiye     | 783562 | 39       | 35       |
| Japan       | Japan               | 日本        | 377975 | 36       | 138      |

#### How JSON becomes rows

- A list of objects becomes one row per object. The header has the union of all keys, in the order they first appear.
- Nested keys are joined with the `join` character (`name.common`), and lists get numeric indexes (`latlng.0`).
- With the default `rows` option `auto`, an object whose values are all objects or lists, where at least one of them has a nested object or list, becomes one row per value, for example records keyed by id with nested fields. The top-level keys are not in the output. Any other object becomes a single row. Set `rows` to `each` or `single` to choose.
- `true` becomes `1`, and `false` and `null` become an empty cell. Use the `true`, `false` and `null` options to change this.

#### JSON Conversion Options

| Option          | Type   | Default | Description |
|-----------------|--------|---------|-------------|
| `delimiter`     | string | `,`     | CSV field delimiter, one character |
| `enclosure`     | string | `"`     | CSV field enclosure, one character |
| `escape`        | string | `''`    | CSV escape character. `''` gives RFC 4180 output; set `\` for the 1.x behavior, but then a value that ends in `\` cannot be read back (a PHP `fputcsv` limit) |
| `join`          | string | `.`     | Joins nested keys. Set `_` for the 1.x behavior |
| `null`          | scalar or null | `null` | Value for null and missing fields |
| `true`          | scalar or null | `true` | Value for `true` (written as `1`), e.g. `'true'` |
| `false`         | scalar or null | `false` | Value for `false` (written as an empty cell), e.g. `'false'` |
| `rows`          | string | `auto`  | `each`: one row per item of the top-level list or object. `single`: the whole input is one row. `auto`: see above |
| `bom`           | bool   | `false` | Add a UTF-8 byte order mark to the output, e.g. for Excel |
| `sanitize`      | bool   | `false` | Prefix cells that start with `=`, `+`, `-`, `@`, tab or carriage return with `'`, to prevent CSV formula injection in spreadsheet apps. Numbers do not change |
| `lines`         | bool   | `false` | Read the input as JSON Lines (one JSON object per line, `\n` or `\r\n` line endings). Not used for `fromArray()` |

### CSV to JSON Converter

```php
use OzdemirBurak\JsonCsv\File\Csv;

$csv = new Csv(__DIR__ . '/file.csv');
$csv->setConversionKey('options', JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Convert to a JSON string, or to a PHP array
$jsonString = $csv->convert();
$rows = $csv->toArray();

// Convert and save, or send as a download
$csv->convertAndSave(__DIR__ . '/file.json');
$csv->convertAndDownload();

// Large files: convert file to file with constant memory
(new Csv())->convertFile(__DIR__ . '/big.csv', __DIR__ . '/big.json');

// From a string
$jsonString = (new Csv())->fromString("name,age\nJohn,30\n")->convert();
```

Assume that the input CSV file is something like below.

| SepalLength | SepalWidth  | PetalLength  | PetalWidth | Name            |
|-------------|-------------|--------------|------------|-----------------|
| 5.1         | 3.5         | 1.4          | 0.2        | Iris-setosa     |
| 7.0         | 3.2         | 4.7          | 1.4        | Iris-versicolor |
| 6.3         | 3.3         | 6.0          | 2.5        | Iris-virginica  |

After the conversion, the resulting JSON data will look like below.

```json
[
    {
        "SepalLength": "5.1",
        "SepalWidth": "3.5",
        "PetalLength": "1.4",
        "PetalWidth": "0.2",
        "Name": "Iris-setosa"
    },
    {
        "SepalLength": "7.0",
        "SepalWidth": "3.2",
        "PetalLength": "4.7",
        "PetalWidth": "1.4",
        "Name": "Iris-versicolor"
    },
    {
        "SepalLength": "6.3",
        "SepalWidth": "3.3",
        "PetalLength": "6.0",
        "PetalWidth": "2.5",
        "Name": "Iris-virginica"
    }
]
```

Values after the last header column are not included. Headers that contain the `join` character become nested objects (`name.common` gives `{"name": {"common": ...}}`). If your headers use `.` in names, set `join` to a character that is not in them.

#### CSV Conversion Options

| Option       | Type   | Default | Description |
|--------------|--------|---------|-------------|
| `delimiter`  | string | `,`     | CSV field delimiter, one character |
| `enclosure`  | string | `"`     | CSV field enclosure, one character |
| `escape`     | string | `''`    | CSV escape character. `''` reads RFC 4180 input; set `\` for the 1.x behavior |
| `join`       | string | `.`     | Splits headers into nested keys. Set `_` for the 1.x behavior |
| `numeric`    | bool   | `false` | Convert numeric strings to numbers. Values that would change stay strings: leading zeros or `+`, surrounding spaces, more than 15 significant digits, and values out of range |
| `keep_empty` | bool   | `false` | Keep empty cells as `""`. By default empty cells are not included |
| `lines`      | bool   | `false` | Write JSON Lines (one JSON object per line). Downloads then use the `.jsonl` extension |
| `options`    | int    | `JSON_UNESCAPED_UNICODE \| JSON_UNESCAPED_SLASHES` | `json_encode` flags, e.g. add `JSON_PRETTY_PRINT`. Set `0` for the 1.x behavior |

### Large files

`convertFile($in, $out)` reads the input file and writes the output file without loading everything into memory, for CSV input and for JSON Lines input (`lines` option). Memory use depends on the longest row (and for JSON Lines on the number of columns), not on the file size. A JSON array is read into memory. The output is the same as `convert()`. `Csv::convertFile()` has an optional third argument, the read size in bytes (default 65536).

`convertFile()` and `convertAndSave()` write a local file to a temporary file in the same directory first and then rename it, so a failed conversion does not leave a partial file and an existing file stays as it was. The file permissions are kept, and for a symlink the target file is written. Stream wrappers such as `php://output` and hard-linked files are written directly.

For 1 million rows on a laptop (PHP 8.5):

| Conversion                 | `convert()`       | `convertFile()`  |
|----------------------------|-------------------|------------------|
| CSV to JSON (67 MB)        | 3.9 s, 480 MB     | 3.7 s, 2.3 MB    |
| JSON Lines to CSV (189 MB) | 11.9 s, 2.5 GB    | 10.8 s, 2.3 MB   |

### Errors

- `setConversionKey()` throws `InvalidArgumentException` for an unknown option, a value of the wrong type, an invalid `delimiter` or `enclosure` (one character, not a line break; the enclosure also not whitespace), an invalid `escape` (empty or one character), an empty `join`, or a `delimiter` that is the same as the `enclosure` or `escape`.
- `convert()` throws `RuntimeException` for invalid input, for example input that is not UTF-8 (a UTF-16 or UTF-32 file gives a clear message), invalid JSON, CSV with an unterminated enclosure, numbers out of range, or data where two values get the same key (duplicate CSV headers, or JSON `{"a_b": 1, "a": {"b": 2}}`).
- `convertAndSave()` and `convertFile()` throw `RuntimeException` if a file cannot be read or written.

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Testing

```bash
composer test         # PHPUnit
composer check-style  # PSR-12
composer analyse      # PHPStan
composer ci           # all of the above
```

## Credits

- [Burak Özdemir][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/ozdemirburak/json-csv.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-github-actions]: https://img.shields.io/github/actions/workflow/status/ozdemirburak/json-csv/tests.yml?branch=master&style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/ozdemirburak/json-csv.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/ozdemirburak/json-csv
[link-github-actions]: https://github.com/ozdemirburak/json-csv/actions/workflows/tests.yml
[link-downloads]: https://packagist.org/packages/ozdemirburak/json-csv
[link-author]: https://github.com/ozdemirburak
[link-contributors]: ../../contributors
