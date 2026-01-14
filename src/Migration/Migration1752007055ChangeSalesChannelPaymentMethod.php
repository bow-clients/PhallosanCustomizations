<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1752007055ChangeSalesChannelPaymentMethod extends MigrationStep
{
    private const PAYMENT_METHOD_NAME = 'app\StripePaymentsApp_stripePayment';

    public function getCreationTimestamp(): int
    {
        return 1752007055;
    }

    public function update(Connection $connection): void
    {
        $paymentId = $connection->fetchOne(
            '
            SELECT `id` FROM `payment_method` WHERE `handler_identifier` = ?',
            [self::PAYMENT_METHOD_NAME]
        );

        $connection->update(
            'sales_channel',
            ['payment_method_id' => $paymentId],
            ['short_name' => null]
        );
    }
}
