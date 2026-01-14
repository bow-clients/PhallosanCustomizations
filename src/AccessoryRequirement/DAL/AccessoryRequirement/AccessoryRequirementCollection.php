<?php declare(strict_types=1);

namespace PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirement;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(AccessoryRequirementEntity $entity)
 * @method void set(string $key, AccessoryRequirementEntity $entity)
 * @method AccessoryRequirementEntity[] getIterator()
 * @method AccessoryRequirementEntity[] getElements()
 * @method AccessoryRequirementEntity|null get(string $key)
 * @method AccessoryRequirementEntity|null first()
 * @method AccessoryRequirementEntity|null last()
 */
class AccessoryRequirementCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AccessoryRequirementEntity::class;
    }
}
