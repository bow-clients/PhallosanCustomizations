<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use PhallosanCustomizations\Migration\Trait\MigrationTrait;
use PhallosanCustomizations\PhallosanConstants;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationDefinition;

/**
 * @internal
 */
class Migration1759754748HsCodeCustomfieldRemoveOrderRelation extends MigrationStep
{
    use MigrationTrait;

    public function getCreationTimestamp(): int
    {
        return 1759754748;
    }

    public function update(Connection $connection): void
    {
        $customFieldSetId = $this->fetchCustomFieldSetId(PhallosanConstants::CUSTOM_FIELD_HS_CODE, $connection);

        if (!$customFieldSetId) {
            return;
        }


        # update relation (remove order relation)
        $connection->delete(
            CustomFieldSetRelationDefinition::ENTITY_NAME,
            [
                'entity_name' => OrderDefinition::ENTITY_NAME,
                'set_id' => Uuid::fromHexToBytes($customFieldSetId),
            ]
        );
    }
}
