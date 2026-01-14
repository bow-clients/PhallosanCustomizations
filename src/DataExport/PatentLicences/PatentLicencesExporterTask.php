<?php declare(strict_types=1);

namespace PhallosanCustomizations\DataExport\PatentLicences;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class PatentLicencesExporterTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'tdo.patent_licences_exporter_task';
    }

    public static function getDefaultInterval(): int
    {
        return 86400; // daily
    }
}
