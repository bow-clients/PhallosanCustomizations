<?php declare(strict_types=1);

namespace PhallosanCustomizations\Extension\TaxRule\Aggregate;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class TaxRuleExtensionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return TaxRuleExtensionEntity::class;
    }
}
