<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use PhallosanCustomizations\PhallosanConstants;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\System\CustomField\CustomFieldDefinition;

/**
 * @internal
 */
class Migration1744121214ProductCustomFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1744121214;
    }

    public function update(Connection $connection): void
    {
        # added "allow_cart_expose"
        $connection->update(
            CustomFieldDefinition::ENTITY_NAME,
            [
                'allow_cart_expose' => 1,
            ],
            ['name' => PhallosanConstants::CUSTOM_FIELD_US_PRODUCT_PRODUCT_NUMBER]
        );

        # added "allow_cart_expose"
        $connection->update(
            CustomFieldDefinition::ENTITY_NAME,
            [
                'allow_cart_expose' => 1,
            ],
            ['name' => PhallosanConstants::CUSTOM_FIELD_PRODUCT_NO_DETAIL]
        );
    }
}
