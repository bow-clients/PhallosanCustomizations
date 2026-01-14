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
class Migration1744383109StandardLanguageSwap_to_en_GB extends MigrationStep
{
    use MigrationTrait;

    private Connection $connection;

    public function getCreationTimestamp(): int
    {
        return 1744383109;
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

        $languageIdEn = $this->getLocaleId($connection, 'en-US');
        $languageIdEnGB = $this->getLocaleId($connection, 'en-GB');

        if (!$languageIdEn || !$languageIdEnGB) {
            return;
        }

        /** @phpstan-ignore-next-line */
        $connection->update('language', ['parent_id' => null], ['1' => '1']);

        $currentStandardLanguage = $languageIdEn;
        $newStandardLanguage = $languageIdEnGB;

        $connection->update('language', ['id' => $tempLanguageId], ['id' => $currentStandardLanguage]);
        $connection->update('language', ['id' => $currentStandardLanguage], ['id' => $newStandardLanguage]);
        $connection->update('language', ['id' => $newStandardLanguage], ['id' => $tempLanguageId]);


        $connection->insert(
            'language',
            [
                'id' => $tempLanguageId,
                'name' => 'temp_language',
                'locale_id' => $tempLanguageId,
                'created_at' => $createdAt,
            ]
        );

        $connection->update('sales_channel_language', ['language_id' => $tempLanguageId], ['language_id' => $currentStandardLanguage]);
        $connection->update('sales_channel_language', ['language_id' => $currentStandardLanguage], ['language_id' => $newStandardLanguage]);
        $connection->update('sales_channel_language', ['language_id' => $newStandardLanguage], ['language_id' => $tempLanguageId]);

        //switch values to work with the new keys
        $tempId = $currentStandardLanguage;
        $currentStandardLanguage = $newStandardLanguage;
        $newStandardLanguage = $tempId;

        //get all translation tables
        $translationTables = $this->connection->fetchFirstColumn(
            "SHOW TABLES LIKE '%_translation'"
        );

        foreach ($translationTables as $tableName) {
            $columnKey = str_ireplace('_translation', '_id', $tableName);
            if ($this->checkForTranslatedValue($tableName, $currentStandardLanguage)) {
                $allDeEntries = $this->getAllTranslations($tableName, $currentStandardLanguage);


                foreach ($allDeEntries as $entry) {
                    //fetch all data to check whether a row exist or the value ist just null
                    if (!empty($entry)) {
                        $translatedColumns = $connection->fetchAssociative(
                            '
                        SELECT * FROM ' . $tableName . '
                        WHERE ' . $columnKey . ' = UNHEX(:keyColumnValue) AND language_id = UNHEX(:language_id)',
                            ['keyColumnValue' => bin2hex($entry[$columnKey]), 'language_id' => bin2hex($newStandardLanguage)]
                        );

                        //row can exist and have value a value of null -> copy german translation
                        //row does not exist with that languageKey which is now the default language but the en-GB translation exist -> duplicate en-GB translation and change languageKey
                        if (!empty($translatedColumns)) {
                            foreach ($translatedColumns as $column => $value) {
                                if ($value === null) {
                                    $translatedColumns[$column] = $entry[$column];
                                }
                            }

                            /* @phpstan-ignore offsetAccess.invalidOffset */
                            unset($translatedColumns[$columnKey]);

                            $this->connection->update(
                                $tableName,
                                $translatedColumns,
                                ['language_id' => $newStandardLanguage,
                                    $columnKey => $entry[$columnKey]]/* @phpstan-ignore array.invalidKey */
                            );
                        } elseif (!$translatedColumns) {
                            $altTranslation = $connection->fetchAssociative(
                                '
                            SELECT * FROM ' . $tableName . ' 
                            WHERE ' . $columnKey . ' = UNHEX(:keyColumnValue) AND language_id = UNHEX(:language_id)',
                                ['keyColumnValue' => bin2hex($entry[$columnKey]), 'language_id' => bin2hex($languageIdEnGB)]
                            );

                            if (!$altTranslation) {
                                $altTranslation = $connection->fetchAssociative(
                                    '
                            SELECT * FROM ' . $tableName . ' 
                            WHERE ' . $columnKey . ' = UNHEX(:keyColumnValue) AND language_id = UNHEX(:language_id)',
                                    ['keyColumnValue' => bin2hex($entry[$columnKey]), 'language_id' => bin2hex($currentStandardLanguage)]
                                );

                                if (!$altTranslation) {
                                    continue;
                                }
                            }

                            $this->connection->insert($tableName, array_merge($altTranslation, ['language_id' => $newStandardLanguage]));
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

    private function checkForTranslatedValue(string $tableName, string $currentStandardLanguage): bool
    {
        $deEntry = $this->connection->fetchAssociative('
        SELECT *
        FROM ' . $tableName . '
        WHERE `language_id` = :language_id
        ', ['language_id' => $currentStandardLanguage]);

        if (!$deEntry) {
            return false;
        }

        return true;
    }

    private function getAllTranslations(string $tableName, string $currentStandardLanguage): array
    {
        $allDeEntries = $this->connection->fetchAllAssociative('
            SELECT *
            FROM ' . $tableName . '
            WHERE `language_id` = :language_id
            ', ['language_id' => $currentStandardLanguage]);

        return $allDeEntries;
    }
}
