<?php declare(strict_types=1);

namespace PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirementOrder;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(AccessoryRequirementOrderEntity $entity)
 * @method void set(string $key, AccessoryRequirementOrderEntity $entity)
 * @method AccessoryRequirementOrderEntity[] getIterator()
 * @method AccessoryRequirementOrderEntity[] getElements()
 * @method AccessoryRequirementOrderEntity|null get(string $key)
 * @method AccessoryRequirementOrderEntity|null first()
 * @method AccessoryRequirementOrderEntity|null last()
 */
class AccessoryRequirementOrderCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AccessoryRequirementOrderEntity::class;
    }
}
