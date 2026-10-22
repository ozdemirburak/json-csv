<?php

namespace OzdemirBurak\JsonCsv\File;

use OzdemirBurak\JsonCsv\AbstractFile;
use RuntimeException;

final class Csv extends AbstractFile
{
    protected array $conversion = [
        'options' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        'delimiter' => ',',
        'enclosure' => '"',
        'escape' => '',
        'join' => '.',
        'numeric' => false,
        'keep_empty' => false,
        'lines' => false,
    ];

    #[\Override]
    public function convert(): string
    {
        return $this->withConversionSettings(function (): string {
            $parts = [];
            $this->writeJson($this->rows([$this->data]), static function (string $json) use (&$parts): void {
                $parts[] = $json;
            });
            return implode('', $parts);
        });
    }

    #[\Override]
    public function convertFile(string $in, string $out, int $chunkSize = 65536): int
    {
        $rows = fn(): \Generator => $this->rows($this->readChunks($in, max(1, $chunkSize)));
        return $this->withConversionSettings(
            fn(): int => $this->writeFile($out, fn(callable $write) => $this->writeJson($rows(), $write))
        );
    }

    /**
     * Converts to a list of PHP arrays, without the JSON encoding step.
     *
     * @return list<array<string|int, mixed>>
     */
    public function toArray(): array
    {
        return $this->withConversionSettings(fn(): array => iterator_to_array($this->rows([$this->data]), false));
    }

    /**
     * Writes the rows as a JSON list, or as JSON Lines, one row at a time. The output is the same
     * as json_encode() of the whole list.
     *
     * @param iterable<array<string|int, mixed>> $rows
     * @param callable(string): void $write
     */
    private function writeJson(iterable $rows, callable $write): void
    {
        $options = (int) $this->conversion['options'];
        $lines = (bool) $this->conversion['lines'];
        if ($lines) {
            // JSON Lines needs one object per line, so pretty printing is not possible
            $options &= ~JSON_PRETTY_PRINT;
        }
        $pretty = !$lines && ($options & JSON_PRETTY_PRINT) !== 0;
        $forceObject = !$lines && ($options & JSON_FORCE_OBJECT) !== 0;
        $index = 0;
        foreach ($rows as $row) {
            // The object cast keeps numeric keys like "0" and "1" as an object, not a list
            $json = $this->encode((object) $row, $options);
            if ($lines) {
                $write($json . "\n");
                continue;
            }
            if ($pretty) {
                // One more level of indentation inside the list, JSON strings do not contain raw newlines
                $json = str_replace("\n", "\n    ", $json);
            }
            $key = $forceObject ? '"' . $index . '":' . ($pretty ? ' ' : '') : '';
            $write(($index === 0 ? ($forceObject ? '{' : '[') : ',') . ($pretty ? "\n    " : '') . $key . $json);
            $index++;
        }
        if (!$lines) {
            $close = $forceObject ? '}' : ']';
            $write($index === 0 ? ($forceObject ? '{}' : '[]') : ($pretty ? "\n" : '') . $close);
        }
    }

    /**
     * @param iterable<string> $chunks
     * @return \Generator<int, array<string|int, mixed>>
     */
    private function rows(iterable $chunks): \Generator
    {
        $columns = null;
        foreach ($this->splitRows($chunks) as $line) {
            if ($columns === null) {
                // Skip blank lines before the header
                if ($line !== '') {
                    $columns = $this->prepareColumns($this->parseCsvLine($line));
                }
                continue;
            }
            $row = $this->buildJsonObject($this->parseCsvLine($line), $columns);
            if ($row !== []) {
                yield $row;
            }
        }
    }

    /**
     * @param positive-int $chunkSize
     * @return \Generator<int, string>
     */
    private function readChunks(string $path, int $chunkSize): \Generator
    {
        $handle = $this->openForReading($path);
        try {
            while (!feof($handle)) {
                $chunk = fread($handle, $chunkSize);
                if ($chunk === false) {
                    throw new RuntimeException("Failed to read file: $path");
                }
                if ($chunk !== '') {
                    yield $chunk;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    private function encode(object $data, int $options): string
    {
        try {
            return json_encode($data, $options | JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $hint = $e->getCode() === JSON_ERROR_UTF8 ? ', the input must be UTF-8' : '';
            $message = 'JSON encoding failed: ' . $e->getMessage() . $hint;
            throw new RuntimeException($message, 0, $e);
        }
    }

    /**
     * @param list<string|null> $keys
     * @return list<array{list<string>, string}>
     */
    private function prepareColumns(array $keys): array
    {
        $keys = array_map('strval', $keys);
        $seen = [];
        foreach ($keys as $key) {
            if ($key !== '' && isset($seen[$key])) {
                throw new RuntimeException("Key collision: duplicate header '$key'");
            }
            $seen[$key] = true;
        }
        /** @var non-empty-string $join validated in setConversionKey() */
        $join = $this->conversion['join'];
        return array_map(static function (string $key) use ($join): array {
            $parts = explode($join, $key);
            $leaf = array_pop($parts);
            return [$parts, $leaf];
        }, $keys);
    }

    /**
     * @param list<string|null> $values
     * @param list<array{list<string>, string}> $columns
     * @return array<string|int, mixed>
     */
    private function buildJsonObject(array $values, array $columns): array
    {
        $jsonObject = [];
        $keepEmpty = $this->conversion['keep_empty'];
        $numeric = $this->conversion['numeric'];
        $count = min(count($values), count($columns));
        for ($i = 0; $i < $count; $i++) {
            $value = (string) $values[$i];
            if ($value === '' && !$keepEmpty) {
                continue;
            }
            [$parents, $leaf] = $columns[$i];
            if ($parents === [] && !isset($jsonObject[$leaf])) {
                // Fast path for a header without the join string
                $jsonObject[$leaf] = $numeric ? $this->convertValue($value) : $value;
                continue;
            }
            $this->setNestedValue($jsonObject, $parents, $leaf, $value);
        }
        return $jsonObject;
    }

    /**
     * A header like "a" and a header like "a_b" can both have values in different rows,
     * but not in the same row. Empty header names can repeat, but only one of them can have a value
     * in a row. An empty value (keep_empty) never replaces another value.
     *
     * @param array<string|int, mixed> $target
     * @param list<string> $parents
     */
    private function setNestedValue(array &$target, array $parents, string $leaf, string $value): void
    {
        foreach ($parents as $keyPart) {
            if (isset($target[$keyPart]) && !is_array($target[$keyPart]) && $target[$keyPart] !== '') {
                if ($value === '') {
                    return;
                }
                throw new RuntimeException("Key collision: '$keyPart' has both a value and nested values");
            }
            if (!isset($target[$keyPart]) || !is_array($target[$keyPart])) {
                $target[$keyPart] = [];
            }
            $target = &$target[$keyPart];
        }
        if (isset($target[$leaf]) && $target[$leaf] !== '') {
            if ($value === '') {
                return;
            }
            throw new RuntimeException("Key collision: more than one value for '$leaf'");
        }
        $target[$leaf] = $this->convertValue($value);
    }

    /**
     * Converts only when the number gives back the same value, e.g. "+5", "007", "1e-400" and
     * values with more than 15 significant digits stay strings.
     */
    private function convertValue(string $value): string|int|float
    {
        if (!$this->conversion['numeric'] || !is_numeric($value) || $value !== trim($value)) {
            return $value;
        }
        if (preg_match('/^(\+|-?0\d)/', $value) === 1) {
            return $value;
        }
        $number = $value + 0;
        if (is_int($number)) {
            return $number;
        }
        // Integers that overflow to float, and values that overflow to INF
        if (!is_finite($number) || preg_match('/^-?\d+$/', $value) === 1) {
            return $value;
        }
        $mantissa = preg_replace('/[eE].*$/', '', ltrim($value, '-')) ?? '';
        $digits = ltrim(str_replace('.', '', $mantissa), '0');
        if (str_contains($mantissa, '.')) {
            $digits = rtrim($digits, '0');
        }
        // Underflow to zero, or more digits than a float keeps
        if (($digits !== '' && $number == 0) || strlen($digits) > 15) {
            return $value;
        }
        return $number;
    }

    /**
     * @return list<string|null>
     */
    private function parseCsvLine(string $line): array
    {
        $result = str_getcsv(
            $line,
            (string) $this->conversion['delimiter'],
            (string) $this->conversion['enclosure'],
            (string) $this->conversion['escape']
        );
        return $result !== [null] ? $result : [];
    }

    /**
     * Splits the chunks into rows on \n, \r\n and \r line endings, except inside enclosed fields.
     * Like str_getcsv, an enclosure starts a quoted field only at the start of a field.
     * A row can continue in the next chunk.
     *
     * @param iterable<string> $chunks
     * @return \Generator<int, string>
     */
    private function splitRows(iterable $chunks): \Generator
    {
        $buffer = '';
        $bomChecked = false;
        // A scan that finds no complete row waits until the buffer doubles, so a very long field
        // is not scanned again for each chunk
        $scanAt = 0;
        foreach ($chunks as $chunk) {
            $buffer .= $chunk;
            if (!$bomChecked) {
                if (strlen($buffer) < 4) {
                    continue;
                }
                $buffer = $this->removeBom($buffer);
                $bomChecked = true;
            }
            if (strlen($buffer) < $scanAt) {
                continue;
            }
            [$rows, $used] = $this->scanRows($buffer, false);
            $buffer = substr($buffer, $used);
            $scanAt = $rows === [] ? 2 * strlen($buffer) : 0;
            yield from $rows;
        }
        $buffer = $bomChecked ? $buffer : $this->removeBom($buffer);
        // First the complete rows, so that errors come in the same order as with one chunk
        [$rows, $used] = $this->scanRows($buffer, false);
        yield from $rows;
        $buffer = substr($buffer, $used);
        if ($buffer !== '') {
            yield from $this->scanRows($buffer, true)[0];
        }
    }

    /**
     * @return array{list<string>, int} The complete rows, and the number of bytes they use. If $final
     *                                  is false, an incomplete last row stays for the next chunk.
     */
    private function scanRows(string $data, bool $final): array
    {
        $delimiter = (string) $this->conversion['delimiter'];
        $enclosure = (string) $this->conversion['enclosure'];
        $escape = (string) $this->conversion['escape'];
        $unquotedStops = "\r\n" . $enclosure;
        $quotedStops = $escape !== '' && $escape !== $enclosure ? $enclosure . $escape : $enclosure;
        $rows = [];
        $length = strlen($data);
        $rowStart = 0;
        $i = 0;
        while (($i += strcspn($data, $unquotedStops, $i)) < $length) {
            $char = $data[$i];
            if ($char !== $enclosure) {
                if (!$final && $char === "\r" && $i + 1 === $length) {
                    // Maybe the first half of \r\n
                    return [$rows, $rowStart];
                }
                $rows[] = substr($data, $rowStart, $i - $rowStart);
                $i += ($char === "\r" && ($data[$i + 1] ?? '') === "\n") ? 2 : 1;
                $rowStart = $i;
                continue;
            }
            if (!$this->isFieldStart($data, $i, $rowStart, $delimiter)) {
                $i++;
                continue;
            }
            // Skip to the closing enclosure
            $i++;
            while (true) {
                $i += strcspn($data, $quotedStops, $i);
                if ($i >= $length || ($i + 1 === $length && !$final)) {
                    // The data ends inside the field, or the enclosure can be the first half of a doubled one
                    if (!$final) {
                        return [$rows, $rowStart];
                    }
                    if ($i >= $length) {
                        throw new RuntimeException('Invalid CSV data: unterminated enclosure');
                    }
                }
                if ($data[$i] !== $enclosure) {
                    // Escape character, keep it and the escaped character, str_getcsv handles them
                    $i += 2;
                    continue;
                }
                if (($data[$i + 1] ?? '') === $enclosure) {
                    $i += 2;
                    continue;
                }
                $i++;
                break;
            }
        }
        if (!$final) {
            return [$rows, $rowStart];
        }
        if ($rowStart < $length) {
            $rows[] = substr($data, $rowStart);
        }
        return [$rows, $length];
    }

    /**
     * Like str_getcsv, an enclosure starts a quoted field only at the start of a field,
     * optionally after whitespace.
     */
    private function isFieldStart(string $data, int $i, int $rowStart, string $delimiter): bool
    {
        for ($k = $i - 1; $k >= $rowStart; $k--) {
            $char = $data[$k];
            if ($char === $delimiter) {
                return true;
            }
            if ($char !== ' ' && $char !== "\t" && $char !== "\v" && $char !== "\f") {
                return false;
            }
        }
        return true;
    }

    protected function getExtension(): string
    {
        return $this->conversion['lines'] ? 'jsonl' : 'json';
    }

    protected function getMimeType(): string
    {
        return $this->conversion['lines'] ? 'application/jsonl' : 'application/json';
    }
}
