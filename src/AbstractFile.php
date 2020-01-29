<?php

namespace OzdemirBurak\JsonCsv;

abstract class AbstractFile
{
    /**
     * @var array
     */
    protected $conversion;

    /**
     * @var string
     */
    protected $data;

    /**
     * @var string
     */
    protected $filename;

    /**
     * CsvToJson constructor.
     *
     * @param string $filepath
     */
    public function __construct($filepath)
    {
        [$this->filename, $this->data] = [pathinfo($filepath, PATHINFO_FILENAME), file_get_contents($filepath)];
    }

    /**
     * @param null $filename
     * @param bool $exit
     */
    public function convertAndDownload($filename = null, $exit = true)
    {
        $filename = $filename ?? $this->filename;
        header('Content-disposition: attachment; filename=' . $filename . '.' . $this->conversion['extension']);
        header('Content-type: ' . $this->conversion['type']);
        echo $this->convert();
        if ($exit === true) {
            exit();
        }
    }

    /**
     * @param string $path
     *
     * @return bool|int
     */
    public function convertAndSave($path): int
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
     * @param string     $key
     * @param string|int $value
     *
     * @return array
     */
    public function setConversionKey($key, $value): array
    {
        $this->conversion[$key] = $value;
        return $this->conversion;
    }

    /**
     * @return string
     */
    abstract public function convert(): string;
}
