<?php declare(strict_types=1);

namespace PhallosanCustomizations\Command\Import;

use PhallosanCustomizations\Import\ImportShippingPricesService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'phallosan:import:shipping-prices',
    description: 'Import Shipping Method Prices from csv file (comma separated)',
)]
class ImportShippingPricesCommand extends Command
{
    public function __construct(
        private readonly ImportShippingPricesService $importer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the CSV file to import')
            ->addArgument('shippingMethodId', InputArgument::REQUIRED, 'ID of the shipping method to update')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'If set, the import will be simulated but no changes will be made')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->importer->importCSV(
            $input->getArgument('file'),
            $input->getArgument('shippingMethodId'),
            $input->getOption('dry-run') ?? false,
        );

        return Command::SUCCESS;
    }
}
