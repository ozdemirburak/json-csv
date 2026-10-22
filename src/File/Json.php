<?php

namespace OzdemirBurak\JsonCsv\File;

use OzdemirBurak\JsonCsv\AbstractFile;
use RuntimeException;

final class Json extends AbstractFile
{
    protected array $conversion = [
        'delimiter' => ',',
        'enclosure' => '"',
        'escape' => '',
        'join' => '.',
        'null' => null,
        'true' => true,
        'false' => false,
        'rows' => 'auto',
        'bom' => false,
        'sanitize' => false,
        'lines' => false,
    ];

    /** @var array<mixed>|null Data from fromArray(), used instead of decoding $data */
    private ?array $array = null;

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $json = new self();
        $json->array = $data;
        return $json;
    }

    public function fromString(string $dataString): static
    {
        $this->array = null;
        return parent::fromString($dataString);
    }

    #[\Override]
    public function convert(): string
    {
        return $this->withConversionSettings(function (): string {
            // The lines option is about the input string, not about fromArray() data
            $lines = $this->array === null && $this->conversion['lines'];
            $parts = [];
            // No local variable for the data, so that writeData() can free each row after it is flattened
            $this->writeData(
                $this->array ?? ($lines ? $this->decodeLines($this->data) : $this->decode($this->data)),
                $lines,
                static function (string $csv) use (&$parts): void {
                    $parts[] = $csv;
                }
            );
            return implode('', $parts);
        });
    }

    #[\Override]
    public function convertFile(string $in, string $out): int
    {
        return $this->withConversionSettings(
            fn(): int => $this->writeFile($out, fn(callable $write) => $this->writeFileData($in, $write))
        );
    }

    /**
     * @param callable(string): void $write
     */
    private function writeFileData(string $in, callable $write): void
    {
        if (!$this->conversion['lines']) {
            $handle = $this->openForReading($in);
            $json = stream_get_contents($handle);
            fclose($handle);
            if ($json === false) {
                throw new RuntimeException("Failed to read file: $in");
            }
            $this->writeData($this->decode($json), false, $write);
            return;
        }
        // Two passes, so that memory use depends on the number of columns, not on the number of rows:
        // the first pass finds all columns for the header, the second pass writes the rows
        $default = [];
        foreach ($this->streamLines($in) as $row) {
            $default += array_fill_keys(array_keys($row), $this->conversion['null']);
        }
        $this->writeRows($this->streamLines($in), $default, $write);
    }

    /**
     * @param callable(string): void $write
     */
    private function writeData(mixed $data, bool $lines, callable $write): void
    {
        if ($data === null || $data === []) {
            return;
        }
        if (!is_array($data)) {
            throw new RuntimeException('JSON data must be an object or an array');
        }
        $rows = $lines ? 'each' : $this->conversion['rows'];
        if ($rows === 'single' || ($rows === 'auto' && !$this->hasRows($data))) {
            $row = [];
            $this->flatten($data, '', $row);
            $this->writeRows([$row], $row, $write);
            return;
        }
        $data = array_values($data);
        $flattened = [];
        $default = [];
        $null = $this->conversion['null'];
        for ($index = 0, $count = count($data); $index < $count; $index++) {
            $flat = $this->flattenRow($data[$index]);
            // Free each decoded row as soon as it is flattened
            unset($data[$index]);
            // The + operator keeps numeric keys and the first-seen column order
            $default += array_fill_keys(array_keys($flat), $null);
            $flattened[] = $flat;
        }
        $this->writeRows($flattened, $default, $write);
    }

    /**
     * @return array<string|int, scalar|null>
     */
    private function flattenRow(mixed $row): array
    {
        if (!is_array($row)) {
            throw new RuntimeException('JSON rows must all be objects or arrays');
        }
        $flat = [];
        $this->flatten($row, '', $flat);
        return $flat;
    }

    private function decode(string $json, string $context = ''): mixed
    {
        $json = $this->removeBom($json);
        if (trim($json) === '') {
            return null;
        }
        try {
            return json_decode($json, true, 512, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException('Invalid JSON data' . $context . ': ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Decodes JSON Lines (one JSON value per line) into a list. Lines end with \n or \r\n,
     * a bare \r is JSON whitespace.
     *
     * @return list<mixed>
     */
    private function decodeLines(string $data): array
    {
        $rows = [];
        foreach (explode("\n", $data) as $index => $line) {
            $line = rtrim($line, "\r");
            if (trim($line) !== '') {
                $rows[] = $this->decode($line, ' on line ' . ($index + 1));
            }
        }
        return $rows;
    }

    /**
     * Reads JSON Lines one line at a time, and gives the flattened rows.
     *
     * @return \Generator<int, array<string|int, scalar|null>>
     */
    private function streamLines(string $path): \Generator
    {
        $handle = $this->openForReading($path);
        try {
            $number = 0;
            while (($line = fgets($handle)) !== false) {
                $number++;
                $line = rtrim($line, "\r\n");
                if (trim($line) !== '') {
                    yield $this->flattenRow($this->decode($line, ' on line ' . $number));
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Writes the header and the rows as CSV, in parts of about 64 KB.
     *
     * @param iterable<array<string|int, scalar|null>> $rows
     * @param array<string|int, scalar|null> $default All columns in order, the value for a missing column
     *                                                 in a row
     * @param callable(string): void $write
     */
    private function writeRows(iterable $rows, array $default, callable $write): void
    {
        if ($default === []) {
            return;
        }
        // php://temp moves to a temporary file after 2 MB, which makes large outputs much slower
        $buffer = fopen('php://memory', 'w+b');
        if ($buffer === false) {
            throw new RuntimeException('Failed to open temporary stream');
        }
        $flush = static function () use ($buffer, $write): void {
            rewind($buffer);
            $write((string) stream_get_contents($buffer));
            rewind($buffer);
            ftruncate($buffer, 0);
        };
        try {
            if ($this->conversion['bom']) {
                fwrite($buffer, "\xEF\xBB\xBF");
            }
            $this->putCsv($buffer, array_keys($default));
            foreach ($rows as $row) {
                // array_replace keeps the column order of $default, all row keys are in $default
                $this->putCsv($buffer, array_replace($default, $row));
                if (ftell($buffer) >= 65536) {
                    $flush();
                }
            }
            $flush();
        } finally {
            fclose($buffer);
        }
    }

    /**
     * Writes into $result by reference, so nested arrays do not copy the result for each level.
     *
     * @param array<mixed> $array
     * @param array<string|int, scalar|null> $result
     */
    private function flatten(array $array, string $prefix, array &$result): void
    {
        foreach ($array as $key => $value) {
            $newKey = $prefix . $key;
            if (array_key_exists($newKey, $result)) {
                throw new RuntimeException("Key collision: more than one value for column '$newKey'");
            }
            if (is_array($value)) {
                $this->flatten($value, $newKey . $this->conversion['join'], $result);
            } elseif ($value === null) {
                $result[$newKey] = $this->conversion['null'];
            } elseif (is_bool($value)) {
                $result[$newKey] = $this->conversion[$value ? 'true' : 'false'];
            } elseif (is_float($value)) {
                if (!is_finite($value)) {
                    throw new RuntimeException("Number out of range in column '$newKey'");
                }
                $result[$newKey] = json_encode($value);
            } elseif (is_scalar($value)) {
                $result[$newKey] = $value;
            } else {
                $type = get_debug_type($value);
                throw new RuntimeException("Unsupported value of type $type in column '$newKey'");
            }
        }
    }

    /**
     * @param resource $handle
     * @param array<string|int, scalar|null> $fields
     */
    private function putCsv($handle, array $fields): void
    {
        if ($this->conversion['sanitize']) {
            $fields = array_map($this->sanitize(...), $fields);
        }
        $enclosure = (string) $this->conversion['enclosure'];
        // fputcsv writes one empty field as a blank line, which CSV readers skip
        if (count($fields) === 1 && in_array(reset($fields), ['', null, false], true)) {
            fwrite($handle, $enclosure . $enclosure . "\n");
            return;
        }
        fputcsv(
            $handle,
            $fields,
            (string) $this->conversion['delimiter'],
            $enclosure,
            (string) $this->conversion['escape']
        );
    }

    /**
     * Prevents CSV formula injection: spreadsheet apps run cells that start with these characters.
     */
    private function sanitize(string|int|float|bool|null $field): string|int|float|bool|null
    {
        if (is_string($field) && !is_numeric($field) && preg_match('/^[=+\-@\t\r]/', $field) === 1) {
            return "'" . $field;
        }
        return $field;
    }

    /**
     * The 'auto' row rule: a list of objects/arrays is converted to one CSV row per item. An object is also converted
     * to one row per value when all its values are objects/arrays and at least one of them has a
     * nested object/array, e.g. records keyed by id with nested fields. The top-level keys are not
     * in the output. Anything else is converted to a single row.
     *
     * @param array<mixed> $data
     */
    private function hasRows(array $data): bool
    {
        $isArray = static fn(mixed $value): bool => is_array($value);
        if (array_is_list($data)) {
            return array_any($data, $isArray);
        }
        return array_all($data, $isArray)
            && array_any($data, static fn(mixed $value): bool => is_array($value) && array_any($value, $isArray));
    }

    protected function getExtension(): string
    {
        return 'csv';
    }

    protected function getMimeType(): string
    {
        return 'text/csv';
    }
}
