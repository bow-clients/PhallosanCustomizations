<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1751484522AddOrderCommentTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1751484522;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `order_comment` (
    `id` BINARY(16) NOT NULL,
    `order_id` BINARY(16) NOT NULL,
    `order_version_id` BINARY(16) NOT NULL,
    `comment` LONGTEXT NOT NULL,
    `is_public` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk.order_comment.order_id`
        FOREIGN KEY (`order_id`, `order_version_id`)
        REFERENCES `order` (`id`, `version_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->executeStatement($sql);
    }
}
