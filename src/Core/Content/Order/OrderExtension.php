<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Content\Order;

use PhallosanCustomizations\Core\Content\OrderComment\OrderCommentDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OrderExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToManyAssociationField(
                'orderComments',
                OrderCommentDefinition::class,
                'order_id'
            )
        );
    }

    public function getDefinitionClass(): string
    {
        return OrderDefinition::class;
    }
}
