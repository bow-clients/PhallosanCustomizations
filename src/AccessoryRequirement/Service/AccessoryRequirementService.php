<?php declare(strict_types=1);

namespace PhallosanCustomizations\AccessoryRequirement\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirement\AccessoryRequirementDefinition;
use PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirement\AccessoryRequirementEntity;
use PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirement\Extensions\ProductExtension;
use PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirementOrder\AccessoryRequirementOrderDefinition;
use PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirementOrder\AccessoryRequirementOrderEntity;
use PhallosanCustomizations\AccessoryRequirement\Exceptions\OrderNumberAlreadyRedeemedException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Uuid\Uuid;

class AccessoryRequirementService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $accessoryRequirementRepository,
        private readonly EntityRepository $accessoryRequirementOrderRepository,
        private readonly EntityRepository $orderRepository
    ) {
    }

    public function accessoryRequirementOrderSearch(Criteria $criteria, Context $context): ?EntitySearchResult
    {
        return $this->accessoryRequirementOrderRepository->search($criteria, $context);
    }

    public function upsertAccessoryRequirementOrder(array $payload, Context $context): void
    {
        $this->accessoryRequirementOrderRepository->upsert($payload, $context);
    }

    public function getAccessoryRequirementList(array $accessoryRequirementIds, Context $context): EntitySearchResult
    {
        $criteria = new Criteria($accessoryRequirementIds);

        return $this->accessoryRequirementRepository->search($criteria, $context);
    }

    public function isRequirementFulfilled(AccessoryRequirementEntity $accessoryRequirementEntity, Collection $lineItems): bool
    {
        /**
         * Check if the required Product is in the Cart
         */
        $requiredProductId = $accessoryRequirementEntity->getRequiredProductId();
        $isRequiredProductInCollection = $this->isRequiredProductInCollection((string)$requiredProductId, $lineItems);

        if ($isRequiredProductInCollection) {
            return true;
        }

        /**
         * check if the accessory product is a simple product
         */
        $lineItemWithAccessoryRequirementProduct = $lineItems->get((string)$accessoryRequirementEntity->getProductId());
        if (!$lineItemWithAccessoryRequirementProduct) {
            /**
             * check if the product is part of a container product
             */
            /** @var LineItem $lineItem */
            foreach ($lineItems as $lineItem) {
                if (!$lineItem->hasChildren()) {
                    continue;
                }

                $childProductIds = $this->getProductIds($lineItem->getChildren());

                $requiredProductInChilds = array_diff([$requiredProductId], array_diff($childProductIds, [$requiredProductId]));

                if (\count($requiredProductInChilds) === 0) {
                    continue;
                }

                $lineItemWithAccessoryRequirementProduct = $lineItem;

                break;
            }
        }

        if (!$lineItemWithAccessoryRequirementProduct) {
            return false;
        }

        \assert($lineItemWithAccessoryRequirementProduct instanceof LineItem);

        $extension = $lineItemWithAccessoryRequirementProduct->getExtension(ProductExtension::NAME);

        if (!$extension) {
            return false;
        }

        $orderNumber = $extension->getVars()['requiredProductOrderNumber'] ?? null;

        if (empty($orderNumber)) {
            return false;
        }

        $isRequiredOrderNumberValid = $this->isRequiredOrderValid($orderNumber, $accessoryRequirementEntity->getId(), Context::createDefaultContext());

        if (!$isRequiredOrderNumberValid) {
            return false;
        }

        return true;
    }

    public function getRequirementOrderByOrderNumber(string $orderNumber, string $accessoryRequirementId, Context $context): ?AccessoryRequirementOrderEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('accessoryRequirementId', $accessoryRequirementId), new EqualsFilter('orderNumber', $orderNumber));

        /** @var AccessoryRequirementOrderEntity|null $accessoryRequirementOrderEntity */
        $accessoryRequirementOrderEntity = $this->accessoryRequirementOrderRepository->search($criteria, $context)->first();

        return $accessoryRequirementOrderEntity;
    }

    public function getOrderByNumber(string $orderNumber, Context $context, ?bool $withCondition = null): ?OrderEntity
    {
        if ($withCondition === null) {
            $withCondition = true;
        }
        $criteria = new Criteria();
        $criteria->addAssociation('lineItems');
        $criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber));
        if ($withCondition === true) {
            $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
                new EqualsAnyFilter('stateMachineState.technicalName', [
                    OrderTransactionStates::STATE_CANCELLED,
                    OrderTransactionStates::STATE_FAILED,
                    OrderTransactionStates::STATE_REFUNDED,
                ]),
            ]));
        }

        /** @var OrderEntity|null $orderEntity */
        $orderEntity = $this->orderRepository->search($criteria, $context)->first();

        return $orderEntity;
    }

    public function checkRequiredOrderManualEntry(string $orderNumber, string $accessoryRequirementId): bool
    {
        $tableName = AccessoryRequirementOrderDefinition::ENTITY_NAME;

        $query = $this->connection->createQueryBuilder();
        $query->from($tableName);
        $query->andWhere($tableName . '.accessory_requirement_id = :accessoryRequirementId');
        $query->andWhere($tableName . '.order_number = :orderNumber');
        $query->setParameter('accessoryRequirementId', Uuid::fromHexToBytes($accessoryRequirementId));
        $query->setParameter('orderNumber', $orderNumber);

        $query->select([
            $tableName . '.redeemed_order_id',
            $tableName . '.redeemed_order_number',
            $tableName . '.redeemed_at',
        ]);

        try {
            $result = $query->execute();

            if (!$result instanceof Result) {
                return false;
            }

            $results = $result->fetchAll();

            if (\count($results) === 0) {
                return false;
            }

            $redeemedAt = $results[0]['redeemed_at'];
        } catch (\Exception $e) {
            /**
             * @todo check if logging would be an option here
             */
            return false;
        }

        /**
         * if it has already been redeemed, don't allow it (only 1 purchase per order))
         */
        if (!empty($redeemedAt)) {
            throw new OrderNumberAlreadyRedeemedException();
        }

        return true;
    }

    public function isRequiredOrderValid(string $orderNumber, string $accessoryRequirementId, Context $context): bool
    {
        /**
         * check for an existing entry in the accessory_requirement_order table or in the order table directoy
         */
        try {
            $isInManualList = $this->checkRequiredOrderManualEntry($orderNumber, $accessoryRequirementId);
        } catch (OrderNumberAlreadyRedeemedException $e) {
            return false;
        }

        if ($isInManualList) {
            return true;
        }

        /**
         * check for existing entry in order table with all order states except "cancelled"
         */
        $orderEntity = $this->getOrderByNumber($orderNumber, $context);

        if (!$orderEntity) {
            return false;
        }

        return $this->checkRequiredProductInOrder($orderEntity, $accessoryRequirementId);
    }

    public function isRequiredProductInCollection(string $productId, Collection $collection): bool
    {
        /** @var Entity $item */
        foreach ($collection as $item) {
            if (method_exists($item, 'getReferencedId')) {
                if ($item->getReferencedId() === $productId) {
                    return true;
                }
            }
            if (method_exists($item, 'getChildren')) {
                if ($this->isRequiredProductInCollection($productId, $item->getChildren())) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getProductIds(Collection $lineItems): array
    {
        $productIds = [];

        /** @var Entity $lineItem */
        foreach ($lineItems as $lineItem) {
            if (!method_exists($lineItem, 'getReferencedId')) {
                continue;
            }
            if (!$lineItem->getReferencedId()) {
                continue;
            }
            $productIds[] = $lineItem->getReferencedId();

            if (method_exists($lineItem, 'getChildren')) {
                $childProductIds = $this->getProductIds($lineItem->getChildren());

                foreach ($childProductIds as $childValue) {
                    if (!$childValue) {
                        continue;
                    }
                    $productIds[] = $childValue;
                }
            }
        }

        return $productIds;
    }

    public function getRequirementsByProducts(array $productIds): array
    {
        $tableName = AccessoryRequirementDefinition::ENTITY_NAME;

        $query = $this->connection->createQueryBuilder();
        $query->from($tableName);
        $query->andWhere($tableName . '.product_id IN (:ids)');
        $query->setParameter('ids', Uuid::fromHexToBytesList($productIds), Connection::PARAM_STR_ARRAY);

        $query->select([
            $tableName . '.id',
            $tableName . '.product_id',
        ]);

        try {
            $result = $query->execute();

            if (!$result instanceof Result) {
                return [];
            }

            $results = $result->fetchAll();

            if (\count($results) === 0) {
                return [];
            }

            $mappedList = [];
            foreach ($results as $result) {
                $mappedList[Uuid::fromBytesToHex($result['product_id'])] = Uuid::fromBytesToHex($result['id']);
            }

            return $mappedList;
        } catch (\Exception $e) {
            /**
             * @todo check if logging would be an option here
             */
        }

        return [];
    }

    private function checkRequiredProductInOrder(OrderEntity $orderEntity, string $accessoryRequirementId): bool
    {
        /** @var AccessoryRequirementEntity|null $accessoryRequirement */
        $accessoryRequirement = $this->getAccessoryRequirementList([$accessoryRequirementId], Context::createDefaultContext())->first();

        if ($accessoryRequirement === null) {
            return false;
        }

        $requiredProductId = $accessoryRequirement->getRequiredProductId();
        $productIds = $this->getProductIdsFromOrder($orderEntity->getLineItems() ?? new OrderLineItemCollection());

        return \in_array($requiredProductId, $productIds, true);
    }

    private function getProductIdsFromOrder(OrderLineItemCollection $lineItems): array
    {
        $productIds = [];

        /** @var OrderLineItemEntity $lineItem */
        foreach ($lineItems as $lineItem) {
            if (!$lineItem->getReferencedId()) {
                continue;
            }
            $productIds[] = $lineItem->getReferencedId();

            if ($lineItem->getChildren() && $lineItem->getChildren()->count()) {
                $childProductIds = $this->getProductIdsFromOrder($lineItem->getChildren());

                foreach ($childProductIds as $childValue) {
                    $productIds[] = $childValue;
                }
            }
        }

        return $productIds;
    }
}
