<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use PhallosanCustomizations\Migration\Trait\MigrationTrait;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1751524862AddedUrlPrio extends MigrationStep
{
    use MigrationTrait;

    public function getCreationTimestamp(): int
    {
        return 1751524862;
    }

    public function update(Connection $connection): void
    {
        $countryId = $this->getCountryByName($connection, ['United States of America']);

        $urlIdResult = $connection->fetchAssociative(
            'SELECT `scd`.`id`
                    FROM `sales_channel_domain` scd
                    LEFT JOIN sales_channel sc ON sc.id = scd.sales_channel_id
                    WHERE sc.country_id = :countryId',
            ['countryId' => array_column($countryId, 'country_id')[0]]
        );

        if ($urlIdResult !== false && isset($urlIdResult['id'])) {
            $urlId = $urlIdResult['id'];

            $connection->update(
                'neti_language_detector_sales_channel_domain_priority',
                [
                    'priority' => 10,
                ],
                [
                    'sales_channel_domain_id' => $urlId,
                ]
            );
        }
    }
}
