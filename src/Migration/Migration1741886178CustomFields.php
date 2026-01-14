<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use PhallosanCustomizations\Migration\Trait\MigrationTrait;
use PhallosanCustomizations\PhallosanConstants;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\System\CustomField\CustomFieldDefinition;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class Migration1741886178CustomFields extends MigrationStep
{
    use MigrationTrait;

    final public const CUSTOM_FIELDS = [
        [
            'name' => OrderDefinition::ENTITY_NAME,
            'config' => [
                'label' => [
                    'en-GB' => 'Additional Order Information',
                    'de-DE' => 'Zusätzliche Bestell-Informationen',
                ],
                'translated' => true,
            ],
            'relations' => [
                [
                    'entityName' => OrderDefinition::ENTITY_NAME,
                ],
            ],
            'customFields' => [
                [
                    'name' => PhallosanConstants::CUSTOM_FIELD_ORDER_PACKAGING_INSTRUCTIONS,
                    'type' => CustomFieldTypes::TEXT,
                    'config' => [
                        'label' => [
                            'en-GB' => 'Packaging Instructions',
                            'de-DE' => 'Pack-Anweisungen',
                        ],
                        'type' => 'text',
                        'customFieldPosition' => 1,
                        'componentName' => 'sw-textarea-field',
                        'customFieldType' => 'text',
                    ],
                    'active' => true,
                ],
                [
                    'name' => PhallosanConstants::CUSTOM_FIELD_ORDER_FINECOM_ERROR,
                    'type' => CustomFieldTypes::TEXT,
                    'config' => [
                        'label' => [
                            'en-GB' => 'FineCom Error Message',
                            'de-DE' => 'FineCom Fehlermeldung',
                        ],
                        'type' => 'text',
                        'customFieldPosition' => 1,
                        'componentName' => 'sw-textarea-field',
                        'customFieldType' => 'text',
                        'readonly' => true,
                        'disabled' => true,
                    ],
                    'active' => true,
                ],
            ],
        ],
        [
            'name' => CustomerDefinition::ENTITY_NAME,
            'config' => [
                'label' => [
                    'en-GB' => 'Additional Customer Information',
                    'de-DE' => 'Zusätzliche Kunden-Informationen',
                ],
                'translated' => true,
            ],
            'relations' => [
                [
                    'entityName' => CustomerDefinition::ENTITY_NAME,
                ],
            ],
            'customFields' => [
                [
                    'name' => PhallosanConstants::CUSTOM_FIELD_CUSTOMER_INTERNAL_NOTE,
                    'type' => CustomFieldTypes::TEXT,
                    'config' => [
                        'label' => [
                            'en-GB' => 'Internal Note',
                            'de-DE' => 'Interne Notiz',
                        ],
                        'type' => 'text',
                        'customFieldPosition' => 1,
                        'componentName' => 'sw-textarea-field',
                        'customFieldType' => 'text',
                    ],
                    'active' => true,
                ],
            ],
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1741886178;
    }

    public function update(Connection $connection): void
    {
        # rename "product_hs_code" to "product_hs_code_us" and update label
        $connection->update(
            CustomFieldDefinition::ENTITY_NAME,
            [
                'name' => PhallosanConstants::CUSTOM_FIELD_HS_CODE_US,
                'config' => json_encode([
                    'label' => [
                        'de-DE' => 'HS-Code Zollpflichtig (außerhalb EU)',
                        'en-GB' => 'HS-Code Zollpflichtig (außerhalb EU)',
                    ],
                    'type' => 'text',
                    'customFieldPosition' => 1,
                    'componentName' => 'sw-field',
                    'customFieldType' => 'text',
                ]),
            ],
            ['name' => PhallosanConstants::CUSTOM_FIELD_HS_CODE]
        );

        # update label
        $connection->update(
            CustomFieldDefinition::ENTITY_NAME,
            [
                'config' => json_encode([
                    'label' => [
                        'de-DE' => 'HS-Code nicht Zollpflichtig (innerhalb EU)',
                        'en-GB' => 'HS-Code nicht Zollpflichtig (innerhalb EU)',
                    ],
                    'type' => 'text',
                    'customFieldPosition' => 1,
                    'componentName' => 'sw-field',
                    'customFieldType' => 'text',
                ]),
            ],
            ['name' => PhallosanConstants::CUSTOM_FIELD_HS_CODE_EU]
        );

        $this->createOrUpdateCustomFields(self::CUSTOM_FIELDS, $connection);
    }
}
