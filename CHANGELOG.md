# Changelog

All Notable changes to `json-csv` will be documented in this file.

## 2.0.1 - 2026-10-23

- Updated the author homepage to https://ozdemir.dev and the license year.

## 2.0.0 - 2026-10-23

### Breaking changes

- Minimum PHP version is now 8.4. Dev dependencies: PHPUnit 13, PHP_CodeSniffer 4 (PSR-12), PHPStan 2.
- `Json` and `Csv` are now `final`, and their helper methods are private.
- `setConversionKey()` now returns the converter (fluent), not the options array. Use `getConversion()` to read the options.
- `setConversionKey()` throws `InvalidArgumentException` for an unknown key, a value of the wrong type, and invalid `delimiter`, `enclosure` (also whitespace), `escape` and `join` values. `extension` and `type` are no longer options.
- `convertAndSave()` returns `int` and throws `RuntimeException` on failure (it returned `false`). A local file is now written to a temporary file in the same directory and renamed, so the directory must be writable (permissions and symlinks are kept; stream wrappers and hard links are written directly).
- The `utf8_encoding` option is now `bom`, because it only adds a byte order mark.
- `convertAndDownload()` throws `RuntimeException` if headers were already sent.
- The default `join` is now `.` for both converters, so CSV headers such as `first_name` are not split into nested objects. Set `join` to `_` to get the 1.x behavior.
- The default CSV to JSON `options` is now `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`, so `日本` and `a/b` are not escaped. Set `options` to `0` to get the 1.x behavior.
- The default `escape` is now `''` (RFC 4180) for both converters. Set `escape` to `\` to get the 1.x behavior.
- The `null` option now also replaces explicit `null` values, as documented.
- Invalid input now throws `RuntimeException`: scalar JSON, a list with both scalars and objects, numbers out of range, CSV with an unterminated enclosure, and key collisions (duplicate CSV headers, one row with values for both `a` and `a_b`, or JSON `{"a_b": 1, "a": {"b": 2}}`). Before, these gave a `TypeError` or lost data silently.
- An object with scalars next to an array of objects is now one row (it was a `TypeError`).
- CSV rows whose headers are all numbers (`0,1`) now give JSON objects, not lists.
- Floats are written with full precision in both directions, independent of the `serialize_precision` ini setting.
- With `numeric`, values that would change stay strings: leading zeros or `+`, surrounding spaces, more than 15 significant digits, and values out of range.
- A single empty CSV cell is written as `""`, not as a blank line.
- Download headers: `Content-Type` now has `; charset=utf-8`, and an empty filename becomes `download`.

### Added

- `convertFile($in, $out)` converts large CSV and JSON Lines files with constant memory (2.3 MB for 1 million rows). The output file is replaced only when the conversion succeeds; `convertAndSave()` now works the same way.
- `rows` option for JSON to CSV: `auto` (default), `each` or `single`.
- `true` and `false` options for JSON to CSV, to write booleans as e.g. `true`/`false`.
- `Json::fromArray()` converts a PHP array without JSON encoding.
- `Csv::toArray()` returns PHP arrays without JSON encoding.
- `getDownloadHeaders()` returns the download headers, e.g. for a framework response.
- `getConversion()` returns the current options.
- `keep_empty` option for CSV to JSON, to keep empty cells as `""`.
- `sanitize` option for JSON to CSV, to prevent CSV formula injection.
- `lines` option for JSON Lines input (JSON to CSV) and output (CSV to JSON). Downloads use `.jsonl` and `application/jsonl`. Lines end with `\n` or `\r\n`.

### Fixed

- CSV rows were merged or lost when a value had an escaped enclosure (`\"`), a stray quote (`5'10"`), or a value that ends in `\`.
- Carriage returns inside quoted CSV values were changed to `\n`.
- A UTF-8 BOM in CSV or JSON input broke the first key or gave an error.
- Columns moved for numeric JSON keys and lists of lists.
- Big integers and floats lost precision in CSV output.
- Blank lines and whitespace before a CSV header or an enclosure were handled differently from `str_getcsv`.
- Empty JSON input, rows without keys and empty nested objects gave blank lines or errors.
- Repeated empty CSV headers lost values silently.
- A directory path was read as a file.
- UTF-16 and UTF-32 input gave a parse error; now the error says to convert the input to UTF-8.

### Performance

- CSV to JSON is about 3 times faster and uses about 4 times less memory (1 million rows: 12.1 s and 1.9 GB before, 3.9 s and 480 MB after).
- JSON Lines to CSV is about 3.5 times faster (1 million rows: 39.9 s and 3.8 GB before, 11.9 s and 2.5 GB after).
- The cycle collector is off during a conversion. Converted data has no reference cycles, and the collector runs scanned all rows for nothing.

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
