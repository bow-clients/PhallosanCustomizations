<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use PhallosanCustomizations\Migration\Trait\MigrationTrait;
use PhallosanCustomizations\PhallosanConstants;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class Migration1743168558ProductCustomFields extends MigrationStep
{
    use MigrationTrait;

    final public const CUSTOM_FIELDS = [
        [
            'name' => 'us_product_custom_fields',
            'config' => [
                'label' => [
                    'en-GB' => 'US Product Customfields',
                    'de-DE' => 'US Produkt Zusatzfelder',
                ],
                'translated' => true,
            ],
            'relations' => [
                [
                    'entityName' => ProductDefinition::ENTITY_NAME,
                ],
            ],
            'customFields' => [
                [
                    'name' => PhallosanConstants::CUSTOM_FIELD_US_PRODUCT_PRODUCT_NUMBER,
                    'type' => CustomFieldTypes::TEXT,
                    'config' => [
                        'customFieldType' => 'text',
                        'customFieldPosition' => 1,
                        'label' => [
                            'en-GB' => 'Product Number',
                            'de-DE' => 'Produktnummer',
                        ],
                        'componentName' => 'sw-text-field',
                        'type' => 'text',
                    ],
                ],
                [
                    'name' => PhallosanConstants::CUSTOM_FIELD_US_PRODUCT_EAN,
                    'type' => CustomFieldTypes::TEXT,
                    'config' => [
                        'customFieldType' => 'text',
                        'customFieldPosition' => 2,
                        'label' => [
                            'en-GB' => 'EAN',
                            'de-DE' => 'EAN',
                        ],
                        'componentName' => 'sw-text-field',
                        'type' => 'text',
                    ],
                ],
            ],
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1743168558;
    }

    public function update(Connection $connection): void
    {
        $this->createOrUpdateCustomFields(self::CUSTOM_FIELDS, $connection);
    }
}
