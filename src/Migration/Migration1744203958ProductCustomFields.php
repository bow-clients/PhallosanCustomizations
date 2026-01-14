<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use PhallosanCustomizations\Migration\Trait\MigrationTrait;
use PhallosanCustomizations\PhallosanConstants;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal
 */
class Migration1744203958ProductCustomFields extends MigrationStep
{
    use MigrationTrait;
    
    final public const CUSTOM_FIELDS = [
        [
            'name' => 'product_custom_fields',
            'config' => [
                'label' => [
                    'en-GB' => 'Product Customfields',
                    'de-DE' => 'Produkt Zusatzfelder',
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
                    'name' => PhallosanConstants::CUSTOM_FIELD_PRODUCT_SHIPPING_NOTE,
                    'type' => CustomFieldTypes::HTML,
                    'config' => [
                        'customFieldType' => 'text',
                        'customFieldPosition' => 14,
                        'label' => [
                            'en-GB' => 'Shipping note',
                            'de-DE' => 'Versand Hinweis',
                        ],
                        'componentName' => 'sw-field',
                        'type' => 'text',
                    ],
                ],
                [
                    'name' => PhallosanConstants::CUSTOM_FIELD_PRODUCT_SHOW_UNIT_PRICE,
                    'type' => CustomFieldTypes::BOOL,
                    'config' => [
                        'customFieldType' => 'checkbox',
                        'customFieldPosition' => 15,
                        'label' => [
                            'en-GB' => 'Show unit price in product box',
                            'de-DE' => 'Einzelpreis in Produktbox anzeigen',
                        ],
                        'componentName' => 'sw-field',
                        'type' => 'checkbox',
                    ],
                ],
            ],
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1744203958;
    }

    public function update(Connection $connection): void
    {
        $this->createOrUpdateCustomFields(self::CUSTOM_FIELDS, $connection);
    }
}
