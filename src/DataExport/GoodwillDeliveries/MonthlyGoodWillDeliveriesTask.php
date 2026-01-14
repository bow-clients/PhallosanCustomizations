<?php declare(strict_types=1);

namespace PhallosanCustomizations\DataExport\GoodwillDeliveries;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class MonthlyGoodWillDeliveriesTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'tdo.monthly_good_will_deliveries_task';
    }

    public static function getDefaultInterval(): int
    {
        return 2628000; //monthly
    }
}
