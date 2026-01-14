<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use PhallosanCustomizations\Migration\Trait\MigrationTrait;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class Migration1745398167CreateSalesChannels extends MigrationStep
{
    use MigrationTrait;

    public const SALES_CHANNEL_NAME = 'PHALLOSAN';

    private Connection $connection;

    public function getCreationTimestamp(): int
    {
        return 1745398167;
    }

    public function update(Connection $connection): void
    {
        $this->connection = $connection;

        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $oldSalesChannels = $this->getAllSalesChannels();

        $mainSalesChannel = $this->getSalesChannel('Phallosan - EN (US)');

        $languageIdEn = $this->getLocaleId($connection, 'en-GB');
        $currencyIdUsd = $this->getCurrencyId($connection, 'USD');
        $snippetSetId = $this->getSnippetSetId('en-GB');
        $mainPaymentMethodId = $mainSalesChannel['payment_method_id'];
        $paymentMethods = $mainSalesChannel['payment_methods'];
        $mainShippingMethodId = $mainSalesChannel['shipping_method_id'];
        $shippingMethods = $mainSalesChannel['shipping_methods'];
        $navigationCategoryId = $mainSalesChannel['navigation_category_id'];
        $navigationCategoryVersionId = $mainSalesChannel['navigation_category_version_id'];
        $homeCmsPageVersionId = $mainSalesChannel['home_cms_page_version_id'];
        $footerCategoryId = $mainSalesChannel['footer_category_id'];
        $footerCategoryVersionId = $mainSalesChannel['footer_category_version_id'];
        $serviceCategoryId = $mainSalesChannel['service_category_id'];
        $serviceCategoryVersionId = $mainSalesChannel['service_category_version_id'];
        $customerGroupId = $mainSalesChannel['customer_group_id'];

        $countries = $this->getCountries();
        $products = $this->getAllProducts();
        $salesChannelTypeId = $this->getSalesChannelTypeId();

        $connection->executeStatement('
                TRUNCATE document;
                TRUNCATE order_delivery_position;   
                  
            ');

        $connection->executeStatement('
                DELETE FROM `order`;
                DELETE FROM order_delivery;
                DELETE FROM order_address;
                DELETE FROM order_customer;
                DELETE FROM customer;
                DELETE FROM newsletter_recipient;
            ');

        foreach ($oldSalesChannels as $oldSalesChannel) {
            $connection->update(
                'sales_channel',
                ['active' => 0],
                ['id' => $oldSalesChannel['id']]
            );

            $connection->delete('sales_channel_domain', [
                'sales_channel_id' => $oldSalesChannel['id'],
            ]);

            $connection->delete('sales_channel', [
                'id' => $oldSalesChannel['id'],
            ]);
        }

        foreach ($countries as $country) {
            $id = Uuid::randomBytes();
            $connection->insert('sales_channel', [
                'id' => $id,
                'type_id' => $salesChannelTypeId,
                'access_key' => AccessKeyHelper::generateAccessKey('sales-channel'),
                'active' => 1,
                'language_id' => $languageIdEn,
                'currency_id' => $currencyIdUsd,
                'payment_method_id' => $mainPaymentMethodId,
                'shipping_method_id' => $mainShippingMethodId,
                'country_id' => $country['id'],
                'navigation_category_id' => $navigationCategoryId,
                'navigation_category_version_id' => $navigationCategoryVersionId,
                'home_cms_page_version_id' => $homeCmsPageVersionId,
                'footer_category_id' => $footerCategoryId,
                'footer_category_version_id' => $footerCategoryVersionId,
                'service_category_id' => $serviceCategoryId,
                'service_category_version_id' => $serviceCategoryVersionId,
                'customer_group_id' => $customerGroupId,
                'created_at' => $createdAt,
            ]);

            $connection->insert('sales_channel_translation', [
                'sales_channel_id' => $id,
                'language_id' => $languageIdEn,
                'name' => self::SALES_CHANNEL_NAME . ' - ' . $country['iso'],
                'created_at' => $createdAt,
            ]);

            $connection->insert('sales_channel_country', [
                'sales_channel_id' => $id,
                'country_id' => $country['id'],
            ]);

            $connection->insert('sales_channel_language', [
                'sales_channel_id' => $id,
                'language_id' => $languageIdEn,
            ]);

            $connection->insert('sales_channel_currency', [
                'sales_channel_id' => $id,
                'currency_id' => $currencyIdUsd,
            ]);

            $connection->insert('theme_sales_channel', [
                'theme_id' => $mainSalesChannel['theme_id'],
                'sales_channel_id' => $id,
            ]);

            foreach ($products as $product) {
                $connection->insert('product_visibility', [
                    'id' => Uuid::randomBytes(),
                    'product_id' => $product['id'],
                    'product_version_id' => $product['version_id'],
                    'sales_channel_id' => $id,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                    'created_at' => $createdAt,
                ]);
            }

            foreach ($paymentMethods as $paymentMethod) {
                $connection->insert('sales_channel_payment_method', [
                    'sales_channel_id' => $id,
                    'payment_method_id' => $paymentMethod['payment_method_id'],
                ]);
            }

            foreach ($shippingMethods as $shippingMethod) {
                $connection->insert('sales_channel_shipping_method', [
                    'sales_channel_id' => $id,
                    'shipping_method_id' => $shippingMethod['shipping_method_id'],
                ]);
            }

            $basicDomain = $mainSalesChannel['url'] . '/' . strtolower($country['iso']);

            $connection->insert('sales_channel_domain', [
                'id' => Uuid::randomBytes(),
                'sales_channel_id' => $id,
                'language_id' => $languageIdEn,
                'url' => $basicDomain,
                'currency_id' => $currencyIdUsd,
                'snippet_set_id' => $snippetSetId,
                'created_at' => $createdAt,
            ]);
        }
    }

    private function getAllProducts(): array
    {
        return $this->connection->fetchAllAssociative('
            SELECT id, version_id
            FROM product');
    }

    private function getSnippetSetId(string $iso): string
    {
        return $this->connection->fetchOne(
            '
        SELECT id
        FROM snippet_set
        WHERE iso = :iso',
            ['iso' => $iso]
        );
    }

    private function getSalesChannelTypeId(): string
    {
        return $this->connection->fetchOne('
            SELECT `sales_channel_type_id`
            FROM `sales_channel_type_translation`
            WHERE `name`= :name
        ', ['name' => 'Storefront']);
    }

    private function getCountries(): array
    {
        return $this->connection->fetchAllAssociative('
            SELECT iso, id
            FROM `country`
        ');
    }

    private function getSalesChannel(string $salesChannelName): array
    {
        $salesChannel = $this->connection->fetchAssociative('
            SELECT 
                sc.id,
                sc.payment_method_id,
                sc.shipping_method_id,
                sc.navigation_category_id,
                sc.navigation_category_version_id,
                sc.home_cms_page_version_id,
                sc.footer_category_id,
                sc.footer_category_version_id,
                sc.service_category_id,
                sc.service_category_version_id,
                sc.customer_group_id,
                scd.url,
                scd.snippet_set_id
            FROM `sales_channel_translation` sct
            LEFT JOIN `sales_channel` sc ON sct.sales_channel_id = sc.id
            LEFT JOIN sales_channel_domain scd ON sc.id = scd.sales_channel_id 
            WHERE sct.name = :name
        ', ['name' => $salesChannelName]);

        if (\is_array($salesChannel)) {
            $salesChannel['payment_methods'] = $this->connection->fetchAllAssociative('
            SELECT payment_method_id
            FROM `sales_channel_payment_method`
            WHERE sales_channel_id = :id
            ', ['id' => $salesChannel['id']]);

            $salesChannel['shipping_methods'] = $this->connection->fetchAllAssociative('
            SELECT shipping_method_id
            FROM `sales_channel_shipping_method`
            WHERE sales_channel_id = :id
            ', ['id' => $salesChannel['id']]);

            $salesChannel['theme_id'] = $this->connection->fetchOne('
            SELECT theme_id
            FROM `theme_sales_channel`
            ', ['sales_channel_id' => $salesChannel['id']]);
        }

        return \is_array($salesChannel) ? $salesChannel : [];
    }

    private function getAllSalesChannels(): array
    {
        return $this->connection->fetchAllAssociative('
           SELECT * 
           FROM `sales_channel`
        ');
    }
}
