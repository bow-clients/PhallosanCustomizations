<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use PhallosanCustomizations\Migration\Trait\MigrationTrait;
use PhallosanCustomizations\PhallosanConstants;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class Migration1729519762HsCode extends MigrationStep
{
    use MigrationTrait;

    final public const CUSTOM_FIELDS = [
        [
            'name' => PhallosanConstants::CUSTOM_FIELD_HS_CODE,
            'config' => [
                'label' => [
                    'de-DE' => 'HS-Code',
                    'en-GB' => 'HS-Code',
                ],
                'translated' => true,
            ],
            'relations' => [
                [
                    'entityName' => ProductDefinition::ENTITY_NAME,
                ],
                [
                    'entityName' => OrderDefinition::ENTITY_NAME,
                ],
            ],
            'customFields' => [
                [
                    'name' => PhallosanConstants::CUSTOM_FIELD_HS_CODE,
                    'type' => CustomFieldTypes::TEXT,
                    'config' => [
                        'customFieldType' => 'text',
                        'customFieldPosition' => 0,
                        'label' => [
                            'de-DE' => 'HS-Code',
                            'en-GB' => 'HS-Code',
                        ],
                        'componentName' => 'sw-field',
                        'type' => 'text',
                    ],
                ],
                [
                    'name' => PhallosanConstants::CUSTOM_FIELD_HS_CODE_EU,
                    'type' => CustomFieldTypes::TEXT,
                    'config' => [
                        'customFieldType' => 'text',
                        'customFieldPosition' => 0,
                        'label' => [
                            'de-DE' => 'HS-Code EU',
                            'en-GB' => 'HS-Code EU',
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
        return 1729519762;
    }

    public function update(Connection $connection): void
    {
        $this->createOrUpdateCustomFields(self::CUSTOM_FIELDS, $connection);
    }
}
