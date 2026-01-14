<?php declare(strict_types=1);

namespace PhallosanCustomizations\Tests\Trait;

use PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirement\AccessoryRequirementEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait AccessoryRequirementTrait
{
    private EntityRepository $accessoryRequirementRepository;

    private EntityRepository $accessoryRequirementOrderRepository;

    private Context $context;

    private bool $init = false;

    public function initAccessoryRequirement(): void
    {
        $this->accessoryRequirementRepository = $this->getContainer()->get('accessory_requirement.repository');
        $this->accessoryRequirementOrderRepository = $this->getContainer()->get('accessory_requirement_order.repository');
        $this->context = Context::createDefaultContext();

        $this->init = true;
    }

    public function accessoryRequirementUpsert(array $accessoryRequiredData): void
    {
        if (!$this->init) {
            throw new \InvalidArgumentException('Trait not initiated. Please call $this->initAccessoryRequired() first');
        }

        $this->accessoryRequirementRepository->upsert([$accessoryRequiredData], $this->context);
    }

    public function getAccessoryRequirementByProduct(string $productId): ?AccessoryRequirementEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('productId', $productId)
        );

        return $this->accessoryRequirementSearch($criteria)->first();
    }

    public function getAccessoryRequirementById(string $accessoryRequiredId): ?AccessoryRequirementEntity
    {
        return $this->accessoryRequirementSearch(new Criteria([$accessoryRequiredId]))->first();
    }

    public function accessoryRequirementSearch(Criteria $criteria): ?EntitySearchResult
    {
        if (!$this->init) {
            throw new \InvalidArgumentException('Trait not initiated. Please call $this->initAccessoryRequired() first');
        }

        return $this->accessoryRequirementRepository->search($criteria, $this->context);
    }

    public function accessoryRequirementOrderUpsert(array $accessoryRequiredOrderData): void
    {
        if (!$this->init) {
            throw new \InvalidArgumentException('Trait not initiated. Please call $this->initAccessoryRequired() first');
        }

        $this->accessoryRequirementOrderRepository->upsert([$accessoryRequiredOrderData], $this->context);
    }

    public function getAccessoryRequirementOrderById(string $accessoryRequiredOrderId): ?AccessoryRequirementEntity
    {
        return $this->accessoryRequirementOrderSearch(new Criteria([$accessoryRequiredOrderId]))->first();
    }

    public function accessoryRequirementOrderSearch(Criteria $criteria): ?EntitySearchResult
    {
        if (!$this->init) {
            throw new \InvalidArgumentException('Trait not initiated. Please call $this->initAccessoryRequired() first');
        }

        return $this->accessoryRequirementOrderRepository->search($criteria, $this->context);
    }

    abstract protected static function getContainer(): ContainerInterface;
}
