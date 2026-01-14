<?php declare(strict_types=1);

namespace PhallosanCustomizations\DataExport\TaxOffice\CancelledWorld;

use PhallosanCustomizations\DataExport\TaxOffice\MonthlySalesExportService;
use PhallosanCustomizations\PhallosanConstants;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: MonthlyCancelledExporterWorldTask::class)]
class MonthlyCancelledExporterWorldTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        private readonly MonthlySalesExportService  $monthlySalesExportService,
        private readonly SystemConfigService        $systemConfigService,
        EntityRepository                            $scheduledTaskRepository
    ) {
        parent::__construct($scheduledTaskRepository);
    }

    public function run(): void
    {
        $now = new \DateTime();

        $day = $now->format('d');
        $lastExportDate = $this->monthlySalesExportService->getLastRuntime(PhallosanConstants::PLUGIN_CONFIG_MONTHLY_WORLD_CANCELLATIONS_TAX_OFFICE);

        $currentMonth = $now->format('m');

        if (($day >= '7' && $lastExportDate === '') || ($day >= '7' && ($lastExportDate !== $currentMonth))) {
            try {
                $this->monthlySalesExportService->getMonthlyCancellations('world', true);
                $this->systemConfigService->set(PhallosanConstants::PLUGIN_CONFIG_MONTHLY_WORLD_CANCELLATIONS_TAX_OFFICE, $now->getTimestamp());
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
                //logging
            }
        }
    }
}
