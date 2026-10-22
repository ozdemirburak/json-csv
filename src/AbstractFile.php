<?php

namespace OzdemirBurak\JsonCsv;

use InvalidArgumentException;
use RuntimeException;

abstract class AbstractFile
{
    /** @var array<string, string|int|float|bool|null> */
    protected array $conversion = [];
    protected string $data = '';
    protected string $filename = '';

    public function __construct(?string $filepath = null)
    {
        if ($filepath !== null) {
            $this->loadFile($filepath);
        }
    }

    protected function loadFile(string $filepath): void
    {
        if (!is_file($filepath) || !is_readable($filepath)) {
            throw new RuntimeException("File not readable: $filepath");
        }
        $this->filename = pathinfo($filepath, PATHINFO_FILENAME);
        $content = file_get_contents($filepath);
        if ($content === false) {
            throw new RuntimeException("Failed to read file: $filepath");
        }
        $this->data = $content;
    }

    /**
     * Sends the download headers and the converted data. Calls exit() unless $exit is false.
     */
    public function convertAndDownload(?string $filename = null, bool $exit = true): void
    {
        if (headers_sent($file, $line)) {
            throw new RuntimeException("Cannot download, headers already sent in $file on line $line");
        }
        $content = $this->convert();
        foreach ($this->getDownloadHeaders($filename, strlen($content)) as $name => $value) {
            header("$name: $value");
        }
        echo $content;
        if ($exit) {
            exit();
        }
    }

    /**
     * The headers convertAndDownload() sends, e.g. for a framework response object.
     *
     * @return array<string, string>
     */
    public function getDownloadHeaders(?string $filename = null, ?int $contentLength = null): array
    {
        $filename ??= $this->filename;
        $filename = ($filename !== '' ? $filename : 'download') . '.' . $this->getExtension();
        $fallback = str_replace(['"', '\\'], '_', preg_replace('/[^\x20-\x7E]/', '_', $filename) ?? 'download');
        $headers = [
            'Content-Disposition' => sprintf(
                'attachment; filename="%s"; filename*=UTF-8\'\'%s',
                $fallback,
                rawurlencode($filename)
            ),
            'Content-Type' => $this->getMimeType() . '; charset=utf-8',
        ];
        if ($contentLength !== null) {
            $headers['Content-Length'] = (string) $contentLength;
        }
        return $headers;
    }

    public function fromString(string $dataString): static
    {
        $this->data = $dataString;
        return $this;
    }

    /**
     * @return int The number of bytes written
     */
    public function convertAndSave(string $path): int
    {
        $content = $this->convert();
        return $this->writeFile($path, static fn(callable $write) => $write($content));
    }

    /**
     * Converts the file at $in and writes the result to $out. CSV and JSON Lines input is read
     * in chunks, so memory use does not depend on the file size. $out is replaced only when
     * the conversion succeeds.
     *
     * @return int The number of bytes written
     */
    abstract public function convertFile(string $in, string $out): int;

    /**
     * Writes the output of $producer to $path. A local file is written to a temporary file in the same
     * directory first and then renamed, so a failed conversion does not leave a partial file and an
     * existing file stays as it was. The file permissions are kept, and for a symlink the target is
     * written. Stream wrappers (e.g. php://output) and hard links are written directly.
     *
     * @internal
     * @param callable(callable(string): void): void $producer Gets a function that writes a string
     */
    protected function writeFile(string $path, callable $producer): int
    {
        $target = is_link($path) ? (realpath($path) ?: $path) : $path;
        $directory = dirname($target);
        $atomic = !str_contains($target, '://') && is_dir($directory) && is_writable($directory)
            && (!is_file($target) || (stat($target)['nlink'] ?? 1) === 1);
        $file = $atomic ? $directory . '/.json-csv-' . bin2hex(random_bytes(6)) . '.tmp' : $target;
        $handle = @fopen($file, $atomic ? 'xb' : 'wb');
        if ($handle === false) {
            $error = error_get_last()['message'] ?? 'unknown error';
            throw new RuntimeException("Failed to save file: $path ($error)");
        }
        $bytes = 0;
        $buffer = '';
        $flush = static function (string $data) use ($handle, $path): int {
            if ($data !== '' && fwrite($handle, $data) !== strlen($data)) {
                throw new RuntimeException("Failed to save file: $path");
            }
            return strlen($data);
        };
        try {
            $producer(static function (string $data) use (&$buffer, &$bytes, $flush): void {
                $buffer .= $data;
                if (strlen($buffer) >= 65536) {
                    $bytes += $flush($buffer);
                    $buffer = '';
                }
            });
            $bytes += $flush($buffer);
            fclose($handle);
            $handle = null;
            if ($atomic) {
                if (is_file($target)) {
                    chmod($file, fileperms($target) & 0777);
                }
                if (!@rename($file, $target)) {
                    $error = error_get_last()['message'] ?? 'unknown error';
                    throw new RuntimeException("Failed to save file: $path ($error)");
                }
            }
        } finally {
            if ($handle !== null) {
                fclose($handle);
            }
            if ($atomic && is_file($file)) {
                unlink($file);
            }
        }
        return $bytes;
    }

    /**
     * Removes a UTF-8 byte order mark. Input in another encoding gives a clear error, not a parse error.
     *
     * @internal
     */
    protected function removeBom(string $data): string
    {
        if (str_starts_with($data, "\xEF\xBB\xBF")) {
            return substr($data, 3);
        }
        // UTF-32 first, its little-endian mark starts with the UTF-16 one
        $marks = [
            "\x00\x00\xFE\xFF" => 'UTF-32',
            "\xFF\xFE\x00\x00" => 'UTF-32',
            "\xFE\xFF" => 'UTF-16',
            "\xFF\xFE" => 'UTF-16',
        ];
        foreach ($marks as $bom => $name) {
            if (str_starts_with($data, $bom)) {
                throw new RuntimeException("Input is $name, convert it to UTF-8 first");
            }
        }
        return $data;
    }

    /**
     * Runs $callback with the settings a conversion needs, and restores them after:
     * - serialize_precision -1, so json_encode() writes the shortest exact float representation.
     * - The cycle collector off. Converted data has no reference cycles, and with many rows each
     *   collector run scans all of them for nothing (2-3 times slower).
     *
     * @internal
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    protected function withConversionSettings(callable $callback): mixed
    {
        $precision = ini_set('serialize_precision', '-1');
        $gc = gc_enabled();
        gc_disable();
        try {
            return $callback();
        } finally {
            if ($gc) {
                gc_enable();
            }
            if ($precision !== false) {
                ini_set('serialize_precision', $precision);
            }
        }
    }

    /**
     * @internal
     * @return resource
     */
    protected function openForReading(string $path)
    {
        $handle = is_file($path) ? @fopen($path, 'rb') : false;
        if ($handle === false) {
            throw new RuntimeException("File not readable: $path");
        }
        return $handle;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * @return array<string, string|int|float|bool|null>
     */
    public function getConversion(): array
    {
        return $this->conversion;
    }

    public function setConversionKey(string $key, string|int|float|bool|null $value): static
    {
        $this->validateConversionValue($key, $value);
        $this->conversion[$key] = $value;
        return $this;
    }

    /** @internal */
    protected function validateConversionValue(string $key, string|int|float|bool|null $value): void
    {
        if (!array_key_exists($key, $this->conversion)) {
            $keys = implode(', ', array_keys($this->conversion));
            throw new InvalidArgumentException("Unknown conversion key '$key', valid keys are: $keys");
        }
        $default = $this->conversion[$key];
        $valid = match ($key) {
            'delimiter' => $this->isCsvCharacter($value)
                && $value !== $this->conversion['enclosure']
                && $value !== $this->conversion['escape'],
            // Like str_getcsv, whitespace before an enclosure is skipped, so it cannot be one
            'enclosure' => $this->isCsvCharacter($value) && !ctype_space($value)
                && $value !== $this->conversion['delimiter'],
            'escape' => $value === '' || ($this->isCsvCharacter($value) && $value !== $this->conversion['delimiter']),
            'join' => is_string($value) && $value !== '',
            'null', 'true', 'false' => true,
            'rows' => in_array($value, ['auto', 'each', 'single'], true),
            default => get_debug_type($value) === get_debug_type($default),
        };
        if (!$valid) {
            throw new InvalidArgumentException("Invalid value for conversion key '$key'");
        }
    }

    private function isCsvCharacter(mixed $value): bool
    {
        return is_string($value) && strlen($value) === 1 && $value !== "\n" && $value !== "\r";
    }

    abstract public function convert(): string;

    abstract protected function getExtension(): string;

    abstract protected function getMimeType(): string;
}
