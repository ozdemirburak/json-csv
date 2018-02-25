# JSON to CSV and CSV to JSON Converter

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-travis]][link-travis]
[![Total Downloads][ico-downloads]][link-downloads]

The most basic CSV to JSON and JSON to CSV converter library in PHP without any dependencies.

## Install

Via Composer

``` bash
$ composer require ozdemirburak/json-csv
```

## Usage

### JSON to CSV Converter

``` php
use OzdemirBurak\JsonCsv\File\Json;

// JSON to CSV
$json = new Json(__DIR__ . '/above.json');
// To convert JSON to CSV string
$csvString = $json->convert();
// To convert JSON to CSV and save
$json->convertAndSave(__DIR__ . '/above.csv');
// To convert JSON to CSV and force download on browser
$json->convertAndDownload();
```

Assume that the input JSON is something like below. 

```json
[
  {
    "name": {
      "common": "Turkey",
      "official": "Republic of Turkey",
      "native": "T\u00fcrkiye"
    },
    "area": 783562,
    "latlng": [39, 35]
  },
  {
    "name": {
      "common": "Israel",
      "official": "State of Israel",
      "native": "\u05d9\u05e9\u05e8\u05d0\u05dc"
    },
    "area": 20770,
    "latlng": [31.30, 34.45]
  }
]
```

After the conversion, the resulting CSV data will look like below.

| name_common,name_official,name_native,area,latlng_0,latlng_1 | 
|--------------------------------------------------------------| 
| Turkey,"Republic of Turkey",Türkiye,783562,39,35             | 
| Israel,"State of Israel",ישראל,20770,31.3,34.45              | 


### CSV to JSON Converter

``` php
use OzdemirBurak\JsonCsv\File\Csv;

// CSV to JSON
$csv = new Csv(__DIR__ . '/below.csv');
$csv->setConversionKey('options', JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
// To convert CSV to JSON string
$jsonString = $csv->convert();
// To convert CSV to JSON and save
$csv->convertAndSave(__DIR__ . '/below.csv');
// To convert CSV to JSON and force download on browser
$csv->convertAndDownload();
```

Assume that the input CSV file is something like below. 

| SepalLength,SepalWidth,PetalLength,PetalWidth,Name | 
|----------------------------------------------------| 
| 5.1,3.5,1.4,0.2,Iris-setosa                        | 
| 7.0,3.2,4.7,1.4,Iris-versicolor                    | 
| 6.3,3.3,6.0,2.5,Iris-virginica                     | 


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

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Testing

``` bash
$ composer test
```

## Known Issues

Currently, it is assumed that each object shares the same properties while converting JSON to CSV. So if one object has a property that the other one does not have, then it will be a major problem.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

- [Burak Özdemir][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/ozdemirburak/json-csv.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/ozdemirburak/json-csv/master.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/ozdemirburak/json-csv.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/ozdemirburak/json-csv
[link-travis]: https://travis-ci.org/ozdemirburak/json-csv
[link-downloads]: https://packagist.org/packages/ozdemirburak/json-csv
[link-author]: https://github.com/ozdemirburak
[link-contributors]: ../../contributors
