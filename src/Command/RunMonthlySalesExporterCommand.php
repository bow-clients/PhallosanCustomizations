<?php declare(strict_types=1);

namespace PhallosanCustomizations\Command;

use PhallosanCustomizations\DataExport\MonthlySales\MonthlySalesExporterTask;
use PhallosanCustomizations\DataExport\MonthlySales\MonthlySalesExporterTaskHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunMonthlySalesExporterCommand extends Command
{
    public function __construct(
        private MonthlySalesExporterTaskHandler $handler
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('phallosan:run:monthly-sales-exporter')
            ->setDescription('Run MonthlySalesExporterTaskHandler manually');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Running MonthlySalesExporterTaskHandler...');

        $this->handler->__invoke(new MonthlySalesExporterTask());

        $output->writeln('Done.');

        return Command::SUCCESS;
    }
}
