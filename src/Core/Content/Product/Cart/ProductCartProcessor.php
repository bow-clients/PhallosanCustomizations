<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Content\Product\Cart;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Dvsn\Bundle\Service\Cart\LineItemFactoryServiceInterface;
use PhallosanCustomizations\PhallosanConstants;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Content\Product\Cart\ProductFeatureBuilder;
use Shopware\Core\Content\Product\Cart\ProductGatewayInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Price\AbstractProductPriceCalculator;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tax\TaxEntity;

class ProductCartProcessor implements CartProcessorInterface, CartDataCollectorInterface
{
    final public const CUSTOM_PRICE = 'customPrice';

    final public const ALLOW_PRODUCT_PRICE_OVERWRITES = 'allowProductPriceOverwrites';

    final public const SKIP_PRODUCT_RECALCULATION = 'skipProductRecalculation';

    final public const KEEP_INACTIVE_PRODUCT = 'keepInactiveProduct';

    /**
     * @internal
     */
    public function __construct(
        private readonly ProductGatewayInterface $productGateway,
        private readonly ProductFeatureBuilder $featureBuilder,
        private readonly AbstractProductPriceCalculator $priceCalculator,
        private readonly EntityCacheKeyGenerator $generator,
        private readonly EntityRepository $productRepository,
        private readonly Connection $connection
    ) {
    }

    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        Profiler::trace('phallosan::cart::product::collect', function () use ($data, $original, $context, $behavior): void {
            $lineItems = $this->getProducts($original->getLineItems());

            $items = array_column($lineItems, 'item');

            $hash = $this->getDataContextHash($context);

            // find products in original cart which requires data from gateway
            $ids = $this->getNotCompleted($data, $items, $hash);

            if (!empty($ids)) {
                // fetch missing data over gateway
                $products = $this->productGateway->get($ids, $context);

                // add products to data collection
                foreach ($products as $product) {
                    $data->set($this->getDataKey($product->getId()), $product);
                }

                if (!Feature::isActive('v6.7.0.0') && !Feature::isActive('PERFORMANCE_TWEAKS')) {
                    // refresh data timestamp to prevent unnecessary gateway calls
                    foreach ($items as $lineItem) {
                        $product = $products->get((string) $lineItem->getReferencedId());

                        if ($product) {
                            $lineItem->setDataTimestamp(new \DateTimeImmutable());
                        }
                    }
                }
            }

            // refresh data timestamp to prevent unnecessary gateway calls
            foreach ($items as $lineItem) {
                $product = $data->get($this->getDataKey($lineItem->getReferencedId() ?: ''));

                // product was fetched, update timestamp to not fetch it again
                if ($product instanceof ProductEntity) {
                    if (Feature::isActive('v6.7.0.0') || Feature::isActive('PERFORMANCE_TWEAKS')) {
                        $lineItem->setDataTimestamp($product->getUpdatedAt() ?? $product->getCreatedAt());
                    }
                    // we have asked for this product, but we didn't get it back, so we need to remove it
                } elseif (\in_array($lineItem->getReferencedId(), $ids, true)) {
                    $lineItem->setDataTimestamp(null);
                }

                // no matter if we fetched data or not, we need to set the hash to all products in case it changed
                // so the next time we need to calculate and there is no data, we know to fetch it again
                $lineItem->setDataContextHash($hash);
            }

            foreach ($lineItems as $match) {
                // replace not valid products (in the saleschannel) with the alternative products, if configured
                $this->validateNonAvailableProductInSaleschannel($match['item'], $original, $data, $behavior, $match['scope'], $match['parent'], $context);

                // remove invalid dvsn-bundle-product line items
                $this->validateBundleProductAvailability($match['item'], $original, $data, $behavior, $match['scope'], $match['parent'], $context);
            }

            // run price calculator in batch
            $this->recalculate(array_column($lineItems, 'item'), $data, $context, $behavior);

            $this->featureBuilder->prepare($items, $data, $context);
        }, 'cart');
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
    }

    /**
     * @return list<array{'item': LineItem, 'scope': LineItemCollection, 'parent': ?LineItem}>
     */
    private function getProducts(LineItemCollection $items): array
    {
        $allowedTypes = [
            LineItem::PRODUCT_LINE_ITEM_TYPE,
            LineItemFactoryServiceInterface::BUNDLE_PRODUCT_LINE_ITEM_TYPE,
        ];

        $matches = [];
        foreach ($items as $item) {
            if (\in_array($item->getType(), $allowedTypes, true)) {
                $matches[] = ['item' => $item, 'scope' => $items, 'parent' => null];
            }

            $nested = $this->getProducts($item->getChildren());

            foreach ($nested as $match) {
                $match['parent'] = $item;
                $matches[] = $match;
            }
        }

        return $matches;
    }

    private function validateBundleProductAvailability(LineItem $item, Cart $cart, CartDataCollection $data, CartBehavior $behavior, LineItemCollection $items, ?LineItem $parent, SalesChannelContext $context): void
    {
        if ($item->getType() !== LineItemFactoryServiceInterface::BUNDLE_PRODUCT_LINE_ITEM_TYPE) {
            return;
        }

        $product = $data->get(
            $this->getDataKey((string) $item->getReferencedId())
        );

        if ($product) {
            return;
        }

        $items->remove($item->getId());
    }

    private function validateNonAvailableProductInSaleschannel(LineItem $item, Cart $cart, CartDataCollection $data, CartBehavior $behavior, LineItemCollection $items, ?LineItem $parent, SalesChannelContext $context): void
    {
        $product = $data->get(
            $this->getDataKey((string) $item->getReferencedId())
        );

        // product data was never detected and the product is not inside the data collection
        if ($product !== null || $item->getDataTimestamp() !== null) {
            return;
        }

        if ($behavior->hasPermission(self::KEEP_INACTIVE_PRODUCT)) {
            return;
        }

        $itemCustomFields = $item->getPayloadValue('customFields');
        $alternativeProductId = $itemCustomFields[PhallosanConstants::CUSTOM_FIELD_PRODUCT_ALTERNATIVE_PRODUCT] ?? null;

        $productNumber = $alternativeProductNumber = $item->getPayloadValue('productNumber');
        if (!$alternativeProductId) {
            if (strpos($productNumber, '-US') !== false) {
                $alternativeProductNumber = str_replace('-US', '', $productNumber);
            } else {
                $alternativeProductNumber = $productNumber . '-US';
            }

            if ($productNumber !== $alternativeProductNumber) {
                $alternativeProductId = $this->getProductIdByProductNumber($alternativeProductNumber);
            }
        }

        if (!$alternativeProductId && $productNumber !== $alternativeProductNumber) {
            return;
        }

        $product = $this->productGateway->get([$alternativeProductId], $context)->first();

        if (!$product) {
            return;
        }

        $bundleProductId = $item->getPayloadValue('dvsnBundleProductId');
        if ($bundleProductId === $item->getReferencedId()) {
            $item->setPayloadValue('dvsnBundleProductId', $product->getId());
        }
        if ($parent) {
            $bundleProductId = $parent->getPayloadValue('dvsnBundleProductId');
            if ($bundleProductId === $item->getReferencedId()) {
                $parent->setPayloadValue('dvsnBundleProductId', $product->getId());
            }
        }

        if ($item->getType() === LineItemFactoryServiceInterface::BUNDLE_PRODUCT_LINE_ITEM_TYPE) {
            $item->setPayloadValue('productNumber', $product->getProductNumber());
        }

        $data->set($this->getDataKey($product->getId()), $product);

        $item->setReferencedId($product->getId());
    }

    /**
     * @param LineItem[] $lineItems
     *
     * @return mixed[]
     */
    private function getNotCompleted(CartDataCollection $data, array $lineItems, string $hash): array
    {
        $ids = [];

        $changes = [];

        foreach ($lineItems as $lineItem) {
            $id = $lineItem->getReferencedId();
            if ($id === '' || $id === null) {
                continue;
            }

            // data already fetched?
            if ($data->has($this->getDataKey($id))) {
                continue;
            }

            // user change line item quantity or price?
            if ($lineItem->isModified()) {
                $ids[] = $id;

                continue;
            }

            if ($lineItem->getDataTimestamp() === null) {
                $ids[] = $id;

                continue;
            }

            if ($lineItem->getDataContextHash() !== $hash) {
                $ids[] = $id;

                continue;
            }

            // check if some data is missing (label, price, cover)
            if (!$this->isComplete($lineItem)) {
                $ids[] = $id;

                continue;
            }

            $changes[$id] = $lineItem->getDataTimestamp()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        }

        if (empty($changes)) {
            return $ids;
        }

        $updates = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(id)) as id, IFNULL(updated_at, created_at) FROM product WHERE id IN (:ids) AND version_id = :liveVersionId',
            [
                'ids' => Uuid::fromHexToBytesList(array_keys($changes)),
                'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ]
        );

        foreach ($changes as $id => $timestamp) {
            // Product has been deleted, as we cannot find it
            if (!isset($updates[$id])) {
                $ids[] = $id;

                continue;
            }

            // Product has been updated, but the timestamp is older than the one we have
            if ($updates[$id] !== $changes[$id]) {
                $ids[] = $id;
            }
        }

        return array_filter(array_unique($ids));
    }

    private function isComplete(LineItem $lineItem): bool
    {
        return $lineItem->getPriceDefinition() !== null
            && $lineItem->getLabel() !== null
            && $lineItem->getDeliveryInformation() !== null
            && $lineItem->getQuantityInformation() !== null;
    }

    private function shouldPriceBeRecalculated(LineItem $lineItem, CartBehavior $behavior): bool
    {
        if ($lineItem->getPriceDefinition() !== null
            && $lineItem->hasExtension(self::CUSTOM_PRICE)
            && $behavior->hasPermission(self::ALLOW_PRODUCT_PRICE_OVERWRITES)) {
            return false;
        }

        if ($lineItem->getPriceDefinition() !== null
            && $behavior->hasPermission(self::SKIP_PRODUCT_RECALCULATION)) {
            return false;
        }

        if ($lineItem->getPriceDefinition() !== null && $lineItem->isModifiedByApp()) {
            return false;
        }

        return true;
    }

    private function getDataKey(string $id): string
    {
        return 'product-' . $id;
    }

    /**
     * @param array<LineItem> $lineItems
     */
    private function recalculate(array $lineItems, CartDataCollection $data, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $affected = [];

        foreach ($lineItems as $lineItem) {
            if (!$this->shouldPriceBeRecalculated($lineItem, $behavior)) {
                continue;
            }

            $id = $lineItem->getReferencedId();

            $product = $data->get(
                $this->getDataKey((string) $id)
            );

            // no data for enrich exists
            if (!$product instanceof SalesChannelProductEntity) {
                continue;
            }

            $affected[] = $product;
        }

        // Check if the price has to be updated
        if (empty($affected)) {
            return;
        }

        $this->priceCalculator->calculate($affected, $context);
    }

    private function getDataContextHash(SalesChannelContext $context): string
    {
        $contextHash = $this->generator->getSalesChannelContextHash($context, [RuleAreas::PRODUCT_AREA]);

        $activeTaxRules = array_map(static function (TaxEntity $taxRule) {
            return $taxRule->getRules()?->getIds() ?: $taxRule->getId();
        }, $context->getTaxRules()->getElements());

        return Hasher::hash([$contextHash, $activeTaxRules]);
    }

    private function getProductIdByProductNumber(string $productNumber): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('productNumber', $productNumber)
        );

        /** @var IdSearchResult $idSearchResult */
        $idSearchResult = $this->productRepository->searchIds($criteria, Context::createDefaultContext());

        if ($idSearchResult->getTotal() === 0) {
            return null;
        }

        return $idSearchResult->firstId();
    }
}
