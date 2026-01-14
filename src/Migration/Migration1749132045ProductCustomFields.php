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
class Migration1749132045ProductCustomFields extends MigrationStep
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
                    'name' => PhallosanConstants::CUSTOM_FIELD_PRODUCT_PRODUCTBUNDLE_LINK,
                    'type' => CustomFieldTypes::SELECT,
                    'config' => [
                        'customFieldType' => 'entity',
                        'customFieldPosition' => 1,
                        'entity' => 'product',
                        'label' => [
                            'de-DE' => 'Produktbox Bundle Verlinkung',
                            'en-GB' => 'Productbox Bundle link',
                        ],
                        'componentName' => 'sw-entity-single-select',
                        'type' => 'entity',
                    ],
                ],
            ],
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1749132045;
    }

    public function update(Connection $connection): void
    {
        $this->createOrUpdateCustomFields(self::CUSTOM_FIELDS, $connection);
    }
}
