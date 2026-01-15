<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * Adds a custom field to Country entity for default language assignment.
 * Used by BowGeoIpSw for redirecting users to the correct language domain.
 *
 * @internal
 */
class Migration1768463734AddCountryDefaultLanguageField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1768463734;
    }

    public function update(Connection $connection): void
    {
        // Check if custom field set already exists
        $existingSetId = $connection->fetchOne(
            'SELECT id FROM custom_field_set WHERE name = :name',
            ['name' => 'country_geoip_settings']
        );

        if ($existingSetId) {
            return; // Already migrated
        }

        $customFieldSetId = Uuid::randomBytes();

        // Custom Field Set Config
        $customFieldSetConfig = [
            'label' => [
                'en-GB' => 'GeoIP Redirect Settings',
                'de-DE' => 'GeoIP Weiterleitungs-Einstellungen',
            ],
        ];

        // Create Custom Field Set
        $connection->insert('custom_field_set', [
            'id' => $customFieldSetId,
            'name' => 'country_geoip_settings',
            'config' => json_encode($customFieldSetConfig),
            'active' => 1,
            'position' => 10,
            'app_id' => null,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        // Relation to Country Entity
        $connection->insert('custom_field_set_relation', [
            'id' => Uuid::randomBytes(),
            'set_id' => $customFieldSetId,
            'entity_name' => 'country',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        // Custom Field: Default Language (Entity Select)
        $customFieldConfig = [
            'label' => [
                'en-GB' => 'Default Language for GeoIP Redirect',
                'de-DE' => 'Standard-Sprache für GeoIP Weiterleitung',
            ],
            'helpText' => [
                'en-GB' => 'When a visitor from this country is detected, they will be redirected to this language domain.',
                'de-DE' => 'Wenn ein Besucher aus diesem Land erkannt wird, wird er zu dieser Sprach-Domain weitergeleitet.',
            ],
            'placeholder' => [
                'en-GB' => 'Select language...',
                'de-DE' => 'Sprache auswählen...',
            ],
            'componentName' => 'sw-entity-single-select',
            'entity' => 'language',
            'customFieldType' => 'entity',
            'customFieldPosition' => 1,
        ];

        $connection->insert('custom_field', [
            'id' => Uuid::randomBytes(),
            'name' => 'country_geoip_default_language',
            'type' => CustomFieldTypes::TEXT, // Entity select stores UUID as text
            'config' => json_encode($customFieldConfig),
            'active' => 1,
            'set_id' => $customFieldSetId,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // Nothing to do
    }
}
