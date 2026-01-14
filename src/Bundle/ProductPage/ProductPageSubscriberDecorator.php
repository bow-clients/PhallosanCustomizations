<?php declare(strict_types=1);

namespace PhallosanCustomizations\Bundle\ProductPage;

use Dvsn\Bundle\Core\Content\Bundle\BundleEntity;
use Dvsn\Bundle\Service\Bundle\BundleCollectorServiceInterface;
use Dvsn\Bundle\Struct\BundleStruct;
use Dvsn\Bundle\Subscriber\Storefront\Page\Product\ProductPageSubscriber;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;

class ProductPageSubscriberDecorator extends ProductPageSubscriber
{
    public function __construct(
        private readonly BundleCollectorServiceInterface $bundleCollectorService,
        private readonly SystemConfigService $systemConfigService
    ) {
        parent::__construct(
            $bundleCollectorService,
            $systemConfigService
        );
    }

    public function onPageLoaded(ProductPageLoadedEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();
        $product = $event->getPage()->getProduct();

        $config = $this->systemConfigService->get('DvsnBundle.config', $salesChannelContext->getSalesChannel()->getId());

        /** @phpstan-ignore-next-line */
        if ((bool) $config['status'] === false) {
            return;
        }

        if ($product->getAvailable() === false) {
            return;
        }

        if ((int) $product->getChildCount() > 0) {
            return;
        }

        $bundles = $this->bundleCollectorService->get(
            $product,
            $salesChannelContext
        );

        $key = array_key_first($bundles);
        if ($key === null || !isset($bundles[$key])) {
            return;
        }
        /**
         * @note use array_values to reset the index
         */
        $tempProducts = array_values($bundles[$key]->getProducts());

        //TODO start
        $isBundleProduct = false;
        foreach ($tempProducts as $product) {
            $assignedProduct = $product->getProduct();
            $customFields = $assignedProduct->getCustomFields();
            if ($customFields && \array_key_exists('product_custom_bundle_product', $customFields)) {
                if ($customFields['product_custom_bundle_product'] === true) {
                    $isBundleProduct = true;
                }
            }
        }

        if ($isBundleProduct) {
            foreach ($tempProducts as $tempProductsKey => $product) {
                $assignedProduct = $product->getProduct();
                $customFields = $assignedProduct->getCustomFields();
                if ($customFields && \array_key_exists('product_custom_bundle_product', $customFields)) {
                    if ($customFields['product_custom_bundle_product'] === true) {
                        $product->setId('alt_parent-product');
                    }
                }
                /**
                 * @note mark the second product (the first is the hidden dummy base product) as official parent product (can't be removed from bundle)
                 */
                $arrayIndex = array_search($product, $tempProducts, true);
                if ($arrayIndex === 1) {
                    $tempProducts[$tempProductsKey]->setId('parent-product');
                }
            }
            $bundles[$key]->setProducts($tempProducts);
        }
        //TODO end

        if (isset($config['productDetailMergeMultipleFatherProducts']) && $config['productDetailMergeMultipleFatherProducts'] === true) {
            foreach ($bundles as $bundle) {
                if ($bundle->getSelectionType() !== BundleEntity::SELECTION_TYPE_FULL) {
                    continue;
                }

                $products = $bundle->getProducts();
                $parentId = $products[0]->getProduct()->getId();

                foreach ($products as $i => $product) {
                    if ($product->getId() === 'parent-product') {
                        continue;
                    }

                    if ($product->getProduct()->getId() === $parentId) {
                        $products[0]->setQuantity($products[0]->getQuantity() + $product->getQuantity());
                        unset($products[$i]);
                    }
                }

                $bundle->setProducts(array_values($products));
            }
        }

        $bundlesByDisplayType = [
            'display' => [
                BundleStruct::DISPLAY_TYPE_CONTENT => [],
                BundleStruct::DISPLAY_TYPE_BUY_WIDGET => [],
            ],
            'total' => 0,
        ];

        foreach ($bundles as $bundle) {
            $bundlesByDisplayType['display'][$bundle->getDisplayType()][] = $bundle;
            $bundlesByDisplayType['total']++;
        }

        $event->getPage()->assign([
            'dvsnBundle' => $bundlesByDisplayType,
            'dvsnBundleConfiguration' => $config,
        ]);
    }
}
