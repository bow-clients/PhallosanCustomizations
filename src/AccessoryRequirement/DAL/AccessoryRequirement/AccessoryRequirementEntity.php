<?php declare(strict_types=1);

namespace PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirement;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class AccessoryRequirementEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    protected ?string $name;

    protected ?string $description;

    protected ?string $productId = null;

    protected ?ProductEntity $product = null;

    protected ?string $requiredProductId = null;

    protected ?ProductEntity $requiredProduct = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function setProductId(?string $productId): void
    {
        $this->productId = $productId;
    }

    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function setProduct(?ProductEntity $product): void
    {
        $this->product = $product;
    }

    public function getRequiredProductId(): ?string
    {
        return $this->requiredProductId;
    }

    public function setRequiredProductId(?string $requiredProductId): void
    {
        $this->requiredProductId = $requiredProductId;
    }

    public function getRequiredProduct(): ?ProductEntity
    {
        return $this->requiredProduct;
    }

    public function setRequiredProduct(?ProductEntity $requiredProduct): void
    {
        $this->requiredProduct = $requiredProduct;
    }
}
