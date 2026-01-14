<?php declare(strict_types=1);

namespace PhallosanCustomizations\Extension\TaxRule;

use PhallosanCustomizations\Extension\TaxRule\Aggregate\TaxRuleExtensionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Tax\Aggregate\TaxRule\TaxRuleDefinition;

class TaxRuleExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToOneAssociationField(
                'customTaxRule',
                'id',
                'tax_rule_id',
                TaxRuleExtensionDefinition::class,
            ))->addFlags(new CascadeDelete())
        );
    }

    public function getDefinitionClass(): string
    {
        return TaxRuleDefinition::class;
    }
}
