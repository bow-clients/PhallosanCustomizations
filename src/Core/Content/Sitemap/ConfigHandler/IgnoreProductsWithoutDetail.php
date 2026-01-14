<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Content\Sitemap\ConfigHandler;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Sitemap\ConfigHandler\ConfigHandlerInterface;
use Shopware\Core\Content\Sitemap\Service\ConfigHandler;

/**
 * This method loads a list of product ids that should be ignored in the url handling and generation
 */
class IgnoreProductsWithoutDetail implements ConfigHandlerInterface
{
    private static ?array $productIds = null;

    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function getSitemapConfig(): array
    {
        return [
            ConfigHandler::CUSTOM_URLS_KEY => [],
            ConfigHandler::EXCLUDED_URLS_KEY => $this->getExcludedUrlKeys(),
        ];
    }

    private function getExcludedUrlKeys(): array
    {
        if (self::$productIds !== null) {
            return self::$productIds;
        }

        $productsWithoutDetailPage = $this->getProductsWithoutDetail();

        if (\count($productsWithoutDetailPage) === 0) {
            return self::$productIds = [];
        }

        $allSalesChannelIds = $this->getAllSalesChannelIds();

        $resourceList = [];
        foreach ($productsWithoutDetailPage as $productWithoutDetailPage) {
            foreach ($allSalesChannelIds as $salesChannel) {
                $resourceList[] = [
                    'resource' => ProductEntity::class,
                    'identifier' => $productWithoutDetailPage['productId'],
                    'salesChannelId' => $salesChannel['salesChannelId'],
                ];
            }
        }

        return self::$productIds = $resourceList;
    }

    /**
     * @return list<array{productId: string}>
     */
    private function getProductsWithoutDetail(): array
    {
        $sql = "SELECT
                    DISTINCT LOWER(HEX(product_id)) as productId
                FROM
                    product_translation
                WHERE
                    JSON_EXTRACT(custom_fields, '$.product_custom_fields_no_detail') = 1";

        try {
            /** @var list<array{productId: string}> $productsWithoutDetailPage */
            $productsWithoutDetailPage = $this->connection->fetchAllAssociative($sql);
        } catch (\Exception $e) {
            $productsWithoutDetailPage = [];
        }

        return $productsWithoutDetailPage;
    }

    /**
     * @return list<array{salesChannelId: string}>
     */
    private function getAllSalesChannelIds(): array
    {
        $sql = 'SELECT
                    DISTINCT LOWER(HEX(id)) as salesChannelId
                FROM
                    sales_channel';

        try {
            /** @var list<array{salesChannelId: string}> $salesChannelList */
            $salesChannelList = $this->connection->fetchAllAssociative($sql);
        } catch (\Exception $e) {
            $salesChannelList = [];
        }

        return $salesChannelList;
    }
}
