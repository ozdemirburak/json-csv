<?php

namespace OzdemirBurak\JsonCsv;

use RuntimeException;

abstract class AbstractFile
{
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
        if (!is_readable($filepath)) {
            throw new RuntimeException("File not readable: $filepath");
        }
        $this->filename = pathinfo($filepath, PATHINFO_FILENAME);
        $content = file_get_contents($filepath);
        if ($content === false) {
            throw new RuntimeException("Failed to read file: $filepath");
        }
        $this->data = $content;
    }

    public function convertAndDownload(?string $filename = null, bool $exit = true): void
    {
        $filename ??= $this->filename;
        $content = $this->convert();
        $this->sendHeaders($filename, strlen($content));
        echo $content;
        if ($exit) {
            exit();
        }
    }

    protected function sendHeaders(string $filename, int $contentLength): void
    {
        header('Content-disposition: attachment; filename=' . $filename . '.' . $this->conversion['extension']);
        header('Content-type: ' . $this->conversion['type']);
        header('Content-Length: ' . $contentLength);
    }

    public function fromString(string $dataString): static
    {
        $this->data = $dataString;
        return $this;
    }

    public function convertAndSave(string $path): int|false
    {
        return file_put_contents($path, $this->convert());
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setConversionKey(string $key, string|int|bool|null $value): array
    {
        $this->conversion[$key] = $value;
        return $this->conversion;
    }

    abstract public function convert(): string;
}
