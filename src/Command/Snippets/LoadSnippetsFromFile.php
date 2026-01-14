<?php declare(strict_types=1);

namespace PhallosanCustomizations\Command\Snippets;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LoadSnippetsFromFile extends Command
{
    public function __construct(
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('tdo:load-snippets-from-file');
        $this->addArgument('fileName', InputArgument::REQUIRED, 'Name of the CSV file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fileName = 'public/' . $input->getArgument('fileName');
        $output->writeln('Started snippets import from file: ' . $fileName);

        $file = fopen($fileName, 'rb');
        if (!$file) {
            return 0;
        }
        $headline = fgetcsv($file, 2000, ';');

        $sets = $this->connection->fetchAllAssociative(
            'SELECT id, iso FROM snippet_set;'
        );

        $sets = array_column($sets, 'id', 'iso');

        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        while (($data = fgetcsv($file, 2000000, ';')) !== false) {
            /** @phpstan-ignore-next-line */
            if (!\is_array($data)) {
                continue;
            }

            $num = \count($data);
            $snippetQuery = '';
            $output->writeln('Loading snippets for: ' . $data[0]);
            for ($c = 1; $c < $num; $c++) {
                $code = $data[0];
                /** @phpstan-ignore-next-line */
                if (!\array_key_exists($c, $headline)) {
                    continue;
                }
                $iso = $headline[$c] ?? '';
                if (!\array_key_exists($c, $data)) {
                    continue;
                }
                $snippetValue = $data[$c];
                if (!\array_key_exists($iso, $sets)) {
                    continue;
                }
                $snippetSetId = bin2hex($sets[$iso]);
                $id = Uuid::randomHex();
                $quotedSnippetValue = $this->connection->quote($snippetValue, ParameterType::STRING);
                $snippetQuery .= "INSERT INTO `snippet` (id, translation_key, value, snippet_set_id, author, created_at) VALUES (X'$id', '$code', $quotedSnippetValue, X'$snippetSetId', 'System', '$createdAt') ON DUPLICATE KEY UPDATE value=$quotedSnippetValue;\r\n";
            }
            $this->connection->executeStatement($snippetQuery);
        }
        fclose($file);

        $output->writeln('Loaded snippets from file');

        return 0;
    }
}
