<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirement\AccessoryRequirementDefinition;
use PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirement\Extensions\ProductExtension;
use PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirementOrder\AccessoryRequirementOrderDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Migration\InheritanceUpdaterTrait;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1742729301AccessoryRequirementDatabaseStructure extends MigrationStep
{
    use InheritanceUpdaterTrait;

    public function getCreationTimestamp(): int
    {
        return 1742729301;
    }

    public function update(Connection $connection): void
    {
        // create accessory requirement entity table
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `' . AccessoryRequirementDefinition::ENTITY_NAME . '` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NULL,
                `description` VARCHAR(255) NULL,
                `custom_fields` json DEFAULT NULL,
                `product_id` binary(16) DEFAULT NULL,
                `product_version_id` binary(16) DEFAULT NULL,
                `required_product_id` binary(16) DEFAULT NULL,
                `required_product_version_id` binary(16) DEFAULT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              KEY `fk.accessory_requirement.product_id__product_version_id` (`product_id`,`product_version_id`),
              KEY `fk.accessory_requirement.required_product_id__product_version_id` (`required_product_id`,`required_product_version_id`),
              UNIQUE KEY `uniq.accessory_requirement.product_id` (`product_id`),
              CONSTRAINT `fk.accessory_requirement.product_id__product_version_id` FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE SET NULL,
              CONSTRAINT `fk.accessory_requirement.required_product_id__product_version_id` FOREIGN KEY (`required_product_id`, `required_product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        // add accessory requirement column to the product entity
        $columnsOfTableProduct = $connection->getSchemaManager()->listTableDetails(ProductDefinition::ENTITY_NAME)->getColumns();

        if (!\array_key_exists(strtolower(ProductExtension::NAME), $columnsOfTableProduct)) {
            $this->updateInheritance($connection, ProductDefinition::ENTITY_NAME, ProductExtension::NAME);
        }

        // create accessory requirement order entity table
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `' . AccessoryRequirementOrderDefinition::ENTITY_NAME . '` (
                `id` BINARY(16) NOT NULL,
                `accessory_requirement_id` binary(16) NOT NULL,
                `order_id` binary(16) DEFAULT NULL,
                `order_number` VARCHAR(255) NOT NULL,
                `redeemed_order_id` binary(16) DEFAULT NULL,
                `redeemed_order_number` VARCHAR(255) DEFAULT NULL,
                `comment` VARCHAR(255) DEFAULT NULL,
                `migrated` tinyint unsigned DEFAULT 0,
                `custom_fields` json DEFAULT NULL,
                `migrated_at` DATETIME(3) NULL,
                `redeemed_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              KEY `fk.accessory_requirement_order.accessory_requirement_id` (`accessory_requirement_id`),
              UNIQUE KEY `uniq.accessory_requirement_order.order_number` (`order_number`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
