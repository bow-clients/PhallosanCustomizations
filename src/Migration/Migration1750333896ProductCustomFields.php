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
class Migration1750333896ProductCustomFields extends MigrationStep
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
                    'name' => PhallosanConstants::CUSTOM_FIELD_PRODUCT_PACKAGE_UNIT_FINECOM,
                    'type' => CustomFieldTypes::TEXT,
                    'config' => [
                        'customFieldType' => 'text',
                        'customFieldPosition' => 18,
                        'label' => [
                            'de-DE' => 'Verpackungseinheit',
                            'en-GB' => 'Packaging unit',
                        ],
                        'helpText' => [
                            'de-DE' => 'Das Feld dient dazu, dass die Menge pro Karton angegeben werden kann.',
                            'en-GB' => 'The field is used to specify the quantity per carton.',
                        ],
                        'componentName' => 'sw-field',
                        'type' => 'text',
                    ],
                ],
            ],
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1750333896;
    }

    public function update(Connection $connection): void
    {
        $this->createOrUpdateCustomFields(self::CUSTOM_FIELDS, $connection);
    }
}
