<?php declare(strict_types=1);

namespace PhallosanCustomizations\Bundle\Cart;

use Dvsn\Bundle\Core\Checkout\Cart\BundleCartProcessor;
use Dvsn\Bundle\Service\Cart\LineItemFactoryServiceInterface;
use PhallosanCustomizations\PhallosanConstants;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Composition\DiscountCompositionBuilder;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Composition\DiscountCompositionItem;
use Shopware\Core\Content\Product\Cart\ProductGatewayInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class BundleCartProcessorDecorator extends BundleCartProcessor
{
    /**
     * @todo make somehow conigurable or fetch from bundle information
     */
    private const BUNDLE_PRODUCT_NUMBER = '5000';

    public function __construct(
        private readonly PercentagePriceCalculator $percentagePriceCalculator,
        private readonly QuantityPriceCalculator $quantityPriceCalculator,
        private readonly DiscountCompositionBuilder $discountCompositionBuilder,
        private readonly EntityRepository $productRepository,
        private readonly ProductGatewayInterface $productGateway,
    ) {
        parent::__construct(
            $percentagePriceCalculator,
            $quantityPriceCalculator,
            $discountCompositionBuilder
        );
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        // get every line item which is a bundle
        $lineItems = $original->getLineItems()->filterType(
            LineItemFactoryServiceInterface::BUNDLE_LINE_ITEM_TYPE
        );

        // do we even have a bundle?
        if ($lineItems->count() === 0) {
            // we dont
            return;
        }

        // loop every bundle
        /** @var LineItem $lineItem */
        foreach ($lineItems->getElements() as $lineItem) {
            // loop every child
            /** @var LineItem $child */
            foreach ($lineItem->getChildren() as $child) {
                switch ($child->getType()) {
                    case LineItemFactoryServiceInterface::BUNDLE_PRODUCT_LINE_ITEM_TYPE:
                        /** @var QuantityPriceDefinition $definition */
                        $definition = $child->getPriceDefinition();

                        $price = $this->quantityPriceCalculator->calculate(
                            $definition,
                            $context
                        );

                        $child->setPrice($price);

                        $customFields = $child->getPayload()['customFields'];

                        if (\array_key_exists('product_custom_bundle_product', $customFields) && $customFields['product_custom_bundle_product']) {
                            $lineItem->getChildren()->remove($child->getId());

                            $bundleMainProductId = $this->getBundleMainProductId($context);

                            if ($bundleMainProductId) {
                                $lineItem->setPayloadValue('dvsnBundleProductId', $bundleMainProductId);
                            }
                        }

                        break;

                    case LineItemFactoryServiceInterface::BUNDLE_DISCOUNT_LINE_ITEM_TYPE:
                        /** @var PercentagePriceDefinition $definition */
                        $definition = $child->getPriceDefinition();

                        $childPrices = $lineItem->getChildren()
                            ->filterType(LineItemFactoryServiceInterface::BUNDLE_PRODUCT_LINE_ITEM_TYPE)
                            ->getPrices();

                        /** @var CalculatedPrice $price */
                        $price = $this->percentagePriceCalculator->calculate(
                            $definition->getPercentage(),
                            $childPrices,
                            $context
                        );

                        // if we have the same bundle multiple times, the total price is calculated correctly
                        // for the complete bundle (e.g. 15% for 2x the bundle). but the unit price is the same
                        // as the total price even though we -should- have 2x unit price (for 2x bundle) and
                        // the total price should be the 2x unit price summed up. so we have to create a new
                        // calculated price with the correct quantity and unit price
                        $discountPrice = new CalculatedPrice(
                            round($price->getTotalPrice() / $lineItem->getQuantity(), $context->getCurrency()->getItemRounding()->getDecimals()),
                            $price->getTotalPrice(),
                            $price->getCalculatedTaxes(),
                            $price->getTaxRules(),
                            $lineItem->getQuantity(),
                            $price->getReferencePrice(),
                            $price->getListPrice()
                        );

                        $child->setPrice($discountPrice);

                        $composition = [];

                        foreach ($lineItem->getChildren() as $childLineItem) {
                            if ($childLineItem->getType() !== LineItemFactoryServiceInterface::BUNDLE_PRODUCT_LINE_ITEM_TYPE) {
                                continue;
                            }

                            if (!$childLineItem->getPrice() instanceof CalculatedPrice) {
                                continue;
                            }

                            $composition[] = new DiscountCompositionItem(
                                $childLineItem->getId(),
                                $childLineItem->getQuantity(),
                                abs($childLineItem->getPrice()->getTotalPrice() / 100 * $definition->getPercentage())
                            );
                        }

                        $child->setPayloadValue(
                            'composition',
                            $this->discountCompositionBuilder->buildCompositionPayload(
                                $composition
                            )
                        );

                        break;

                    default:
                        // the order may be re-calculated when we changed something but every component
                        // is defined as product. this check will fail and we cant re-construct what happened
                        // before. this will break everything... but we shouldnt throw an exception because
                        // then we couldnt change anything within the order.
                        continue 2;
                }
            }

            // sum everything up (incl. taxes)
            $priceCollection = new PriceCollection(
                $this->getPrices(
                    $lineItem
                )
            );

            // get the summed up prices
            $priceSum = $priceCollection->sum();

            // create a new price with fixed unit price.
            // the unit price is calculated wrong when we have child items with multiple
            // quantity. their unit price (1x) and total price is correct - but if we sum
            // up their unit prices, we will have a faulty unit price for our bundle.
            $newPrice = new CalculatedPrice(
                round($priceSum->getTotalPrice() / $lineItem->getQuantity(), $context->getCurrency()->getItemRounding()->getDecimals()),
                $priceSum->getTotalPrice(),
                $priceSum->getCalculatedTaxes(),
                $priceSum->getTaxRules(),
                $lineItem->getQuantity(),
                $priceSum->getReferencePrice(),
                $priceSum->getListPrice()
            );

            // set this one
            $lineItem->setPrice(
                $newPrice
            );

            // now add the bundle to the cart
            $toCalculate->add($lineItem);
        }
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

    private function getProductByProductId(string $productId): ?ProductEntity
    {
        $criteria = new Criteria([$productId]);

        /** @var EntitySearchResult $searchResult */
        $searchResult = $this->productRepository->search($criteria, Context::createDefaultContext());

        if ($searchResult->getTotal() === 0) {
            return null;
        }

        /** @var ProductEntity $productEntity */
        $productEntity = $searchResult->first();

        return $productEntity;
    }

    private function getBundleMainProductId(SalesChannelContext $salesChannelContext): ?string
    {
        $productId = $this->getProductIdByProductNumber(self::BUNDLE_PRODUCT_NUMBER);

        if (!$productId) {
            return null;
        }

        /**
         * check if the product exists and is active in the saleschannel
         */
        /** @var SalesChannelProductCollection $products */
        $products = $this->productGateway->get([$productId], $salesChannelContext);

        if ($products->count() !== 0) {
            return $products->first()?->getId();
        }

        /**
         * product does not exists, check for alternatives
         */
        $productEntity = $this->getProductByProductId($productId);

        if (!$productEntity) {
            return null;
        }

        /**
         * check for customfield alternatives
         */
        $productCustomFields = $productEntity->getCustomFields();
        $alternativeProductId = $productCustomFields[PhallosanConstants::CUSTOM_FIELD_PRODUCT_ALTERNATIVE_PRODUCT] ?? null;

        $productNumber = $alternativeProductNumber = $productEntity->getProductNumber();
        if (!$alternativeProductId) {
            /**
             * Check for alternatives with/without "-US"
             */
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
            return null;
        }

        $alternativeProduct = $this->productGateway->get([$alternativeProductId], $salesChannelContext)->first();

        if (!$alternativeProduct) {
            return null;
        }

        return $alternativeProductId;
    }

    private function getPrices(LineItem $lineItem): array
    {
        // every price for every child
        $prices = [];

        // loop the children
        foreach ($lineItem->getChildren() as $childLineItem) {
            // we need a valid price
            if (!$childLineItem->getPrice() instanceof CalculatedPrice) {
                // ignore it
                continue;
            }

            // add this price
            $prices[] = $childLineItem->getPrice();
        }

        // return them
        return $prices;
    }
}
