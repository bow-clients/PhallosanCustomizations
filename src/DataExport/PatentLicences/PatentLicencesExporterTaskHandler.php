<?php declare(strict_types=1);

namespace PhallosanCustomizations\DataExport\PatentLicences;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: PatentLicencesExporterTask::class)]
class PatentLicencesExporterTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        private readonly PatentLicencesExporterService $patentLicencesExporterService,
        EntityRepository                               $scheduledTaskRepository
    ) {
        parent::__construct($scheduledTaskRepository);
    }

    public function run(): void
    {
        $this->patentLicencesExporterService->getPatentLicences();
    }
}
