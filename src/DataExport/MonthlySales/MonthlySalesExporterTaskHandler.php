<?php declare(strict_types=1);

namespace PhallosanCustomizations\DataExport\MonthlySales;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: MonthlySalesExporterTask::class)]
class MonthlySalesExporterTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        private readonly MonthlySalesExporterService $monthlySalesExporterService,
        EntityRepository                             $scheduledTaskRepository,
    ) {
        parent::__construct($scheduledTaskRepository);
    }

    public function run(): void
    {
        $this->monthlySalesExporterService->getMonthlySales();
    }
}
