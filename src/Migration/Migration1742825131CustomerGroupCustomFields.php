<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use PhallosanCustomizations\Migration\Trait\MigrationTrait;
use PhallosanCustomizations\PhallosanConstants;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal
 */
class Migration1742825131CustomerGroupCustomFields extends MigrationStep
{
    use MigrationTrait;

    final public const CUSTOM_FIELDS = [
        [
            'name' => 'customer_group_custom_fields',
            'config' => [
                'label' => [
                    'de-DE' => 'Kundengruppen Zusatzfelder',
                    'en-GB' => 'Customer Group Custom Fields',
                ],
                'translated' => true,
            ],
            'relations' => [
                [
                    'entityName' => CustomerGroupDefinition::ENTITY_NAME,
                ],
            ],
            'customFields' => [
                [
                    'name' => PhallosanConstants::CUSTOM_FIELD_CUSTOMER_GROUP_AFFILIATE,
                    'type' => CustomFieldTypes::BOOL,
                    'config' => [
                        'customFieldType' => 'checkbox',
                        'customFieldPosition' => 0,
                        'label' => [
                            'de-DE' => 'Partnerprogramm im Konto anzeigen',
                            'en-GB' => 'Show affiliate in account',
                        ],
                        'componentName' => 'sw-field',
                        'type' => 'checkbox',
                    ],
                ],
                [
                    'name' => PhallosanConstants::CUSTOM_FIELD_CUSTOMER_GROUP_SEO_URL,
                    'type' => CustomFieldTypes::HTML,
                    'config' => [
                        'customFieldType' => 'text',
                        'customFieldPosition' => 1,
                        'label' => [
                            'en-GB' => 'SEO URL',
                            'de-DE' => 'SEO URL',
                        ],
                        'helpText' => [
                            'de-DE' => 'Bitte einen Pfad im Format /xxx/ angeben. Sonderzeichen, Umlaute und Leerzeichen sind nicht erlaubt.',
                            'en-GB' => 'Please enter a path in the format /xxx/. Special characters, umlauts and spaces are not permitted.',
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
        return 1742825131;
    }

    public function update(Connection $connection): void
    {
        $this->createOrUpdateCustomFields(self::CUSTOM_FIELDS, $connection);
    }
}
