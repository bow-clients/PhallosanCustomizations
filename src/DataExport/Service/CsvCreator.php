<?php declare(strict_types=1);

namespace PhallosanCustomizations\DataExport\Service;

class CsvCreator
{
    public const FILE_EXTENSION = '.csv';

    public string $fullPath = '';

    private mixed $file;

    public function __construct(
        public string $path,
        public string $fileName
    ) {
        $this->fullPath = $this->path . $this->fileName . self::FILE_EXTENSION;

        $this->createFolder($this->path);
        $this->createFile();

        $this->file = fopen($this->fullPath, 'wb');
    }

    public function endWriting(): void
    {
        if (!$this->file) {
            return;
        }

        fclose($this->file);
    }

    public function addDataRow(array $dataRow): void
    {
        if (!$this->file) {
            return;
        }

        fputcsv($this->file, $dataRow);
    }

    public function addSpacingRow(int $rows): void
    {
        if (!$this->file) {
            return;
        }

        for ($i = 0; $i < $rows; $i++) {
            fputcsv($this->file, []);
        }
    }

    private function createFile(): void
    {
        file_put_contents($this->fullPath, '');
    }

    private function createFolder(string $path): void
    {
        if (!file_exists($path)) {
            if (!mkdir($path, 0755, true) && !is_dir($path)) {
                throw new \RuntimeException(\sprintf('Directory "%s" was not created', $path));
            }
        }
    }
}
