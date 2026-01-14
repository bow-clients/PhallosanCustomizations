<?php declare(strict_types=1);

namespace PhallosanCustomizations\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Storefront\Page\Product\ProductPageCriteriaEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomsDutyProductCriteriaSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageCriteriaEvent::class => 'onProductPageCriteriaEvent',
        ];
    }

    /**
     * Extends the product page criteria to include tax rules and their extensions.
     */
    public function onProductPageCriteriaEvent(ProductPageCriteriaEvent $event): void
    {
        $event->getCriteria()->addAssociation('tax.rules');
        $event->getCriteria()->addAssociation('tax.rules.extensions');

        $salesChannelTaxRuleIds = [];
        foreach ($event->getSalesChannelContext()->getTaxRules() as $tax) {
            $taxRuleIds = $tax->getRules()?->getIds() ?? [];
            $salesChannelTaxRuleIds[] = $taxRuleIds;
        }
        $salesChannelTaxRuleIds = array_merge([], ...$salesChannelTaxRuleIds);

        $taxRulesAssociation = $event->getCriteria()->getAssociation('tax.rules');
        $taxRulesAssociation->addFilter(new EqualsAnyFilter('id', $salesChannelTaxRuleIds));
    }
}
