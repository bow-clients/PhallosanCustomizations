<?php declare(strict_types=1);

namespace PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirement;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class AccessoryRequirementDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'accessory_requirement';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AccessoryRequirementEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AccessoryRequirementCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('name', 'name'))->addFlags(new Required(), new ApiAware()),
            (new StringField('description', 'description'))->addFlags(new ApiAware()),
            (new CustomFields())->addFlags(new ApiAware()),

            (new FkField('product_id', 'productId', ProductDefinition::ENTITY_NAME))->addFlags(new Required(), new ApiAware()),
            (new OneToOneAssociationField('product', 'product_id', 'id', ProductDefinition::class, false))->addFlags(new ApiAware()),

            (new FkField('required_product_id', 'requiredProductId', ProductDefinition::ENTITY_NAME))->addFlags(new Required(), new ApiAware()),
            (new OneToOneAssociationField('requiredProduct', 'required_product_id', 'id', ProductDefinition::class, false))->addFlags(new ApiAware()),

            (new CreatedAtField())->addFlags(new Required()),
            new UpdatedAtField(),
        ]);
    }
}
