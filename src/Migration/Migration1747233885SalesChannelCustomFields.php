<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use PhallosanCustomizations\Migration\Trait\MigrationTrait;
use PhallosanCustomizations\PhallosanConstants;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @internal
 */
class Migration1747233885SalesChannelCustomFields extends MigrationStep
{
    use MigrationTrait;

    final public const CUSTOM_FIELDS = [
        [
            'name' => 'sales_channel_custom_fields',
            'config' => [
                'label' => [
                    'en-GB' => 'Documents Customfields',
                    'de-DE' => 'Dokumente Zusatzfelder',
                ],
                'translated' => true,
            ],
            'relations' => [
                [
                    'entityName' => SalesChannelDefinition::ENTITY_NAME,
                ],
            ],
            'customFields' => [
                [
                    'name' => PhallosanConstants::CUSTOM_FIELD_SALES_CHANNEL_INVOICE_TEXT,
                    'type' => CustomFieldTypes::HTML,
                    'config' => [
                        'customFieldType' => 'text',
                        'customFieldPosition' => 1,
                        'label' => [
                            'en-GB' => 'Customised text on the invoice',
                            'de-DE' => 'Individueller Text auf der Rechnung',
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
        return 1747233885;
    }

    public function update(Connection $connection): void
    {
        $this->createOrUpdateCustomFields(self::CUSTOM_FIELDS, $connection);
    }
}
