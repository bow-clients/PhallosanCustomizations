<?php declare(strict_types=1);

namespace PhallosanCustomizations\DataExport\MonthlySales;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class MonthlySalesExporterTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'tdo.monthly_sales_exporter_task';
    }

    public static function getDefaultInterval(): int
    {
        return 86400; //daily
    }
}
