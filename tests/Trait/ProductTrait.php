<?php declare(strict_types=1);

namespace PhallosanCustomizations\Tests\Trait;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait ProductTrait
{
    private EntityRepository $productRepository;

    private Context $context;

    private bool $init = false;

    public function initProductTrait(): void
    {
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->context = Context::createDefaultContext();

        $this->init = true;
    }

    public function createTestProductData(?array $mergeData = []): array
    {
        if (!$this->init) {
            throw new \InvalidArgumentException('Trait not initiated. Please call $this->initProductTrait() first');
        }

        $productId = $mergeData['id'] ?? Uuid::randomHex();
        $productNumber = $mergeData['productNumber'] ?? 'Phallosan' . random_int(1, 10000);
        $salesChannelId = $mergeData['salesChannelId'] ?? TestDefaults::SALES_CHANNEL;

        $taxRate = $mergeData['taxRate'] ?? 19;

        $testProductData = [
            'id' => $productId,
            'name' => $mergeData['name'] ?? 'Test Product',
            'productNumber' => $productNumber,
            'tax' => [
                'id' => Uuid::randomHex(),
                'name' => $taxRate . '% VAT',
                'taxRate' => $taxRate,
            ],
            'stock' => $mergeData['stock'] ?? 1000,
            'categories' => $mergeData['categories'] ?? [],
            'purchaseSteps' => $mergeData['purchaseSteps'] ?? 1,
            'minPurchase' => $mergeData['minPurchase'] ?? 1,
            'maxPurchase' => $mergeData['maxPurchase'] ?? null,
            'type' => $mergeData['type'] ?? LineItem::PRODUCT_LINE_ITEM_TYPE,
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => $mergeData['price.gross'] ?? 9.99,
                    'net' => $mergeData['price.net'] ?? 8.32,
                    'linked' => true,
                    'taxRules' => [
                        [
                            'taxRate' => $taxRate,
                            'percentage' => 100,
                        ],
                    ],
                ],
            ],
            'visibilities' => [
                [
                    'id' => Uuid::fromStringToHex($salesChannelId . $productId),
                    'salesChannelId' => $salesChannelId,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
            'active' => $mergeData['active'] ?? true,
            'customFields' => $mergeData['customFields'] ?? null,
            'maxPurchase' => $mergeData['maxPurchase'] ?? 1000,
            'isCloseout' => $mergeData['isCloseout'] ?? false,
            'tags' => $mergeData['tags'] ?? [],
        ];

        if (\array_key_exists('media', $mergeData)) {
            $testProductData['media'] = $mergeData['media'];
        }

        if (\array_key_exists('prices', $mergeData)) {
            $testProductData['prices'] = $mergeData['prices'];
        }

        return $testProductData;
    }

    public function productUpsert(array $productData): void
    {
        if (!$this->init) {
            throw new \InvalidArgumentException('Trait not initiated. Please call $this->initProductTrait() first');
        }

        $this->productRepository->upsert([$productData], $this->context);
    }

    public function createTestProduct(?array $mergeData = []): array
    {
        $productData = $this->createTestProductData($mergeData);

        $this->productUpsert($productData);

        return $productData;
    }

    public function getProductById(string $productId): ?ProductEntity
    {
        return $this->productSearch(new Criteria([$productId]))->first();
    }

    public function productSearch(Criteria $criteria): ?EntitySearchResult
    {
        if (!$this->init) {
            throw new \InvalidArgumentException('Trait not initiated. Please call $this->initProductTrait() first');
        }

        return $this->productRepository->search($criteria, $this->context)->first();
    }

    abstract protected static function getContainer(): ContainerInterface;
}
