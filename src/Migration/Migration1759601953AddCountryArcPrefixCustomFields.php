<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use PhallosanCustomizations\Migration\Trait\MigrationTrait;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal
 */
class Migration1759601953AddCountryArcPrefixCustomFields extends MigrationStep
{
    use MigrationTrait;

    final public const CUSTOM_FIELDS = [
        [
            'name' => 'country_arc_custom_fields',
            'config' => [
                'label' => [
                    'en-GB' => 'ARC ID',
                    'de-DE' => 'ARC ID',
                ],
                'translated' => true,
            ],
            'relations' => [
                [
                    'entityName' => CountryDefinition::ENTITY_NAME,
                ],
            ],
            'customFields' => [
                [
                    'name' => 'arc_id_prefix',
                    'type' => CustomFieldTypes::TEXT,
                    'config' => [
                        'customFieldType' => 'text',
                        'customFieldPosition' => 0,
                        'label' => [
                            'en-GB' => 'ARC ID prefix',
                            'de-DE' => 'ARC ID prefix',
                        ],
                        'type' => 'text',
                        'componentName' => 'sw-field',
                    ],
                    'allow_cart_expose' => 1,
                    'allow_customer_write' => 1,
                ],
            ],
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1759601953;
    }

    public function update(Connection $connection): void
    {
        $this->createOrUpdateCustomFields(self::CUSTOM_FIELDS, $connection);
    }
}
