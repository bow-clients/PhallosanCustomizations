<?php declare(strict_types=1);

namespace PhallosanCustomizations\DataExport\TaxOffice\MonthlySalesWorld;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class MonthlySalesExporterWorldTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'tdo.monthly_sales_export_tax_office_world';
    }

    public static function getDefaultInterval(): int
    {
        return 86400; //daily
    }
}