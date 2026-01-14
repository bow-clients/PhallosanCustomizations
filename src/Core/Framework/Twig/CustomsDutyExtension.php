<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Framework\Twig;

use PhallosanCustomizations\Core\Checkout\CustomsDutyCollector;
use PhallosanCustomizations\Core\Checkout\CustomsDutyProcessor;
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

class CustomsDutyExtension extends AbstractExtension
{
    use TaxRuleTrait;

    public function __construct(
        protected readonly EntityRepository $taxRuleRepository
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('getCartDutyTotal', [$this, 'getCartDutyTotal']),
            new TwigFunction('getProductDutyRate', [$this, 'getProductDutyRate']),
        ];
    }

    /**
     * Calculates the total customs duty for a given Cart or OrderEntity.
     */
    public function getCartDutyTotal(mixed $data): float
    {
        if ($data instanceof Cart) {
            $dutyItems = $data->getLineItems()->filterType(CustomsDutyProcessor::CUSTOMS_DUTY_LINE_ITEM_TYPE);

            return $this->sumPrices($dutyItems);
        }

        if ($data instanceof OrderEntity) {
            $dutyItems = $data->getLineItems()?->filterByType(CustomsDutyProcessor::CUSTOMS_DUTY_LINE_ITEM_TYPE);

            return $this->sumPrices($dutyItems ?? new OrderLineItemCollection());
        }

        throw new \InvalidArgumentException('No support for type: ' . \get_class($data));
    }

    /**
     * Calculates the customs duty rate for a given product based on highest duty rate from its tax rules.
     */
    public function getProductDutyRate(SalesChannelProductEntity $product, SalesChannelContext $context): float
    {
        $dutyRate = 0.0;

        $taxRules = $product->getTax()?->getRules() ?? null;

        if (!$taxRules) {
            $countryId = $context->getShippingLocation()->getCountry()->getId();
            $taxRules = $this->getTaxRules((string)$product->getTaxId(), (string)$countryId, $context->getContext());
        }

        if (\count($taxRules) === 0) {
            return $dutyRate;
        }

        foreach ($taxRules as $taxRule) {
            $taxRuleExtension = $taxRule->getExtension(CustomsDutyCollector::EXTENSION_NAME_TAX_RULE_CUSTOM_RULE);
            if (!$taxRuleExtension instanceof TaxRuleExtensionEntity) {
                continue;
            }
            $dutyRate = max($dutyRate, $taxRuleExtension->getCustomsDuty() ?? 0.0);
        }

        return $dutyRate;
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
