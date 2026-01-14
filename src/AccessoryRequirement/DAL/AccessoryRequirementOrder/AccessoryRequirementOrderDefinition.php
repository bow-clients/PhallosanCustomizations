<?php declare(strict_types=1);

namespace PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirementOrder;

use PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirement\AccessoryRequirementDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class AccessoryRequirementOrderDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'accessory_requirement_order';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AccessoryRequirementOrderEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AccessoryRequirementOrderCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new FkField('accessory_requirement_id', 'accessoryRequirementId', AccessoryRequirementDefinition::class))->addFlags(new Required(), new ApiAware()),

            (new FkField('order_id', 'orderId', OrderDefinition::ENTITY_NAME)),
            (new StringField('order_number', 'orderNumber'))->addFlags(new Required(), new ApiAware()),

            (new FkField('redeemed_order_id', 'redeemedOrderId', OrderDefinition::ENTITY_NAME)),
            (new StringField('redeemed_order_number', 'redeemedOrderNumber'))->addFlags(new ApiAware()),

            (new StringField('comment', 'comment'))->addFlags(new ApiAware()),
            (new BoolField('migrated', 'migrated'))->addFlags(new ApiAware()),

            (new CustomFields()),

            (new ManyToOneAssociationField('accessoryRequirement', 'accessory_requirement_id', AccessoryRequirementDefinition::ENTITY_NAME, 'id', true))->addFlags(
                new ApiAware(),
            ),
            (new OneToOneAssociationField('order', 'order_id', 'id', OrderDefinition::ENTITY_NAME, false))->addFlags(
                new ApiAware(),
            ),
            (new OneToOneAssociationField('redeemedOrder', 'redeemed_order_id', 'id', OrderDefinition::ENTITY_NAME, false))->addFlags(
                new ApiAware(),
            ),

            (new DateTimeField('migrated_at', 'migratedAt'))->addFlags(new ApiAware()),
            (new DateTimeField('redeemed_at', 'redeemedAt'))->addFlags(new ApiAware()),
            (new CreatedAtField())->addFlags(new Required()),
            new UpdatedAtField(),
        ]);
    }
}
