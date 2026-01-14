<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Framework\Twig\Trait;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

trait TaxRuleTrait
{
    protected readonly EntityRepository $taxRuleRepository;

    protected function getTaxRules(string $taxId, string $countryId, Context $context): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('taxId', $taxId),
            new EqualsFilter('countryId', $countryId)
        );

        return $this->taxRuleRepository->search($criteria, $context);
    }
}
