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
class Migration1759310173ProductCustomFieldAlternativeProduct extends MigrationStep
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
                    'name' => PhallosanConstants::CUSTOM_FIELD_PRODUCT_ALTERNATIVE_PRODUCT,
                    'type' => CustomFieldTypes::SELECT,
                    'config' => [
                        'customFieldType' => 'entity',
                        'customFieldPosition' => 50,
                        'entity' => 'product',
                        'label' => [
                            'de-DE' => 'Alternatives Produkt',
                            'en-GB' => 'Alternative Product',
                        ],
                        'helpText' => [
                            'de-DE' => 'Wenn dieses Produkt in dem Verkaufskanal nicht verfügbar ist, soll es mit dem alternativen Produkt ersetzt werden (s. 5000 und 5000-US).',
                            'en-GB' => 'If this Product is not available in the saleschannel, it should be replaced by the alternative product (see 5000 and 5000-US).',
                        ],
                        'componentName' => 'sw-entity-single-select',
                        'type' => 'entity',
                    ],
                    'allow_cart_expose' => true,
                ],
            ],
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1759310173;
    }

    public function update(Connection $connection): void
    {
        $this->createOrUpdateCustomFields(self::CUSTOM_FIELDS, $connection);
    }
}
