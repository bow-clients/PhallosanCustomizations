<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Framework\Twig;

use PhallosanCustomizations\Core\Checkout\ImportVatCollector;
use PhallosanCustomizations\Core\Checkout\ImportVatProcessor;
use PhallosanCustomizations\Core\Framework\Twig\Trait\TaxRuleTrait;
use PhallosanCustomizations\Extension\TaxRule\Aggregate\TaxRuleExtensionEntity;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ImportVatExtension extends AbstractExtension
{
    use TaxRuleTrait;

    public function __construct(
        protected readonly EntityRepository $taxRuleRepository
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('getImportVatTotal', [$this, 'getImportVatTotal']),
            new TwigFunction('getProductImportVatRate', [$this, 'getProductImportVatRate']),
        ];
    }

    /**
     * Calculates the total import vat for a given Cart or OrderEntity.
     */
    public function getImportVatTotal(mixed $data): float
    {
        if ($data instanceof Cart) {
            $importVatItems = $data->getLineItems()->filterType(ImportVatProcessor::IMPORT_VAT_LINE_ITEM_TYPE);

            return $this->sumPrices($importVatItems);
        }

        if ($data instanceof OrderEntity) {
            $importVatItems = $data->getLineItems()?->filterByType(ImportVatProcessor::IMPORT_VAT_LINE_ITEM_TYPE);

            return $this->sumPrices($importVatItems ?? new OrderLineItemCollection());
        }

        throw new \InvalidArgumentException('No support for type: ' . \get_class($data));
    }

    /**
     * Calculates the import vat rate for a given product based on highest import vat rate from its tax rules.
     */
    public function getProductImportVatRate(SalesChannelProductEntity $product, SalesChannelContext $context): float
    {
        $importVatRate = 0.0;

        $taxRules = $product->getTax()?->getRules() ?? null;

        if (!$taxRules) {
            $countryId = $context->getShippingLocation()->getCountry()->getId();
            $taxRules = $this->getTaxRules((string)$product->getTaxId(), (string)$countryId, $context->getContext());
        }

        if (\count($taxRules) === 0) {
            return $importVatRate;
        }

        foreach ($taxRules as $taxRule) {
            $taxRuleExtension = $taxRule->getExtension(ImportVatCollector::EXTENSION_NAME_TAX_RULE_CUSTOM_RULE);
            if (!$taxRuleExtension instanceof TaxRuleExtensionEntity) {
                continue;
            }
            $importVatRate = max($importVatRate, $taxRuleExtension->getImportVat() ?? 0.0);
        }

        return $importVatRate;
    }

    /**
     * @param iterable<LineItem|OrderLineItemEntity> $lineItems
     */
    private function sumPrices(iterable $lineItems): float
    {
        $total = 0.0;

        foreach ($lineItems as $lineItem) {
            $price = $lineItem->getPrice();
            if ($price instanceof CalculatedPrice) {
                $total += $price->getTotalPrice();
            }
        }

        return $total;
    }
}
