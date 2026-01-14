<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Content\OrderComment;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class OrderCommentCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OrderCommentEntity::class;
    }
}
