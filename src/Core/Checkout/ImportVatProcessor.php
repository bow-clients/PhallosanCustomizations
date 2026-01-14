<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Checkout;

use PhallosanCustomizations\Core\Checkout\Struct\ImportVatCartExtensionStruct;
use PhallosanCustomizations\Core\Checkout\Struct\ImportVatLineItemStruct;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Translation\TranslatorInterface;

class ImportVatProcessor implements CartProcessorInterface
{
    use TaxHandlingTrait;

    final public const IMPORT_VAT_LINE_ITEM_TYPE = 'import-vat';

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Processes the cart to calculate import vat and add specific line items.
     */
    public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        $productLineItems = $this->getLineItemsByTypes($toCalculate->getLineItems(), [
            LineItem::PRODUCT_LINE_ITEM_TYPE,
            self::LINE_ITEM_TYPE_DVSN_BUNDLE_PRODUCT,
        ]);

        if ($productLineItems->count() === 0) {
            return;
        }

        $cartExtensionImportVat = $toCalculate->getExtension(ImportVatCollector::LINE_ITEM_EXTENSION_IMPORT_VAT);
        if (!$cartExtensionImportVat instanceof ImportVatCartExtensionStruct) {
            return;
        }

        $importVat = $this->calculateImportVat($productLineItems, $cartExtensionImportVat, $data);
        /** @var float $importVatTotal */
        $importVatTotal = (float)array_sum(array_column($importVat, 'totalImportTax'));
        if (empty($importVat) || $importVatTotal <= 0.0) {
            return;
        }

        $languageId = $context->getLanguageId();

        $this->applyImportVatLineItemToCart($original, $toCalculate, $importVat, $languageId);
    }

    /**
     * Calculates the import vats for each line item based on its price and tax rules.
     *
     */
    private function calculateImportVat(
        LineItemCollection $lineItems,
        ImportVatCartExtensionStruct $cartExtensionImportVat,
        CartDataCollection $data
    ): array {
        $importVats = [];

        $customDutyByLineItems = $data->get(CustomsDutyProcessor::DATA_CUSTOM_DUTY_BY_LINEITEM);

        foreach ($lineItems as $lineItem) {
            $productTotal = $lineItem->getPrice()?->getTotalPrice() ?? 0.0;
            $productImportVats = $cartExtensionImportVat->getLineItemImportVats($lineItem->getId());

            $lineItemCustomDuties = $customDutyByLineItems[$lineItem->getId()] ?? [];

            $appliedCustomDuty = 0;
            if (\count($lineItemCustomDuties) !== 0) {
                $appliedCustomDuty = array_sum(array_column($lineItemCustomDuties, 'totalCustomDuty'));
            }

            $lineItemImportVats = [];

            foreach ($productImportVats as $partialTaxRuleImportVat) {
                $importVatRate = $partialTaxRuleImportVat->getImportVat();

                if (!isset($lineItemImportVats[$importVatRate])) {
                    $lineItemImportVats[$importVatRate] = [
                        'importTax' => [],
                        'totalImportTax' => 0.0,
                    ];
                }

                /**
                 * @note calculate "(productprice + customDuty) * importVat"
                 */
                $productImportTax = ($productTotal + $appliedCustomDuty) * ($importVatRate / 100);

                $partialTaxRuleImportVat->addExtension('calculatedImportTax', new ArrayStruct([
                    'productTotal' => $productTotal,
                    'customDuty' => $appliedCustomDuty,
                    'based_on_total' => ($productTotal + $appliedCustomDuty),
                    'importTax' => $productImportTax,
                ]));

                $lineItemImportVats[$importVatRate]['importTax'][] = $partialTaxRuleImportVat;
                $lineItemImportVats[$importVatRate]['totalImportTax'] += $productImportTax;
            }

            $importVats = $this->mergeTaxGroups($lineItemImportVats, $importVats, 'importTax', 'totalImportTax');
        }

        return $importVats;
    }

    /**
     * Applies the calculated import vats to the cart, reusing existing import vat line items if they match the total import vat amount.
     */
    private function applyImportVatLineItemToCart(
        Cart $original,
        Cart $toCalculate,
        array $importVats,
        string $languageId,
    ): void {
        $importVatLineItems = $original->getLineItems()->filterType(self::IMPORT_VAT_LINE_ITEM_TYPE);

        $importVatLineItemPrice = 0.0;
        $importVatLineItemLanguages = [];
        foreach ($importVatLineItems as $importVatLineItem) {
            $importVatLineItemPrice += $importVatLineItem->getPrice()?->getTotalPrice() ?? 0.0;
            $importVatLineItemLanguages[] = $importVatLineItem->getPayloadValue('labelLanguageId') ?? null;
        }

        $importVatLineItemLanguages = array_filter($importVatLineItemLanguages);

        $recalculateImportVatLineitems = $importVatLineItems->count() !== \count($importVats)
            || (string)$importVatLineItemPrice !== (string)array_sum(array_column($importVats, 'totalImportTax'))
            || \count($importVatLineItemLanguages) !== 1
            || !\in_array($languageId, $importVatLineItemLanguages, true);

        if (!$recalculateImportVatLineitems) {
            foreach ($importVatLineItems as $importVatLineItem) {
                $toCalculate->add($importVatLineItem);
            }

            return;
        }

        $this->removeLineItemByType(self::IMPORT_VAT_LINE_ITEM_TYPE, $toCalculate);
        foreach ($importVats as $taxRate => $importVatEntry) {
            $importVatAmount = $importVatEntry['totalImportTax'];

            /** @var ImportVatLineItemStruct|null $firstImportVat */
            $firstImportVat = $importVatEntry['importTax'][0] ?? null;

            if ($firstImportVat) {
                $taxRate = $firstImportVat->getImportVat();
            }

            $labelImportVat = $this->translator->trans('checkout.importVat.label');

            $newImportVatLineItem = $this->createLineItem(
                lineItemType: self::IMPORT_VAT_LINE_ITEM_TYPE,
                label: $labelImportVat,
                amount: (float)$importVatAmount,
                taxRate: (float)$taxRate,
                languageId: $languageId
            );
            $toCalculate->add($newImportVatLineItem);
        }
    }
}
