<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use PhallosanCustomizations\PhallosanConstants;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1741900976Tags extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1741900976;
    }

    public function update(Connection $connection): void
    {
        $tagList = [
            PhallosanConstants::TAG_FINECOM_FETCHABLE => PhallosanConstants::TAG_FINECOM_FETCHABLE_ID,
            PhallosanConstants::TAG_DELIVERY_INCOMPLETE => PhallosanConstants::TAG_DELIVERY_INCOMPLETE_ID,
            PhallosanConstants::TAG_FINECOM_FETCHED => PhallosanConstants::TAG_FINECOM_FETCHED_ID,
            PhallosanConstants::TAG_RETURNED => PhallosanConstants::TAG_RETURNED_ID,
            PhallosanConstants::TAG_ERROR => PhallosanConstants::TAG_ERROR_ID,
        ];

        foreach ($tagList as $tagName => $tagId) {
            /** @var string $existingTagId */
            $existingTagId = $connection->fetchOne('
                SELECT id
                FROM `tag`
                WHERE `name` = :name
            ', ['name' => $tagName]);

            if (!$existingTagId) {
                $connection->insert('tag', [
                    'id' => Uuid::fromHexToBytes($tagId),
                    'name' => $tagName,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
            }
        }
    }
}
