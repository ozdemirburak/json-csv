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

Note: Requires PHP 8.1 or higher with JSON extension

## Usage

### JSON to CSV Converter

```php
use OzdemirBurak\JsonCsv\File\Json;

// JSON to CSV
$json = new Json(__DIR__ . '/file.json');
// To convert JSON to CSV string
$csvString = $json->convert();
// To set a conversion option then convert JSON to CSV and save
$json->setConversionKey('utf8_encoding', true);
$json->convertAndSave(__DIR__ . '/file.csv');
// To convert JSON to CSV and force download on browser
$json->convertAndDownload();
```

You can also convert directly from a JSON string using the `fromString` method.

```php
$csvString = (new Json())->fromString('{"name": "John", "age": 30}')->convert();
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

| name_common  | name_official        | name_native  | area       | latlng_0 | latlng_1  |
|--------------|----------------------|--------------|------------|----------|-----------|
| Türkiye      | Republic of Türkiye  | Türkiye      | 783562     | 39       | 35        |
| Japan        | Japan                | 日本           | 377975     | 36       | 138       |

#### JSON Conversion Options

| Option          | Type   | Default   | Description                          |
|-----------------|--------|-----------|--------------------------------------|
| `delimiter`     | string | `,`       | CSV field delimiter                  |
| `enclosure`     | string | `"`       | CSV field enclosure character        |
| `escape`        | string | `\`       | CSV escape character                 |
| `join`          | string | `_`       | Character used to join nested keys   |
| `null`          | mixed  | `null`    | Value to use for null/missing fields |
| `utf8_encoding` | bool   | `false`   | Add UTF-8 BOM to output              |

### CSV to JSON Converter

```php
use OzdemirBurak\JsonCsv\File\Csv;

// CSV to JSON
$csv = new Csv(__DIR__ . '/file.csv');
$csv->setConversionKey('options', JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
// To convert CSV to JSON string
$jsonString = $csv->convert();
// To convert CSV to JSON and save
$csv->convertAndSave(__DIR__ . '/file.json');
// To convert CSV to JSON and force download on browser
$csv->convertAndDownload();
```

You can also convert directly from a CSV string using the `fromString` method.

```php
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

#### CSV Conversion Options

| Option      | Type   | Default   | Description                                            |
|-------------|--------|-----------|--------------------------------------------------------|
| `delimiter` | string | `,`       | CSV field delimiter                                    |
| `enclosure` | string | `"`       | CSV field enclosure character                          |
| `escape`    | string | `\`       | CSV escape character                                   |
| `join`      | string | `_`       | Character used to split nested keys                    |
| `numeric`   | bool   | `false`   | Set to `true` to convert numeric strings to numbers    |
| `options`   | int    | `0`       | JSON encode flags (e.g., `JSON_PRETTY_PRINT`)          |

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Testing

```bash
composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

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
