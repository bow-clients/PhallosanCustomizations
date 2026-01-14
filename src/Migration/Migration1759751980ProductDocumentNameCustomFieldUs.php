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
class Migration1759751980ProductDocumentNameCustomFieldUs extends MigrationStep
{
    use MigrationTrait;

    final public const CUSTOM_FIELDS = [
        [
            'name' => 'product_documents_custom_fields',
            'config' => [
                'label' => [
                    'de-DE' => 'Dokumente',
                    'en-GB' => 'Documents',
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
                    'name' => PhallosanConstants::CUSTOM_FIELD_DOCUMENTS_PRODUCT_NAME_US,
                    'type' => CustomFieldTypes::TEXT,
                    'config' => [
                        'customFieldType' => 'text',
                        'customFieldPosition' => 0,
                        'label' => [
                            'de-DE' => 'Produktname in Dokumenten (US)',
                            'en-GB' => 'Product name in documents (US)',
                        ],
                        'componentName' => 'sw-field',
                        'type' => 'text',
                    ],
                    'allow_cart_expose' => 1,
                ],
            ],
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1759751980;
    }

    public function update(Connection $connection): void
    {
        $this->createOrUpdateCustomFields(self::CUSTOM_FIELDS, $connection);
    }
}
