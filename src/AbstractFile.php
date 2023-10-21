<?php

namespace OzdemirBurak\JsonCsv;

abstract class AbstractFile
{
    /**
     * @var array
     */
    protected $conversion = [];

    /**
     * @var string
     */
    protected $data = '';

    /**
     * @var string
     */
    protected $filename = '';

    /**
     * AbstractFile constructor.
     *
     * @param string|null $filepath
     */
    public function __construct(?string $filepath = null)
    {
        if ($filepath !== null) {
            $this->loadFile($filepath);
        }
    }

    /**
     * Load data from a file.
     *
     * @param string $filepath
     */
    protected function loadFile(string $filepath): void
    {
        if (!is_readable($filepath)) {
            throw new \RuntimeException("File not readable: $filepath");
        }
        [$this->filename, $this->data] = [pathinfo($filepath, PATHINFO_FILENAME), file_get_contents($filepath)];
    }

    /**
     * @param string|null $filename
     * @param bool $exit
     */
    public function convertAndDownload(?string $filename = null, bool $exit = true): void
    {
        $filename = $filename ?? $this->filename;
        $this->sendHeaders($filename);
        echo $this->convert();
        if ($exit === true) {
            exit();
        }
    }

    /**
     * Send headers for download.
     *
     * @param string $filename
     */
    protected function sendHeaders(string $filename): void
    {
        header('Content-disposition: attachment; filename=' . $filename . '.' . $this->conversion['extension']);
        header('Content-type: ' . $this->conversion['type']);
    }

    /**
     * @param string $dataString
     *
     * @return $this
     */
    public function fromString(string $dataString): AbstractFile
    {
        $this->data = $dataString;
        return $this;
    }

    /**
     * @param string $path
     *
     * @return bool|int
     */
    public function convertAndSave(string $path): int
    {
        return file_put_contents($path, $this->convert());
    }

    /**
     * @return string
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * @return string
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * @param string $key
     * @param string|int $value
     *
     * @return array
     */
    public function setConversionKey(string $key, $value): array
    {
        $this->conversion[$key] = $value;
        return $this->conversion;
    }

    /**
     * @return string
     */
    abstract public function convert(): string;
}
