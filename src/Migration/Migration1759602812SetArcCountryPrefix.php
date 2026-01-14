<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1759602812SetArcCountryPrefix extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1759602812;
    }

    public function update(Connection $connection): void
    {
        $countryByIsoPrefix = [
            'RU' => 'INN',
            'KR' => 'ARC',
            'KP' => 'ARC',
            'CL' => 'RUT',
        ];

        $countries = $connection->fetchAllAssociative(
            '
            SELECT id, iso FROM country
            WHERE iso in (:iso)',
            ['iso' => array_keys($countryByIsoPrefix)],
            ['iso' => ArrayParameterType::STRING]
        );


        foreach ($countries as $country) {
            $connection->executeStatement('
            UPDATE `country_translation`
            SET
                `custom_fields` = JSON_SET(
                    IFNULL(`custom_fields`, "{}"),
                    "$.arc_id_prefix",
                    :value
                )
            WHERE `country_id` = :countryId
        ', [
                'value' => $countryByIsoPrefix[$country['iso']],
                'countryId' => $country['id'],
            ]);
        }
    }
}
