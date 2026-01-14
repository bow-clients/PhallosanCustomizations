<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Checkout;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Uuid\Uuid;

trait TaxHandlingTrait
{
    public const LINE_ITEM_TYPE_DVSN_BUNDLE_PRODUCT = 'dvsn-bundle-product';

    protected function getLineItemsByTypes(
        LineItemCollection $lineItems,
        array $filterTypes
    ): LineItemCollection {
        $flatItems = $lineItems->getFlat();

        $newList = [];
        /** @var LineItem $flatItem */
        foreach ($flatItems as $flatItem) {
            if (!\in_array($flatItem->getType(), $filterTypes, true)) {
                continue;
            }

            $newList[] = $flatItem;
        }

        return new LineItemCollection($newList);
    }

    protected function mergeTaxGroups(
        array $mergeFrom,
        array $mergeInto,
        string $listKey,
        string $totalKey
    ): array {
        $newList = $mergeInto;

        foreach ($mergeFrom as $mergeKey => $mergeData) {
            if (!isset($newList[$mergeKey])) {
                $newList[$mergeKey] = $mergeData;

                continue;
            }

            $newList[$mergeKey][$listKey] = array_merge($newList[$mergeKey][$listKey], $mergeData[$listKey]);
            $newList[$mergeKey][$totalKey] += $mergeData[$totalKey];
        }

        return $newList;
    }

    protected function createLineItem(
        string $lineItemType,
        string $label,
        float $amount,
        float $taxRate,
        string $languageId
    ): LineItem {
        $lineItem = new LineItem(Uuid::randomHex(), $lineItemType);

        $lineItem->setGood(false);
        $lineItem->setStackable(false);
        $lineItem->setRemovable(false);
        $lineItem->setShippingCostAware(false);

        $lineItem->setLabel($label . ' (' . $taxRate . '%)');

        $lineItem->setPayloadValue('labelLanguageId', $languageId);

        // set tax to 0 for the line item itself
        $taxRules = new TaxRuleCollection();
        $taxRules->add(new TaxRule(0, 100));

        $calculatedTax = new CalculatedTaxCollection();
        $calculatedTax->add(new CalculatedTax(0, 0, $amount));

        $price = new CalculatedPrice(
            $amount,
            $amount,
            $calculatedTax,
            new TaxRuleCollection($taxRules)
        );
        $lineItem->setPrice($price);

        return $lineItem;
    }

    protected function removeLineItemByType(
        string $lineItemType,
        Cart $cart
    ): void {
        $lineItems = $cart->getLineItems();

        $lineItemsToRemove = $lineItems->filterType($lineItemType);
        if ($lineItemsToRemove->count() === 0) {
            return;
        }

        foreach ($lineItemsToRemove as $lineItemToRemove) {
            $lineItems->remove($lineItemToRemove->getId());
        }

        $cart->setLineItems($lineItems);
    }
}
