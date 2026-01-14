<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1754041822CustomTaxImportVat extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1754041822;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE custom_tax_rule ADD import_vat DOUBLE DEFAULT 0 after customs_duty
        ');
    }
}
