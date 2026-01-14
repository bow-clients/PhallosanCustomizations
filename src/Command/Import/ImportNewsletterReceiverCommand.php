<?php declare(strict_types=1);

namespace PhallosanCustomizations\Command\Import;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'phallosan:import:newsletter-receiver',
    description: 'Import Newsletter receiver from csv file (comma separated)',
)]
class ImportNewsletterReceiverCommand extends Command
{
    final public const CSV_COLUMN_FIRSTNAME = 'customers_firstname';
    final public const CSV_COLUMN_LASTNAME = 'customers_lastname';
    final public const CSV_COLUMN_MAIL = 'customers_email_address';
    final public const CSV_COLUMN_LANG = 'customers_langcode';

    final public const CSV_REQUIRED_COLUMNS = [
        self::CSV_COLUMN_FIRSTNAME,
        self::CSV_COLUMN_LASTNAME,
        self::CSV_COLUMN_MAIL,
        self::CSV_COLUMN_LANG,
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
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the CSV file to import');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $importFile = $input->getArgument('file');

        try {
            $csvData = $this->readAndCheckImportFile($importFile);
        } catch (\Exception $e) {
            $output->writeln('Something went wrong while reading the csv file');
            $output->writeln($e->getMessage());

            return Command::FAILURE;
        }

        try {
            $this->importNewsletterReceiver($csvData);
        } catch (\Exception $e) {
            $output->writeln('Something went wrong while importing the data');
            $output->writeln($e->getMessage());

            return Command::FAILURE;
        }

        $output->writeln('File successfully imported');

        return Command::SUCCESS;
    }

    protected function importNewsletterReceiver(array $list): void
    {
        foreach ($list as $importRow) {
            $countryIso = $importRow[self::CSV_COLUMN_LANG];
            $firstname = $importRow[self::CSV_COLUMN_FIRSTNAME];
            $lastname = $importRow[self::CSV_COLUMN_LASTNAME];
            $mail = $importRow[self::CSV_COLUMN_MAIL];

            $countryId = $this->getCountryIdByIso($countryIso);
            if (!$countryId) {
                continue;
            }

            $salesChannel = $this->getSalesChannelByCountry($countryId);
            if (!$salesChannel) {
                continue;
            }

            $language = $this->getLanguageBySalesChannel($salesChannel);
            if (!$language) {
                continue;
            }

            $salutation = $this->getSalutationId('not_specified');
            if (!$salutation) {
                continue;
            }

            $newsletterReceiverId = $this->getNewsletterReceiverByMail($mail);

            $data = [
                'id' => $newsletterReceiverId,
                'email' => $mail,
                'firstName' => $firstname,
                'lastName' => $lastname,
                'status' => 'optIn',
                'hash' => Uuid::randomHex(),
                'language' => $language,
                'salesChannel' => $salesChannel,
                'salutation' => $salutation,
            ];

            if (!$newsletterReceiverId) {
                $data['id'] = Uuid::randomBytes();
                $this->connection->executeStatement('
                INSERT INTO
                    newsletter_recipient
                SET
                    id = :id,
                    email = :email,
                    first_name = :firstName,
                    last_name = :lastName,
                    status = :status,
                    hash = :hash,
                    language_id = :language,
                    sales_channel_id = :salesChannel,
                    salutation_id = :salutation,
                    confirmed_at = now(),
                    created_at = now()
                ', $data);
            } else {
                $this->connection->executeStatement(
                    '
                UPDATE
                    newsletter_recipient
                SET
                    email = :email,
                    first_name = :firstName,
                    last_name = :lastName,
                    status = :status,
                    hash = :hash,
                    language_id = :language,
                    sales_channel_id = :salesChannel,
                    salutation_id = :salutation,
                    confirmed_at = now(),
                    updated_at = now()
                WHERE
                    id = :id
                ',
                    $data
                );
            }
        }
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

        return $this->_cache['countryByIso'][$countryIso] = $result;
    }

    protected function getNewsletterReceiverByMail(string $email): ?string
    {
        $result = $this->connection->fetchOne(
            '
            SELECT id
            FROM newsletter_recipient
            WHERE email =:email',
            [
                'email' => $email,
            ]
        );

        if ($result === false) {
            return null;
        }

        return $result;
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

    private function getSalutationId(string $salutationKey): ?string
    {
        if (isset($this->_cache['salutation'][$salutationKey])) {
            return $this->_cache['salutation'][$salutationKey];
        }

        $result = $this->connection->fetchOne(
            '
            SELECT id
            FROM salutation
            WHERE salutation_key =:key',
            [
                'key' => $salutationKey,
            ]
        );

        if ($result === false) {
            return null;
        }

        if (!isset($this->_cache['salutation'])) {
            $this->_cache['salutation'] = [];
        }

        return $this->_cache['salutation'][$salutationKey] = $result;
    }

    private function getSalesChannelByCountry(string $countryId): ?string
    {
        if (isset($this->_cache['salesChannel'][$countryId])) {
            return $this->_cache['salesChannel'][$countryId];
        }

        $result = $this->connection->fetchOne(
            '
            SELECT id
            FROM sales_channel
            WHERE country_id =:country',
            [
                'country' => $countryId,
            ]
        );

        if ($result === false) {
            return null;
        }

        if (!isset($this->_cache['salesChannel'])) {
            $this->_cache['salesChannel'] = [];
        }

        return $this->_cache['salesChannel'][$countryId] = $result;
    }

    private function getLanguageBySalesChannel(string $salesChannelId): ?string
    {
        if (isset($this->_cache['language'][$salesChannelId])) {
            return $this->_cache['language'][$salesChannelId];
        }

        $result = $this->connection->fetchOne(
            '
            SELECT language_id
            FROM sales_channel
            WHERE id =:id',
            [
                'id' => $salesChannelId,
            ]
        );

        if ($result === false) {
            return null;
        }

        if (!isset($this->_cache['language'])) {
            $this->_cache['language'] = [];
        }

        return $this->_cache['language'][$salesChannelId] = $result;
    }
}
