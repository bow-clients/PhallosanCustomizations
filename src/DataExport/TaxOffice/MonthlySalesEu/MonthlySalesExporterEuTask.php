<?php declare(strict_types=1);

namespace PhallosanCustomizations\DataExport\TaxOffice\MonthlySalesEu;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class MonthlySalesExporterEuTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'tdo.monthly_sales_export_tax_office';
    }

    public static function getDefaultInterval(): int
    {
        return 86400; //daily
    }
}
