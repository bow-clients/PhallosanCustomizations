<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use PhallosanCustomizations\Migration\Trait\MigrationTrait;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal
 */
class Migration1759317395CreateCustomerArcField extends MigrationStep
{
    use MigrationTrait;

    final public const CUSTOM_FIELDS = [
        [
            'name' => 'address_custom_fields',
            'config' => [
                'label' => [
                    'en-GB' => 'Customer address',
                    'de-DE' => 'Kundenadresse',
                ],
                'translated' => true,
            ],
            'relations' => [
                [
                    'entityName' => CustomerAddressDefinition::ENTITY_NAME,
                ],
            ],
            'customFields' => [
                [
                    'name' => 'customer_arc_address_field',
                    'type' => CustomFieldTypes::TEXT,
                    'config' => [
                        'customFieldType' => 'text',
                        'customFieldPosition' => 0,
                        'label' => [
                            'en-GB' => 'ARC ID',
                            'de-DE' => 'ARC ID',
                        ],
                        'type' => 'text',
                        'componentName' => 'sw-field',
                    ],
                    'allow_cart_expose' => 1,
                    'allow_customer_write' => 1,
                ],
            ],
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1759317395;
    }

    public function update(Connection $connection): void
    {
        $this->createOrUpdateCustomFields(self::CUSTOM_FIELDS, $connection);
    }
}
