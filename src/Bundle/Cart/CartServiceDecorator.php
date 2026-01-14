<?php declare(strict_types=1);

namespace PhallosanCustomizations\Bundle\Cart;

use Dvsn\Bundle\Core\Content\Bundle\BundleEntity;
use Dvsn\Bundle\Service\Bundle\BundleCollectorServiceInterface;
use Dvsn\Bundle\Service\Cart\CartService;
use Dvsn\Bundle\Service\Cart\LineItemFactoryServiceInterface;
use Dvsn\Bundle\Struct\BundleStruct;
use Shopware\Core\Checkout\Cart\Cart as CoreCart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService as CoreCartService;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartServiceDecorator extends CartService
{
    public function __construct(
        private readonly SalesChannelRepository $salesChannelProductRepository,
        private readonly LineItemFactoryServiceInterface $lineItemFactoryService,
        private readonly CoreCartService $cartService,
        private readonly BundleCollectorServiceInterface $bundleCollectorService
    ) {
        parent::__construct(
            $salesChannelProductRepository,
            $lineItemFactoryService,
            $cartService,
            $bundleCollectorService
        );
    }

    public function addToCart(string $productId, string $bundleId, string $source, array $bundleProducts, array $selection, int $quantity, CoreCart $cart, SalesChannelContext $salesChannelContext): void
    {
        $bundleStruct = $this->getBundle($productId, $bundleId, $salesChannelContext);

        if ($bundleStruct->getSelectionType() === BundleEntity::SELECTION_TYPE_FULL) {
            $selection = [];

            foreach ($bundleStruct->getProducts() as $i => $product) {
                $key = ($i === 0) ? 'parent-product' : $product->getProduct()->getId();

                if (!isset($bundleProducts[$key])) {
                    $bundleProducts[$key] = $product->getProduct()->getId();
                }

                if ($i !== 0) {
                    $selection[] = $product->getProduct()->getId();
                }
            }
        }


        //TODO start
        if (\array_key_exists('alt_parent-product', $bundleProducts)) {
            unset($selection['alt_parent-product']);

            $altParent = $bundleProducts['alt_parent-product'];
            if (\array_key_exists('parent-product', $bundleProducts)) {
                $selection[] = $bundleProducts['parent-product'];

                $parentKey = $bundleProducts['parent-product'];
                $bundleProducts[$parentKey] = $parentKey;
            }

            $bundleProducts[$altParent] = $altParent;

            $bundleProducts['parent-product'] = $altParent;
            unset($bundleProducts['alt_parent-product']);

            $bundleProducts = [];
            foreach ($bundleStruct->getProducts() as $i => $product) {
                $bundleProducts[$product->getId()] = $product->getProduct()->getId();
            }
        }
        //TODO end

        // get every product which is either parent or child
        $products = $this->getProducts(
            array_merge([$productId], array_values($bundleProducts)),
            $salesChannelContext
        );

        if ($this->validateProducts($bundleStruct, $bundleProducts, $products) === false) {
            throw new \Exception('invalid bundle products');
        }

        if ($this->validateSelection($bundleStruct, $selection, $products) === false) {
            throw new \Exception('invalid bundle selection');
        }

        // create a unique key to have multiple bundles in the cart
        // at the same time. also we need to have different product hashes
        // or we wont be able to add products from within a bundle as a
        // single product to the cart.
        $uniqueKey = md5(serialize(array_merge(['bundleId' => $bundleStruct->getId(), 'productId' => $productId], $bundleProducts)) . serialize($selection));

        // create the bundle line item
        $lineItem = $this->lineItemFactoryService->createBundle(
            $bundleStruct,
            $source,
            $uniqueKey,
            $products[$productId],
            $quantity,
            $salesChannelContext
        );

        // loop every selected product
        foreach ($bundleProducts as $bundleProductId => $bundleSwProductId) {
            // is this the parent?
            if ($bundleProductId === 'parent-product') {
                // add the parent product
                $lineItem->addChild(
                    $this->lineItemFactoryService->createProduct(
                        $bundleStruct,
                        $uniqueKey,
                        $products[$productId],
                        $quantity,
                        $salesChannelContext
                    )
                );

                // continue with next
                continue;
            }

            if ($bundleStruct->getSelectionType() === BundleEntity::SELECTION_TYPE_SELECTABLE && !\in_array($bundleProductId, $selection, true)) {
                continue;
            }

            // get quantity by bundle product id
            $bundleProductQuantity = $bundleStruct->findProduct($bundleProductId)->getQuantity();

            // add that product as child
            $lineItem->addChild(
                $this->lineItemFactoryService->createProduct(
                    $bundleStruct,
                    $uniqueKey,
                    $products[$bundleSwProductId],
                    $bundleProductQuantity * $quantity,
                    $salesChannelContext
                )
            );
        }

        // add the discount
        if ($bundleStruct->getRebate() > 0) {
            $lineItem->addChild(
                $this->lineItemFactoryService->createDiscount(
                    $bundleStruct,
                    $uniqueKey,
                    $products[$productId],
                    $salesChannelContext
                )
            );
        }

        // and add the bundle to the cart
        $cart = $this->cartService->add(
            $cart,
            $lineItem,
            $salesChannelContext
        );
    }

    private function getBundle(string $productId, string $bundleId, SalesChannelContext $salesChannelContext): BundleStruct
    {
        /** @var SalesChannelProductEntity $product */
        $product = $this->salesChannelProductRepository
            ->search((new Criteria())->addFilter(new EqualsFilter('id', $productId)), $salesChannelContext)
            ->first();

        $bundles = $this->bundleCollectorService->get(
            $product,
            $salesChannelContext
        );

        if (!isset($bundles[$bundleId])) {
            throw new \Exception('invalid bundle id');
        }

        return $bundles[$bundleId];
    }

    private function getProducts(array $ids, SalesChannelContext $salesChannelContext): array
    {
        $criteria = (new Criteria($ids))
            ->addAssociations(['cover.media', 'options.group']);

        /** @var SalesChannelProductEntity[] $products */
        $products = $this->salesChannelProductRepository
            ->search($criteria, $salesChannelContext)
            ->getElements();

        return $products;
    }

    private function validateProducts(BundleStruct $bundle, array $bundleProducts, array $products): bool
    {
        try {
            foreach ($bundleProducts as $bundleProductId => $bundleSwProductId) {
                if ($bundleProductId === 'parent-product') {
                    continue;
                }

                $bundleProduct = $bundle->findProduct($bundleProductId);
            }
        } catch (\Exception $exception) {
            return false;
        }

        return true;
    }

    private function validateSelection(BundleStruct $bundle, array $selection, array $products): bool
    {
        if ($bundle->getSelectionType() === BundleEntity::SELECTION_TYPE_FULL) {
            return true;
        }

        $productIds = [];

        /** @var SalesChannelProductEntity $product */
        foreach ($products as $product) {
            $productIds[] = $product->getId();

            if ($product->getParentId() !== null) {
                $productIds[] = $product->getParentId();
            }
        }

        $productIds = array_unique($productIds);

        if (\count(array_intersect($selection, $productIds)) === 0) {
            return false;
        }

        return true;
    }
}
