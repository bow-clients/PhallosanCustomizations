<?php declare(strict_types=1);

namespace PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirementOrder;

use PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirement\AccessoryRequirementEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class AccessoryRequirementOrderEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $accessoryRequirementId;

    protected AccessoryRequirementEntity $accessoryRequirement;

    protected ?string $orderId;

    protected string $orderNumber;

    protected ?OrderEntity $order;

    protected ?string $redeemedOrderId;

    protected ?string $redeemedOrderNumber;

    protected ?OrderEntity $redeemedOrder;

    protected ?string $comment;

    protected bool $migrated = false;

    protected ?\DateTimeInterface $migratedAt = null;

    protected ?\DateTimeInterface $redeemedAt = null;

    public function getAccessoryRequirementId(): string
    {
        return $this->accessoryRequirementId;
    }

    public function setAccessoryRequirementId(string $accessoryRequirementId): void
    {
        $this->accessoryRequirementId = $accessoryRequirementId;
    }

    public function getAccessoryRequirement(): AccessoryRequirementEntity
    {
        return $this->accessoryRequirement;
    }

    public function setAccessoryRequirement(AccessoryRequirementEntity $accessoryRequirement): void
    {
        $this->accessoryRequirement = $accessoryRequirement;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(?string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(string $orderNumber): void
    {
        $this->orderNumber = $orderNumber;
    }

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function setOrder(?OrderEntity $order): void
    {
        $this->order = $order;
    }

    public function getRedeemedOrderId(): ?string
    {
        return $this->redeemedOrderId;
    }

    public function setRedeemedOrderId(?string $redeemedOrderId): void
    {
        $this->redeemedOrderId = $redeemedOrderId;
    }

    public function getRedeemedOrderNumber(): ?string
    {
        return $this->redeemedOrderNumber;
    }

    public function setRedeemedOrderNumber(?string $redeemedOrderNumber): void
    {
        $this->redeemedOrderNumber = $redeemedOrderNumber;
    }

    public function getRedeemedOrder(): ?OrderEntity
    {
        return $this->redeemedOrder;
    }

    public function setRedeemedOrder(?OrderEntity $redeemedOrder): void
    {
        $this->redeemedOrder = $redeemedOrder;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    public function isMigrated(): bool
    {
        return $this->migrated;
    }

    public function setMigrated(bool $migrated): void
    {
        $this->migrated = $migrated;
    }

    public function getMigratedAt(): ?\DateTimeInterface
    {
        return $this->migratedAt;
    }

    public function setMigratedAt(?\DateTimeInterface $migratedAt): void
    {
        $this->migratedAt = $migratedAt;
    }

    public function getRedeemedAt(): ?\DateTimeInterface
    {
        return $this->redeemedAt;
    }

    public function setRedeemedAt(?\DateTimeInterface $redeemedAt): void
    {
        $this->redeemedAt = $redeemedAt;
    }
}
