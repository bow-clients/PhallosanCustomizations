<?php declare(strict_types=1);

namespace PhallosanCustomizations\DataExport\TaxOffice\CancelledDe;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class MonthlyCancelledExporterTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'tdo.monthly_cancelled_export_tax_office';
    }

    public static function getDefaultInterval(): int
    {
        return 86400; //daily
    }
}
