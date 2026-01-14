<?php declare(strict_types=1);

namespace PhallosanCustomizations\Decorator\Sitemap;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Sitemap\Provider\ProductUrlProvider;
use Shopware\Core\Content\Sitemap\Service\ConfigHandler;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\RouterInterface;

class ProductUrlProviderDecorator extends ProductUrlProvider
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ConfigHandler $configHandler,
        private readonly Connection $connection,
        private readonly ProductDefinition $definition,
        private readonly IteratorFactory $iteratorFactory,
        private readonly RouterInterface $router,
        private readonly SystemConfigService $systemConfigService
    ) {
        parent::__construct(
            $this->configHandler,
            $this->connection,
            $this->definition,
            $this->iteratorFactory,
            $this->router,
            $this->systemConfigService
        );
    }

    protected function getSeoUrls(array $ids, string $routeName, SalesChannelContext $context, Connection $connection): array
    {
        $sql = "SELECT
                    DISTINCT LOWER(HEX(product_id)) as product_id
                FROM
                    product_translation
                WHERE
                    JSON_EXTRACT(custom_fields, '$.product_custom_fields_no_detail') = 1
                    AND
                    product_id IN (:ids)";

        try {
            /** @var list<array{productId: string}> $productsWithoutDetailPage */
            $productsWithoutDetailPage = $connection->fetchAllAssociative(
                $sql,
                [
                    'ids' => Uuid::fromHexToBytesList($ids),
                ],
                [
                    'ids' => ArrayParameterType::BINARY,
                ]
            );
        } catch (\Exception $e) {
            $productsWithoutDetailPage = [];
        }

        if (\count($productsWithoutDetailPage) !== 0) {
            /**
             * remove all productIds which have no detail page
             */
            foreach ($productsWithoutDetailPage as $product) {
                if (\in_array($product['productId'], $ids, true)) {
                    $indexInArray = array_search($product['productId'], $ids, true);

                    if ($indexInArray !== false) {
                        unset($ids[$indexInArray]);
                    }
                }
            }
        }

        $ids = array_values($ids);

        return parent::getSeoUrls($ids, $routeName, $context, $connection);
    }
}
