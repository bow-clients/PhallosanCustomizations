<?php declare(strict_types=1);

namespace PhallosanCustomizations\Import\Repository;

use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class ImportShippingMethodPriceRepository
{
    public function __construct(
        private readonly EntityRepository $shippingMethodPriceRepository,
    ) {
    }

    public function getByMethodRuleAndCalculationRule(
        string $shippingMethodId,
        string $ruleId,
        string $calculationRuleId
    ): ShippingMethodPriceEntity {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('shippingMethodId', $shippingMethodId),
            new EqualsFilter('ruleId', $ruleId),
            new EqualsFilter('calculationRuleId', $calculationRuleId)
        );

        $result = $this->shippingMethodPriceRepository->search($criteria, Context::createDefaultContext());
        if ($result->getTotal() > 1) {
            throw new \RuntimeException("Expected exactly one matching shipping method price for shipping method ID '$shippingMethodId' and rule ID '$ruleId', found " . $result->count() . ' shipping method prices: ' . implode(', ', array_keys($result->getElements())));
        }

        $first = $result->first();
        if (!$first instanceof ShippingMethodPriceEntity) {
            throw new \RuntimeException("Shipping method price with shipping method ID '$shippingMethodId' and rule ID '$ruleId' not found.");
        }

        return $first;
    }
}
