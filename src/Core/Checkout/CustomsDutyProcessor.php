<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Checkout;

use PhallosanCustomizations\Core\Checkout\Struct\CustomsDutyCartExtensionStruct;
use PhallosanCustomizations\Core\Checkout\Struct\CustomsDutyLineItemStruct;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Translation\TranslatorInterface;

class CustomsDutyProcessor implements CartProcessorInterface
{
    use TaxHandlingTrait;

    final public const CUSTOMS_DUTY_LINE_ITEM_TYPE = 'customs-duty';
    final public const CUSTOMS_DUTY_LINE_ITEM_EXTENSION = 'customDuty';

    final public const DATA_CUSTOM_DUTY_BY_LINEITEM = 'custom-duties-by-lineitem';

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Processes the cart to calculate duty and add customs duty line items.
     */
    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $productLineItems = $this->getLineItemsByTypes($toCalculate->getLineItems(), [
            LineItem::PRODUCT_LINE_ITEM_TYPE,
            self::LINE_ITEM_TYPE_DVSN_BUNDLE_PRODUCT,
        ]);

        if ($productLineItems->count() === 0) {
            return;
        }

        $cartExtensionCustomsDuty = $toCalculate->getExtension(CustomsDutyCollector::LINE_ITEM_EXTENSION_CUSTOM_DUTY);
        if (!$cartExtensionCustomsDuty instanceof CustomsDutyCartExtensionStruct) {
            return;
        }

        $duties = $this->calculateDuties($productLineItems, $cartExtensionCustomsDuty, $data);
        /** @var float $dutiesTotal */
        $dutiesTotal = (float)array_sum(array_column($duties, 'totalCustomDuty'));
        if (empty($duties) || $dutiesTotal <= 0.0) {
            return;
        }

        $languageId = $context->getLanguageId();

        $this->applyDutiesToCart($original, $toCalculate, $duties, $languageId);
    }

    /**
     * Calculates the customs duty for each line item based on its price and tax rules.
     *
     */
    private function calculateDuties(
        LineItemCollection $lineItems,
        CustomsDutyCartExtensionStruct $customDutyCartExtension,
        CartDataCollection $data
    ): array {
        $allDuties = $dutiesByLineItem = [];

        foreach ($lineItems as $lineItem) {
            $lineItemDuties = [];

            $productTotal = $lineItem->getPrice()?->getTotalPrice() ?? 0.0;
            $productCustomDuties = $customDutyCartExtension->getLineItemCustomDuty($lineItem->getId());

            /** @var CustomsDutyLineItemStruct $partialTaxRuleCustomDuty */
            foreach ($productCustomDuties as $partialTaxRuleCustomDuty) {
                $customDutyRate = $partialTaxRuleCustomDuty->getCustomsDuty();
                if (!$customDutyRate) {
                    continue;
                }

                if (!isset($lineItemDuties[$customDutyRate])) {
                    $lineItemDuties[$customDutyRate] = [
                        'customDuty' => [],
                        'totalCustomDuty' => 0.0,
                    ];
                }

                $productCustomDuty = $productTotal * ($customDutyRate / 100);

                $partialTaxRuleCustomDuty->addExtension('calculatedCustomDuty', new ArrayStruct([
                    'total' => $productCustomDuty,
                    'lineItemId' => $lineItem->getId(),
                ]));

                $partialTaxRuleCustomDuty->setCustomsDutyTotal($productCustomDuty);

                $lineItemDuties[$customDutyRate]['customDuty'][] = $partialTaxRuleCustomDuty;
                $lineItemDuties[$customDutyRate]['totalCustomDuty'] += $productCustomDuty;
            }

            $totalLineItemCustomDuty = array_sum(array_column($lineItemDuties, 'totalCustomDuty'));

            $dutiesByLineItem[$lineItem->getId()] = $lineItemDuties;

            $lineItem->addExtension(self::CUSTOMS_DUTY_LINE_ITEM_EXTENSION, new ArrayStruct([
                'totalCustomDuty' => $totalLineItemCustomDuty,
                'appliedDuties' => $lineItemDuties,
            ]));

            $allDuties = $this->mergeTaxGroups($lineItemDuties, $allDuties, 'customDuty', 'totalCustomDuty');
        }

        $data->set(self::DATA_CUSTOM_DUTY_BY_LINEITEM, $dutiesByLineItem);

        return $allDuties;
    }

    /**
     * Applies the calculated customs duty to the cart, reusing existing duty line items if they match the total duty amount.
     */
    private function applyDutiesToCart(
        Cart  $original,
        Cart  $toCalculate,
        array $duties,
        string $languageId
    ): void {
        $dutyLineItems = new LineItemCollection($original->getLineItems()->filterType(self::CUSTOMS_DUTY_LINE_ITEM_TYPE));

        $dutyLineItemPrice = 0.0;
        $customDutyLineItemLanguages = [];
        foreach ($dutyLineItems as $dutyLineItem) {
            $dutyLineItemPrice += $dutyLineItem->getPrice()?->getTotalPrice() ?? 0.0;
            $customDutyLineItemLanguages[] = $dutyLineItem->getPayloadValue('labelLanguageId');
        }

        $customDutyLineItemLanguages = array_filter($customDutyLineItemLanguages);

        $recalculateCustomDutyLineitems = $dutyLineItems->count() !== \count($duties)
            || (string)$dutyLineItemPrice !== (string)array_sum(array_column($duties, 'totalCustomDuty'))
            || \count($customDutyLineItemLanguages) !== 1
            || !\in_array($languageId, $customDutyLineItemLanguages, true);

        if (!$recalculateCustomDutyLineitems) {
            foreach ($dutyLineItems as $dutyLineItem) {
                $toCalculate->add($dutyLineItem);
            }

            return;
        }

        $this->removeLineItemByType(self::CUSTOMS_DUTY_LINE_ITEM_TYPE, $toCalculate);
        foreach ($duties as $taxRate => $dutyEntry) {
            $dutyAmount = $dutyEntry['totalCustomDuty'];

            /**
             * @note currently we're assuming that there can only be one duty taxrate
             */
            /** @var CustomsDutyLineItemStruct|null $firstDuty */
            $firstDuty = $dutyEntry['customDuty'][0] ?? null;

            if ($firstDuty) {
                $taxRate = $firstDuty->getCustomsDuty();
            }

            $labelDuty = $this->translator->trans('checkout.customsDuty.label');

            $newDutyLineItem = $this->createLineItem(
                lineItemType: self::CUSTOMS_DUTY_LINE_ITEM_TYPE,
                label: $labelDuty,
                amount: (float)$dutyAmount,
                taxRate: (float)$taxRate,
                languageId: $languageId
            );
            $toCalculate->add($newDutyLineItem);
        }
    }
}
