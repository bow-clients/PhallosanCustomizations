<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use PhallosanCustomizations\Migration\Trait\MigrationTrait;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class Migration1742481720StandardLanguageSwap extends MigrationStep
{
    use MigrationTrait;

    private Connection $connection;

    public function getCreationTimestamp(): int
    {
        return 1742481720;
    }

    public function update(Connection $connection): void
    {
        $this->connection = $connection;

        $tempLanguageId = Uuid::randomBytes();
        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $connection->insert(
            'locale',
            [
                'id' => $tempLanguageId,
                'code' => 'temp_locale',
                'created_at' => $createdAt,
            ]
        );

        $languageIdDe = $this->getLocaleId($connection, 'de-DE');
        $languageIdEn = $this->getLocaleId($connection, 'en-US');
        $languageIdEnGB = $this->getLocaleId($connection, 'en-GB');

        $connection->update('language', ['parent_id' => null], ['1' => '1']); /* @phpstan-ignore argument.type */

        $connection->update('language', ['id' => $tempLanguageId], ['id' => $languageIdDe]);
        $connection->update('language', ['id' => $languageIdDe], ['id' => $languageIdEn]);
        $connection->update('language', ['id' => $languageIdEn], ['id' => $tempLanguageId]);


        $connection->insert(
            'language',
            [
                'id' => $tempLanguageId,
                'name' => 'temp_language',
                'locale_id' => $tempLanguageId,
                'created_at' => $createdAt,
            ]
        );

        $connection->update('sales_channel_language', ['language_id' => $tempLanguageId], ['language_id' => $languageIdDe]);
        $connection->update('sales_channel_language', ['language_id' => $languageIdDe], ['language_id' => $languageIdEn]);
        $connection->update('sales_channel_language', ['language_id' => $languageIdEn], ['language_id' => $tempLanguageId]);

        //switch values to work with the new keys
        $tempId = $languageIdDe;
        $languageIdDe = $languageIdEn;
        $languageIdEn = $tempId;

        //get all translation tables
        $translationTables = $this->connection->fetchFirstColumn(
            "SHOW TABLES LIKE '%_translation'"
        );

        foreach ($translationTables as $tableName) {
            $columnKey = str_ireplace('_translation', '_id', $tableName);
            if ($this->checkForGermanValue($tableName, $languageIdDe)) { /* @phpstan-ignore argument.type */
                $allDeEntries = $this->getAllGermanTranslations($tableName, $languageIdDe); /* @phpstan-ignore argument.type */

                foreach ($allDeEntries as $entry) {
                    //fetch all data to check whether a row exist or the value ist just null
                    if (!empty($entry)) {
                        $translatedColumns = $connection->fetchAssociative(
                            '
                        SELECT * FROM ' . $tableName . ' 
                        WHERE ' . $columnKey . ' = UNHEX(:keyColumnValue) AND language_id = UNHEX(:language_id)',
                            ['keyColumnValue' => bin2hex($entry[$columnKey]), 'language_id' => bin2hex($languageIdEn)] /* @phpstan-ignore argument.type */
                        );

                        //row can exist and have value a value of null -> copy german translation
                        //row does not exist with that languageKey which is now the default language but the en-GB translation exist -> duplicate en-GB translation and change languageKey
                        if (!empty($translatedColumns)) {
                            foreach ($translatedColumns as $column => $value) {
                                if ($value === null) {
                                    $translatedColumns[$column] = $entry[$column];
                                }
                            }

                            unset($translatedColumns[$columnKey]);  /* @phpstan-ignore offsetAccess.invalidOffset */

                            $this->connection->update(
                                $tableName,
                                $translatedColumns,
                                ['language_id' => $languageIdEn,
                                    $columnKey => $entry[$columnKey]] /* @phpstan-ignore array.invalidKey */
                            );
                        } elseif (!$translatedColumns) {
                            $altTranslation = $connection->fetchAssociative(
                                '
                            SELECT * FROM ' . $tableName . ' 
                            WHERE ' . $columnKey . ' = UNHEX(:keyColumnValue) AND language_id = UNHEX(:language_id)',
                                ['keyColumnValue' => bin2hex($entry[$columnKey]), 'language_id' => bin2hex($languageIdEnGB)] /* @phpstan-ignore argument.type */
                            );

                            if (!$altTranslation) {
                                $altTranslation = $connection->fetchAssociative(
                                    '
                            SELECT * FROM ' . $tableName . ' 
                            WHERE ' . $columnKey . ' = UNHEX(:keyColumnValue) AND language_id = UNHEX(:language_id)',
                                    ['keyColumnValue' => bin2hex($entry[$columnKey]), 'language_id' => bin2hex($languageIdDe)] /* @phpstan-ignore argument.type */
                                );

                                if (!$altTranslation) {
                                    continue;
                                }
                            }

                            $this->connection->insert($tableName, array_merge($altTranslation, ['language_id' => $languageIdEn]));
                        }
                    }
                }
            }

            $connection->delete(
                'locale',
                [
                    'code' => 'temp_locale',
                ]
            );

            $connection->delete(
                'language',
                [
                    'name' => 'temp_language',
                ]
            );
        }
    }

    private function checkForGermanValue(string $tableName, string $languageIdDe): bool
    {
        $deEntry = $this->connection->fetchAssociative('
        SELECT *
        FROM ' . $tableName . '
        WHERE `language_id` = :language_id
        ', ['language_id' => $languageIdDe]);

        if (!$deEntry) {
            return false;
        }

        return true;
    }

    private function getAllGermanTranslations(string $tableName, string $languageIdDe): array
    {
        $allDeEntries = $this->connection->fetchAllAssociative('
            SELECT *
            FROM ' . $tableName . '
            WHERE `language_id` = :language_id
            ', ['language_id' => $languageIdDe]);

        return $allDeEntries;
    }
}
