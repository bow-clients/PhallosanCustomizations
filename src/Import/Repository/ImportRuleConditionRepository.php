<?php declare(strict_types=1);

namespace PhallosanCustomizations\Import\Repository;

use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionEntity;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class ImportRuleConditionRepository
{
    public const RULE_NAME_PREFIX = 'Versandkosten';

    public function __construct(
        private readonly EntityRepository $ruleConditionRepository,
    ) {
    }

    public function getRuleIdByCountryInCondition(string $countryId): RuleEntity
    {
        $criteria = new Criteria();
        $criteria->addAssociation('rule');
        $criteria->addFilter(
            new EqualsFilter('type', 'customerShippingCountry'),
            new EqualsFilter('value.operator', '='),
            new ContainsFilter('value.countryIds', $countryId),
            new ContainsFilter('rule.name', self::RULE_NAME_PREFIX)
        );

        $result = $this->ruleConditionRepository->search($criteria, Context::createDefaultContext());
        if ($result->getTotal() > 1) {
            throw new \RuntimeException("Expected exactly one matching rule condition for country ID '$countryId', found " . $result->count() . ' in rules: ' . $this->getRuleIdsFromResult($result));
        }

        $first = $result->first();
        if (!$first instanceof RuleConditionEntity) {
            throw new \RuntimeException("Rule condition with country ID '$countryId' not found.");
        }

        $rule = $first->getRule();
        if (!$rule instanceof RuleEntity) {
            throw new \RuntimeException("Rule with country ID '$countryId' not found.");
        }

        return $rule;
    }

    private function getRuleIdsFromResult(EntitySearchResult $result): string
    {
        return implode(', ', array_map(
            fn ($rc) => $rc instanceof RuleConditionEntity && $rc->getRule() ? $rc->getRule()->getId() : 'unknown',
            $result->getElements()
        ));
    }
}
