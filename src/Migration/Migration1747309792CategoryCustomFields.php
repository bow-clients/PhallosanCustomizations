<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use PhallosanCustomizations\Migration\Trait\MigrationTrait;
use PhallosanCustomizations\PhallosanConstants;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal
 */
class Migration1747309792CategoryCustomFields extends MigrationStep
{
    use MigrationTrait;

    final public const CUSTOM_FIELDS = [
        [
            'name' => 'category_custom_fields',
            'config' => [
                'label' => [
                    'en-GB' => 'Category Customfields',
                    'de-DE' => 'Kategorie Zusatzfelder',
                ],
                'translated' => true,
            ],
            'relations' => [
                [
                    'entityName' => CategoryDefinition::ENTITY_NAME,
                ],
            ],
            'customFields' => [
                [
                    'name' => PhallosanConstants::CUSTOM_FIELD_CATEGORY_SHOW_ONLY_IN_EU,
                    'type' => CustomFieldTypes::BOOL,
                    'config' => [
                        'customFieldType' => 'checkbox',
                        'customFieldPosition' => 1,
                        'label' => [
                            'en-GB' => 'Show only in the EU',
                            'de-DE' => 'Nur in der EU anzeigen',
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
        return 1747309792;
    }

    public function update(Connection $connection): void
    {
        $this->createOrUpdateCustomFields(self::CUSTOM_FIELDS, $connection);
    }
}
