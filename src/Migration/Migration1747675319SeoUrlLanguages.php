<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use PhallosanCustomizations\Migration\Trait\MigrationTrait;
use PhallosanCustomizations\PhallosanConstants;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class Migration1747675319SeoUrlLanguages extends MigrationStep
{
    use MigrationTrait;

    public function getCreationTimestamp(): int
    {
        return 1747675319;
    }

    public function update(Connection $connection): void
    {
        $key = PhallosanConstants::PLUGIN_CONFIG_SEO_URL_LANGUAGE;
        $query = $connection->fetchOne(
            'SELECT `id` FROM `system_config` WHERE `configuration_key` =  :key;',
            ['key' => $key]
        );

        $languages = [
            $this->getLocaleId($connection, 'ja-JP') ? bin2hex($this->getLocaleId($connection, 'ja-JP')) : '',
            $this->getLocaleId($connection, 'hi-IN') ? bin2hex($this->getLocaleId($connection, 'hi-IN')) : '',
            $this->getLocaleId($connection, 'th-TH') ? bin2hex($this->getLocaleId($connection, 'th-TH')) : '',
            $this->getLocaleId($connection, 'zh-CN') ? bin2hex($this->getLocaleId($connection, 'zh-CN')) : '',
            $this->getLocaleId($connection, 'ko-KR') ? bin2hex($this->getLocaleId($connection, 'ko-KR')) : '',
        ];

        $languages = array_filter($languages, fn ($locale) => $locale !== '');

        $data = ['_value' => $languages];

        $jsonData = json_encode($data);

        if ($query) {
            $connection->update('system_config', [
                'configuration_value' => $jsonData,
            ], [
                'configuration_key' => $key,
            ]);
        } else {
            $connection->insert('system_config', [
                'id' => Uuid::randomBytes(),
                'configuration_value' => $jsonData,
                'configuration_key' => $key,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }
}
