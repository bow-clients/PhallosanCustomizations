<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration\Trait;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

trait MigrationTrait
{
    protected function createOrUpdateCustomFields(array $customFields, Connection $connection): void
    {
        foreach ($customFields as $fieldSet) {
            $customFields = $fieldSet['customFields'];
            unset($fieldSet['customFields']);

            try {
                $fieldSetId = $this->createCustomFieldSet($fieldSet, $connection);
            } catch (\Exception $e) {
                $fieldSetId = null;
            }

            if ($fieldSetId === null) {
                continue;
            }

            foreach ($customFields as $customField) {
                $customField['set_id'] = Uuid::fromHexToBytes($fieldSetId);

                $this->createCustomField($customField, $connection);
            }
        }
    }

    private function getLocaleId(Connection $connection, string $code): ?string
    {
        $result = $connection->fetchOne(
            '
            SELECT lang.id
            FROM language lang
            INNER JOIN locale loc ON lang.translation_code_id = loc.id
            AND loc.code = :code',
            [
                'code' => $code,
            ]
        );

        if ($result === false) {
            return null;
        }

        return (string)$result;
    }

    private function getCurrencyId(Connection $connection, string $code): ?string
    {
        $result = $connection->fetchOne(
            '
            SELECT id
            FROM currency
            WHERE iso_code = :code',
            [
                'code' => $code,
            ]
        );

        if ($result === false) {
            return null;
        }

        return (string)$result;
    }

    private function getSalesChannelByCountryName(Connection $connection, array $countries): ?array
    {
        return $connection->fetchAllAssociative(
            'SELECT sc.id FROM
                    sales_channel sc
                    LEFT JOIN country_translation ct ON sc.`country_id` = ct.`country_id`
                    WHERE ct.`name` IN (:countryNames)
                    GROUP BY ct.country_id',
            ['countryNames' => $countries],
            ['countryNames' => ArrayParameterType::STRING]
        );
    }

    private function getCountryByName(Connection $connection, array $countries): array
    {
        return $connection->fetchAllAssociative(
            'SELECT country_id FROM
                    country_translation ct
                    WHERE ct.`name` IN (:countryNames)
                    GROUP BY ct.country_id',
            ['countryNames' => $countries],
            ['countryNames' => ArrayParameterType::STRING]
        );
    }

    private function fetchCustomFieldSetId(string $fieldSetName, Connection $connection): ?string
    {
        $customFieldSetId = $connection->fetchOne('
            SELECT id
            FROM `custom_field_set`
            WHERE `name` = :name
        ', ['name' => $fieldSetName]);

        return \is_string($customFieldSetId) ? Uuid::fromBytesToHex($customFieldSetId) : null;
    }

    private function createCustomFieldSet(array $fieldSet, Connection $connection): ?string
    {
        $customFieldSetId = $this->fetchCustomFieldSetId($fieldSet['name'], $connection);

        if ($customFieldSetId) {
            return $customFieldSetId;
        }

        $customFieldSetId = Uuid::fromStringToHex($fieldSet['name']);
        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $connection->insert('custom_field_set', [
            'id' => Uuid::fromHexToBytes($customFieldSetId),
            'name' => $fieldSet['name'],
            'global' => $fieldSet['global'] ?? true,
            'config' => json_encode($fieldSet['config'] ?? []),
            'created_at' => $createdAt,
        ]);

        foreach ($fieldSet['relations'] as $relation) {
            $connection->insert('custom_field_set_relation', [
                'id' => Uuid::randomBytes(),
                'set_id' => Uuid::fromHexToBytes($customFieldSetId),
                'entity_name' => $relation['entityName'],
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        return $this->fetchCustomFieldSetId($fieldSet['name'], $connection);
    }

    private function fetchCustomFieldId(string $name, Connection $connection): ?string
    {
        $customFieldId = $connection->fetchOne('
            SELECT id
            FROM `custom_field`
            WHERE `name` = :name
        ', ['name' => $name]);

        return \is_string($customFieldId) ? Uuid::fromBytesToHex($customFieldId) : null;
    }

    private function createCustomField(array $customField, Connection $connection): ?string
    {
        $customFieldId = $this->fetchCustomFieldId($customField['name'], $connection);

        if ($customFieldId) {
            return $customFieldId;
        }

        $customFieldId = Uuid::fromStringToHex($customField['name']);
        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $customField['id'] = Uuid::fromHexToBytes($customFieldId);
        $customField['config'] = json_encode($customField['config'] ?? []);
        $customField['created_at'] = $createdAt;

        $connection->insert('custom_field', $customField);

        return $this->fetchCustomFieldId($customField['name'], $connection);
    }
}
