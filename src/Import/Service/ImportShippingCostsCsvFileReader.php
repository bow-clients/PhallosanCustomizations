<?php declare(strict_types=1);

namespace PhallosanCustomizations\Import\Service;

use PhallosanCustomizations\Import\DTO\ImportShippingCostsDTO;
use Symfony\Component\Serializer\SerializerInterface;

class ImportShippingCostsCsvFileReader
{
    public function __construct(
        private readonly SerializerInterface $serializer,
    ) {
    }

    /**
     * @return ImportShippingCostsDTO[]
     */
    public function readCsvFile(string $filePath): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \RuntimeException("The CSV file was not found or is not readable at path '$filePath'.");
        }

        $rawFile = file_get_contents($filePath);
        if ($rawFile === false) {
            throw new \RuntimeException("The CSV file at path '$filePath' could not be read.");
        }

        $content = $this->serializer->deserialize($rawFile, ImportShippingCostsDTO::class . '[]', 'csv');
        if (!\is_array($content)) {
            throw new \RuntimeException("The CSV file at path '$filePath' could not be deserialized.");
        }

        foreach ($content as $item) {
            if (!$item instanceof ImportShippingCostsDTO) {
                throw new \RuntimeException("The CSV file at path '$filePath' could not be deserialized.");
            }
        }

        return $content;
    }
}
