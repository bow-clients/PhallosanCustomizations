<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1756919719CustomTaxNewColumns extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1756919719;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE custom_tax_rule
                ADD COLUMN `apply_customs_duty` tinyint(1) NOT NULL DEFAULT 0 after `customs_duty`,
                ADD COLUMN `apply_import_vat` tinyint(1) NOT NULL DEFAULT 0 after `import_vat`
        ');
    }
}
