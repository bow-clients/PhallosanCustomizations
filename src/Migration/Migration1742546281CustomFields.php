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
class Migration1742546281CustomFields extends MigrationStep
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
                    'name' => PhallosanConstants::CUSTOM_FIELD_PRODUCT_COUNTRY,
                    'type' => CustomFieldTypes::JSON,
                    'config' => [
                        'customFieldPosition' => 13,
                        'componentName' => 'sw-entity-single-select',
                        'customFieldType' => 'entity',
                        'entity' => 'country',
                        'label' => [
                            'en-GB' => 'Country of origin',
                            'de-DE' => 'Ursprungsland',
                        ],
                    ],
                    'active' => true,
                ],
            ],
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1742546281;
    }

    public function update(Connection $connection): void
    {
        $this->createOrUpdateCustomFields(self::CUSTOM_FIELDS, $connection);
    }
}
