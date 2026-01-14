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
class Migration1749711112ProductCustomFields extends MigrationStep
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
                    'name' => PhallosanConstants::CUSTOM_FIELD_PRODUCT_ADD_TO_CART,
                    'type' => CustomFieldTypes::TEXT,
                    'config' => [
                        'customFieldType' => 'text',
                        'customFieldPosition' => 17,
                        'label' => [
                            'de-DE' => 'Externe Warenkorb Verlinkung',
                            'en-GB' => 'External shopping cart link',
                        ],
                        'helpText' => [
                            'de-DE' => 'Funktioniert nur in Kombination mit der Plugin Einstellung "Checkout deaktivieren und neue Verlinkung setzen".',
                            'en-GB' => 'Only works in combination with the plugin setting "Deactivate checkout and set new link".',
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
        return 1749711112;
    }

    public function update(Connection $connection): void
    {
        $this->createOrUpdateCustomFields(self::CUSTOM_FIELDS, $connection);
    }
}
