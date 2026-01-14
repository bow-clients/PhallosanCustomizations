<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Content\OrderComment;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OrderCommentDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'order_comment';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return OrderCommentEntity::class;
    }

    public function getCollectionClass(): string
    {
        return OrderCommentCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(OrderDefinition::class, 'order_version_id'))->addFlags(new Required()),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id'),

            (new LongTextField('comment', 'comment'))->addFlags(new Required()),
            (new BoolField('is_public', 'isPublic')),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
