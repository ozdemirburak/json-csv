<?php

namespace OzdemirBurak\JsonCsv\File;

use OzdemirBurak\JsonCsv\AbstractFile;
use RuntimeException;

class Csv extends AbstractFile
{
    protected array $conversion = [
        'extension' => 'json',
        'type' => 'application/json',
        'options' => 0,
        'delimiter' => ',',
        'enclosure' => '"',
        'escape' => '\\',
        'join' => '_',
        'numeric' => false,
    ];

    public function convert(): string
    {
        $rows = $this->parseData();
        if ($rows === []) {
            return '[]';
        }
        $headerRow = array_shift($rows);
        if ($headerRow === null) {
            return '[]';
        }
        $keys = $this->parseCsvLine($headerRow);
        if ($keys === [] || $rows === []) {
            return '[]';
        }
        $splitKeys = $this->splitKeys($keys);
        $jsonObjects = [];
        foreach ($rows as $row) {
            $values = $this->parseCsvLine($row);
            $jsonObject = $this->buildJsonObject($values, $splitKeys);

            if ($jsonObject !== []) {
                $jsonObjects[] = $jsonObject;
            }
        }
        $json = json_encode($jsonObjects, $this->conversion['options']);
        if ($json === false) {
            throw new RuntimeException('JSON encoding failed: ' . json_last_error_msg());
        }
        return $json;
    }

    private function splitKeys(array $keys): array
    {
        return array_map(
            fn(string $key): array => explode($this->conversion['join'], $key),
            $keys
        );
    }

    private function buildJsonObject(array $values, array $splitKeys): array
    {
        $jsonObject = [];
        $count = min(count($values), count($splitKeys));
        for ($i = 0; $i < $count; $i++) {
            if ($values[$i] === '') {
                continue;
            }
            $this->setNestedValue($jsonObject, $splitKeys[$i], $values[$i]);
        }
        return $jsonObject;
    }

    private function setNestedValue(array &$target, array $keyParts, mixed $value): void
    {
        $lastIndex = count($keyParts) - 1;
        foreach ($keyParts as $index => $keyPart) {
            if ($index === $lastIndex) {
                $target[$keyPart] = $this->convertValue($value);
            } else {
                if (!isset($target[$keyPart]) || !is_array($target[$keyPart])) {
                    $target[$keyPart] = [];
                }
                $target = &$target[$keyPart];
            }
        }
    }

    private function convertValue(mixed $value): mixed
    {
        if ($this->conversion['numeric'] && is_numeric($value)) {
            return $value + 0;
        }
        return $value;
    }

    private function parseCsvLine(string $line): array
    {
        $result = str_getcsv(
            $line,
            $this->conversion['delimiter'],
            $this->conversion['enclosure'],
            $this->conversion['escape']
        );
        return $result !== [null] ? $result : [];
    }

    private function parseData(): array
    {
        if ($this->data === '') {
            return [];
        }
        // Normalize line endings
        $normalized = str_replace("\r\n", "\n", $this->data);
        $normalized = str_replace("\r", "\n", $normalized);
        // Handle quoted fields containing newlines by using proper CSV parsing
        $rows = $this->parseCsvWithQuotedNewlines($normalized);
        // Remove empty trailing row
        if ($rows !== [] && end($rows) === '') {
            array_pop($rows);
        }
        return $rows;
    }

    private function parseCsvWithQuotedNewlines(string $data): array
    {
        $rows = [];
        $currentRow = '';
        $inQuotes = false;
        $length = strlen($data);
        for ($i = 0; $i < $length; $i++) {
            $char = $data[$i];
            if ($char === $this->conversion['enclosure']) {
                // Check if it's an escaped quote
                if ($inQuotes && isset($data[$i + 1]) && $data[$i + 1] === $this->conversion['enclosure']) {
                    $currentRow .= $char . $data[$i + 1];
                    $i++;
                    continue;
                }
                $inQuotes = !$inQuotes;
            }
            if ($char === "\n" && !$inQuotes) {
                $rows[] = $currentRow;
                $currentRow = '';
                continue;
            }
            $currentRow .= $char;
        }
        // Add the last row if there's content
        if ($currentRow !== '') {
            $rows[] = $currentRow;
        }
        return $rows;
    }
}
