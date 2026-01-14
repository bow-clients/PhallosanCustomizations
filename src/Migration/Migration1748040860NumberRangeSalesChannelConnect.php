<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PhallosanCustomizations\Migration\Trait\MigrationTrait;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class Migration1748040860NumberRangeSalesChannelConnect extends MigrationStep
{
    use MigrationTrait;

    public function getCreationTimestamp(): int
    {
        return 1748040860;
    }

    public function update(Connection $connection): void
    {
        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $countries = [
            'Austria' => 'EU',
            'Belgium' => 'EU',
            'Bulgaria' => 'EU',
            'Czech Republic' => 'EU',
            'Croatia' => 'EU',
            'Cyprus' => 'EU',
            'Denmark' => 'EU',
            'Estonia' => 'EU',
            'Finland' => 'EU',
            'France' => 'EU',
            'Germany' => 'EU',
            'Greece' => 'EU',
            'Hungary' => 'EU',
            'Italy' => 'EU',
            'Ireland' => 'EU',
            'Jersey' => 'EU',
            'Luxembourg' => 'EU',
            'Latvia' => 'EU',
            'Liechtenstein' => 'CH',
            'Lithuania' => 'EU',
            'Malta' => 'EU',
            'Monaco' => 'EU',
            'Netherlands' => 'EU',
            'Portugal' => 'EU',
            'Poland' => 'EU',
            'Romania' => 'EU',
            'Slovakia (Slovak Republic)' => 'EU',
            'Spain' => 'EU',
            'Sweden' => 'EU',
            'Switzerland' => 'CH',
            'Slovenia' => 'EU',
            'San Marino' => 'EU',
            'Vatican City State (Holy See)' => 'EU',
        ];
        $countryName = array_keys($countries);
        $salesChannels = $this->getSalesChannelByCountryName($connection, $countryName);

        $numberRangesNames = [
            'Delivery notes - Swiss Cupping',
            'Cancellations - Swiss Cupping',
            'Credit notes - Swiss Cupping',
            'Invoice - Swiss Cupping',
        ];

        $numberRanges = $connection->fetchAllAssociative(
            'SELECT `nrt`.`number_range_id`, `nr`.`type_id`
                    FROM `number_range_translation` `nrt`
                    LEFT JOIN `number_range` `nr` ON `nr`.`id` = `nrt`.`number_range_id`
                    WHERE `name` IN (:name)',
            ['name' => $numberRangesNames],
            ['name' => ArrayParameterType::STRING]
        );

        foreach ($numberRanges as $numberRange) {
            $numberRangeQuery = '';
            /** @phpstan-ignore-next-line */
            foreach ($salesChannels as $salesChannel) {
                $id = Uuid::randomHex();
                $numberRangeQuery .= \sprintf(
                    "
                    INSERT INTO `number_range_sales_channel` (
                    id,
                    number_range_id,
                    sales_channel_id,
                    number_range_type_id,
                    created_at)
                    VALUES (X'%s', X'%s', X'%s', X'%s', '%s') ON DUPLICATE KEY UPDATE created_at='%s';\r\n",
                    $id,
                    bin2hex($numberRange['number_range_id']),
                    bin2hex($salesChannel['id']),
                    bin2hex($numberRange['type_id']),
                    $createdAt,
                    $createdAt
                );
            }

            $connection->executeStatement($numberRangeQuery);
        }
    }
}
