<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Checkout;

use PhallosanCustomizations\Core\Checkout\Struct\CustomsDutyCartExtensionStruct;
use PhallosanCustomizations\Core\Checkout\Struct\CustomsDutyLineItemStruct;
use PhallosanCustomizations\Extension\TaxRule\Aggregate\TaxRuleExtensionEntity;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tax\Aggregate\TaxRule\TaxRuleCollection;

class CustomsDutyCollector implements CartDataCollectorInterface
{
    use TaxHandlingTrait;

    final public const LINE_ITEM_EXTENSION_CUSTOM_DUTY = 'lineItemTaxRuleCustomDuties';

    final public const EXTENSION_NAME_TAX_RULE_CUSTOM_RULE = 'customTaxRule';

    public function __construct(
        private readonly EntityRepository $taxRuleRepository,
        private readonly EntityRepository $productRepository,
    ) {
    }

    /**
     * Adds a customs duty cart data extensions the cart.
     * Shopware has no ids assigned for line item tax rules,
     * so we have to add a mapping with corresponding customs duties.
     */
    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $productLineItems = $this->getLineItemsByTypes($original->getLineItems(), [
            LineItem::PRODUCT_LINE_ITEM_TYPE,
            self::LINE_ITEM_TYPE_DVSN_BUNDLE_PRODUCT,
        ]);

        if ($productLineItems->count() === 0) {
            return;
        }

        $lineItemCustomDutyMap = $this->createCustomDutiesToLineItemIdMap($productLineItems, $context);
        if (empty($lineItemCustomDutyMap)) {
            return;
        }

        $original->addExtension(
            self::LINE_ITEM_EXTENSION_CUSTOM_DUTY,
            new CustomsDutyCartExtensionStruct($lineItemCustomDutyMap)
        );
    }

    /**
     * Create a map of line item IDs to their respective customs duties.
     * @return array<string, CustomsDutyLineItemStruct[]>
     */
    private function createCustomDutiesToLineItemIdMap(
        LineItemCollection $lineItems,
        SalesChannelContext $context,
    ): array {
        $taxRuleCustomDutyMap = $this->createTaxRuleCustomDutyMap($context);
        $productEntities = $this->getLineItemProductEntities($lineItems, $context);

        /** @var array<string, CustomsDutyLineItemStruct[]> $customDutiesByLineItemId */
        $customDutiesByLineItemId = [];

        foreach ($lineItems as $lineItem) {
            $productEntity = $productEntities->get($lineItem->getReferencedId() ?? $lineItem->getId());
            if (!$productEntity instanceof ProductEntity) {
                continue;
            }

            $lineItemTaxRules = [];
            $taxRules = $productEntity->getTax()?->getRules() ?? new TaxRuleCollection();

            foreach ($taxRules as $taxRule) {
                $taxRuleId = $taxRule->getId();
                if (!isset($taxRuleCustomDutyMap[$taxRuleId])) {
                    continue;
                }

                // Shopware use the tax rate as key in line item tax rules
                $taxRate = (string) ($taxRule->getTaxRate());
                $lineItemTaxRules[$taxRate] = new CustomsDutyLineItemStruct(
                    $lineItem->getId(),
                    $taxRuleId,
                    $taxRuleCustomDutyMap[$taxRuleId]
                );
            }
            $customDutiesByLineItemId[$lineItem->getId()] = $lineItemTaxRules;
        }

        return $customDutiesByLineItemId;
    }

    /**
     * Get the TaxRuleEntities for the current SalesChannel with the CustomDutyExtension.
     */
    private function getSalesChannelTaxRuleEntities(SalesChannelContext $context): TaxRuleCollection
    {
        $taxIds = $context->getTaxRules()->getIds();
        $countryId = $context->getSalesChannel()->getCountryId();

        $criteria = new Criteria();
        $criteria->addAssociation('extension.customTaxRule');
        $criteria->addFilter(new EqualsAnyFilter('taxId', $taxIds));
        $criteria->addFilter(new EqualsFilter('countryId', $countryId));

        $entities = $this->taxRuleRepository
            ->search($criteria, $context->getContext())
            ->getEntities();

        if (!$entities instanceof TaxRuleCollection) {
            return new TaxRuleCollection();
        }

        return $entities;
    }

    /**
     * Create a map of current sales channel tax rule IDs to their respective customs duties if available.
     * @return array<string, float>
     */
    private function createTaxRuleCustomDutyMap(SalesChannelContext $context): array
    {
        $taxRuleCollection = $this->getSalesChannelTaxRuleEntities($context);

        /** @var array<string, float> $taxRuleCustomDutyMap */
        $taxRuleCustomDutyMap = [];

        foreach ($taxRuleCollection as $taxRule) {
            $taxRuleExtension = $taxRule->getExtension(self::EXTENSION_NAME_TAX_RULE_CUSTOM_RULE);
            if (!$taxRuleExtension instanceof TaxRuleExtensionEntity) {
                continue;
            }

            $applyCustomsDuty = $taxRuleExtension->getApplyCustomsDuty();

            $customsDuty = $taxRuleExtension->getCustomsDuty() ?? 0.0;
            if (!$applyCustomsDuty || $customsDuty <= 0) {
                continue;
            }

            $taxRuleCustomDutyMap[$taxRule->getId()] = $customsDuty;
        }

        return $taxRuleCustomDutyMap;
    }

    /**
     * Get the ProductEntities for the given LineItemCollection.
     */
    private function getLineItemProductEntities(
        LineItemCollection $lineItems,
        SalesChannelContext $context,
    ): ProductCollection {
        $productLineItems = $this->getLineItemsByTypes($lineItems, [
            LineItem::PRODUCT_LINE_ITEM_TYPE,
            self::LINE_ITEM_TYPE_DVSN_BUNDLE_PRODUCT,
        ]);
        $productIds = $productLineItems
            ->getReferenceIds();

        $criteria = new Criteria(array_filter($productIds));
        $criteria->addAssociation('tax');
        $criteria->addAssociation('tax.rules');

        $products = $this->productRepository
            ->search($criteria, $context->getContext())
            ->getEntities();

        if (!$products instanceof ProductCollection) {
            return new ProductCollection();
        }

        return $products;
    }
}
