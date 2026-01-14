<?php declare(strict_types=1);

namespace PhallosanCustomizations\Extension\TaxRule\Aggregate;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Tax\Aggregate\TaxRule\TaxRuleDefinition;

class TaxRuleExtensionDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'custom_tax_rule';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return TaxRuleExtensionEntity::class;
    }

    public function getCollectionClass(): string
    {
        return TaxRuleExtensionCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
            (new FkField('tax_rule_id', 'taxRuleId', TaxRuleDefinition::class)),
            new FloatField('customs_duty', 'customsDuty'),
            new BoolField('apply_customs_duty', 'applyCustomsDuty'),
            new FloatField('import_vat', 'importVat'),
            new BoolField('apply_import_vat', 'applyImportVat'),
            new ManyToOneAssociationField('taxRule', 'tax_rule_id', TaxRuleDefinition::class, 'id', false),
        ]);
    }
}
