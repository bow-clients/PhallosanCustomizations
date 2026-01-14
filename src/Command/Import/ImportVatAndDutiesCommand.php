<?php declare(strict_types=1);

namespace PhallosanCustomizations\Command\Import;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'phallosan:import:vat-and-duties',
    description: 'Import Custom Duties and Import Vats from csv file (comma separated)',
)]
class ImportVatAndDutiesCommand extends Command
{
    final public const CSV_COLUMN_ISO_CODE = 'countries_iso_code_2';
    final public const CSV_COLUMN_CUSTOM_DUTY = 'custom_duty';
    final public const CSV_COLUMN_IMPORT_VAT = 'import_vat';

    final public const CSV_REQUIRED_COLUMNS = [
        self::CSV_COLUMN_ISO_CODE,
        self::CSV_COLUMN_CUSTOM_DUTY,
        self::CSV_COLUMN_IMPORT_VAT,
    ];

    private array $_cache = [];

    public function __construct(
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    // Provides a description, printed out in bin/console
    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the CSV file to import')
            ->addOption('taxId', null, InputOption::VALUE_REQUIRED, 'Tax-ID for which the rules should be imported');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $importFile = $input->getArgument('file');
        $taxId = $input->getOption('taxId');

        try {
            $csvData = $this->readAndCheckImportFile($importFile);
        } catch (\Exception $e) {
            $output->writeln('Something went wrong while reading the csv file');
            $output->writeln($e->getMessage());

            return Command::FAILURE;
        }

        try {
            $this->importVatAndDuty($csvData, $taxId);
        } catch (\Exception $e) {
            $output->writeln('Something went wrong while importing the data');
            $output->writeln($e->getMessage());

            return Command::FAILURE;
        }

        $output->writeln('File successfully imported');

        return Command::SUCCESS;
    }

    protected function importVatAndDuty(array $list, string $taxId): void
    {
        foreach ($list as $importRow) {
            $countryIso = $importRow[self::CSV_COLUMN_ISO_CODE];
            $customDuty = (float)($importRow[self::CSV_COLUMN_CUSTOM_DUTY] ?? 0);
            $importVat = (float)($importRow[self::CSV_COLUMN_IMPORT_VAT] ?? 0);

            $countryId = $this->getCountryIdByIso($countryIso);
            // if the country does not exists, skip it
            if (!$countryId) {
                continue;
            }
            $taxRuleId = $this->getTaxRuleId($taxId, $countryId);

            // if there is no tax rule for the country, skip it
            if (!$taxRuleId) {
                continue;
            }

            $customTaxRuleId = $this->getCustomTaxRuleId($taxRuleId);

            if (!$customTaxRuleId) {
                $this->connection->executeStatement('
                INSERT INTO
                    custom_tax_rule
                SET
                    id = :id,
                    tax_rule_id = :taxRuleId,
                    customs_duty = :customsDuty,
                    import_vat = :importVat,
                    created_at = now()
                ', [
                    'id' => Uuid::randomBytes(),
                    'taxRuleId' => Uuid::fromHexToBytes($taxRuleId),
                    'customsDuty' => $customDuty,
                    'importVat' => $importVat,
                ]);
            } else {
                $this->connection->executeStatement('
                UPDATE
                    custom_tax_rule
                SET
                    customs_duty = :customsDuty,
                    import_vat = :importVat,
                    updated_at = now()
                WHERE
                    id = :id
                ', [
                    'customsDuty' => $customDuty,
                    'importVat' => $importVat,
                    'id' => Uuid::fromHexToBytes($customTaxRuleId),
                ]);
            }
        }
    }

    protected function getCustomTaxRuleId(string $taxRuleId): ?string
    {
        if (isset($this->_cache['customTaxRule'][$taxRuleId])) {
            return $this->_cache['customTaxRule'][$taxRuleId];
        }

        $result = $this->connection->fetchOne(
            '
            SELECT id
            FROM custom_tax_rule
            WHERE tax_rule_id = :taxRuleId',
            [
                'taxRuleId' => Uuid::fromHexToBytes($taxRuleId),
            ]
        );

        if ($result === false) {
            return null;
        }

        if (!isset($this->_cache['customTaxRule'])) {
            $this->_cache['customTaxRule'] = [];
        }

        return $this->_cache['customTaxRule'][$taxRuleId] = Uuid::fromBytesToHex($result);
    }

    protected function getTaxRuleId(string $taxId, string $countryId): ?string
    {
        $cacheKey = $taxId . '-' . $countryId;
        if (isset($this->_cache['taxRuleId'][$cacheKey])) {
            return $this->_cache['taxRuleId'][$cacheKey];
        }

        $result = $this->connection->fetchOne(
            '
            SELECT id
            FROM tax_rule
            WHERE tax_id =:taxId AND country_id = :countryId',
            [
                'taxId' => Uuid::fromHexToBytes($taxId),
                'countryId' => Uuid::fromHexToBytes($countryId),
            ]
        );

        if ($result === false) {
            return null;
        }

        if (!isset($this->_cache['taxRuleId'])) {
            $this->_cache['taxRuleId'] = [];
        }

        return $this->_cache['taxRuleId'][$cacheKey] = Uuid::fromBytesToHex($result);
    }

    protected function getCountryIdByIso(string $countryIso): ?string
    {
        if (isset($this->_cache['countryByIso'][$countryIso])) {
            return $this->_cache['countryByIso'][$countryIso];
        }

        $result = $this->connection->fetchOne(
            '
            SELECT id
            FROM country
            WHERE iso =:iso',
            [
                'iso' => $countryIso,
            ]
        );

        if ($result === false) {
            return null;
        }

        if (!isset($this->_cache['countryByIso'])) {
            $this->_cache['countryByIso'] = [];
        }

        return $this->_cache['countryByIso'][$countryIso] = Uuid::fromBytesToHex($result);
    }

    protected function readAndCheckImportFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception('CSV in "' . $filePath . '" does not exists!');
        }

        /** @var array $csvData */
        $csvData = $this->readImportFile($filePath);

        if (\count($csvData) === 0) {
            throw new \Exception('CSV file does not contain any data');
        }

        $firstEntry = $csvData[0];
        foreach (self::CSV_REQUIRED_COLUMNS as $requiredColumn) {
            if (!\array_key_exists($requiredColumn, $firstEntry)) {
                throw new \Exception('Required CSV Column "' . $requiredColumn . '" is missing');
            }
        }

        return $csvData;
    }

    protected function readImportFile(string $filePath): array
    {
        $rows = [];
        $headline = null;
        if (($handle = fopen($filePath, 'rb')) !== false) {
            while (($row = fgetcsv($handle, null, ',')) !== false) {
                if (!$headline) {
                    $headline = array_filter(array_map(fn ($row) => trim((string)$row), $row));

                    continue;
                }

                if (\count($headline) !== \count($row)) {
                    if (\count($row) > \count($headline)) {
                        $row = \array_slice($row, 0, \count($headline));
                    } else {
                        $row = array_pad($row, \count($headline), '');
                    }
                }

                $rows[] = array_combine($headline, $row);
            }
            fclose($handle);
        }

        return $rows;
    }
}
