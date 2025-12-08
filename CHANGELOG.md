# Changelog

All Notable changes to `json-csv` will be documented in this file.

## 1.0.0

- Minimum PHP version is now 8.1, and updated PHPUnit to version 10/11.
- Modernized codebase with PHP 8.1+ features (typed properties, union types, `static` return type).
- Replaced `numbers` option with boolean `numeric` option for CSV to JSON conversion.
- Improved handling of edge cases (empty arrays, nested structures, arrays of scalars).
- Added support for CSV fields containing newlines within quoted values.
- Better handling of different line endings (Unix, Windows, old Mac).
- Added comprehensive test suite with 85+ tests covering edge cases.
- Removed Travis CI and updated GitHub Actions workflow.

## 0.4.0
- Remove support for PHP 7.2.
- Added PHP 8.0 support.

## 0.3.0
- Remove support for PHP 7.1.
- Added utf-8 support for csv conversion.

## 0.2.0
- Remove support for PHP 7.0.
- Better CSV to JSON conversion method.

## 0.1.1
- Fix error with assoc. arrays (splat operator).

## 0.1.0
- Fix property error in JSON to CSV conversion.

## 0.0.1
- Initial release with basic conversion methods.
