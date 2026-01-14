<?php declare(strict_types=1);

namespace PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirement\Extensions;

use PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirement\AccessoryRequirementDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ProductExtension extends EntityExtension
{
    public const NAME = 'accessoryRequirement';

    public function getDefinitionClass(): string
    {
        return ProductDefinition::class;
    }

    public function extendFields(FieldCollection $collection): void
    {
        parent::extendFields($collection);

        $collection->add(
            (
            new OneToOneAssociationField(
                self::NAME,
                'id',
                'product_id',
                AccessoryRequirementDefinition::class,
                true
            )
            )->addFlags(new Inherited(), new ApiAware()),
        );
    }
}
