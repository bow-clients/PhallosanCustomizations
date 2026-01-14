<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1751824162AddCustomTaxRule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1751824162;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS custom_tax_rule (
                id BINARY(16) NOT NULL,
                tax_rule_id BINARY(16) NOT NULL,
                customs_duty DOUBLE DEFAULT 0,
                created_at DATETIME(3) NOT NULL,
                updated_at DATETIME(3),
                PRIMARY KEY (id),
                CONSTRAINT fk_tax_rule FOREIGN KEY (tax_rule_id) REFERENCES tax_rule (id) ON DELETE CASCADE
            );
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
