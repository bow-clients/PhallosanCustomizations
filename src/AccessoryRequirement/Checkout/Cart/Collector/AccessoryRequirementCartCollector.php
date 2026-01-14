<?php declare(strict_types=1);

namespace PhallosanCustomizations\AccessoryRequirement\Checkout\Cart\Collector;

use PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirement\AccessoryRequirementEntity;
use PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirement\Extensions\ProductExtension;
use PhallosanCustomizations\AccessoryRequirement\Service\AccessoryRequirementService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AccessoryRequirementCartCollector implements CartDataCollectorInterface
{
    public function __construct(
        private readonly AccessoryRequirementService $accessoryRequirementService,
    ) {
    }

    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        if ($original->getLineItems()->count() === 0) {
            return;
        }

        $lineItems = $original->getLineItems()->filter(
            closure: fn (LineItem $entity) => $entity->getType() !== LineItem::PROMOTION_LINE_ITEM_TYPE
        );

        $productIds = $this->accessoryRequirementService->getProductIds($lineItems);

        $idHash = md5(implode('|', $productIds));

        $dataAccessoryRequirement = $data->get('accessory-requirement') ?? [];
        $dataAccessoryIdHash = $dataAccessoryRequirement['idHash'] ?? null;

        if ($dataAccessoryIdHash === $idHash) {
            return;
        }

        $productsWithRequirements = $this->accessoryRequirementService->getRequirementsByProducts($productIds);

        if (\count($productsWithRequirements) === 0) {
            return;
        }

        foreach ($original->getLineItems() as $lineItem) {
            $this->collectLineItemData($lineItem, $productsWithRequirements, $original, $context);
        }

        $idHash = md5(implode('|', $productIds));

        $data->set('accessory-requirement', [
            'idHash' => $idHash,
        ]);
    }

    public function collectLineItemData(LineItem $lineItem, array $productsWithRequirements, Cart $original, SalesChannelContext $context): void
    {
        $accessoryRequirementProductId = $this->getAccessoryRequirementProductId($lineItem, $productsWithRequirements);

        if ($accessoryRequirementProductId === null) {
            return;
        }

        $accessoryRequirementId = $productsWithRequirements[$accessoryRequirementProductId];

        /** @var AccessoryRequirementEntity|null $accessoryRequirement */
        $accessoryRequirement = $this->accessoryRequirementService->getAccessoryRequirementList([$accessoryRequirementId], $context->getContext())
            ->first();

        if ($accessoryRequirement === null) {
            return;
        }

        $requiredProductId = $accessoryRequirement->getRequiredProductId();
        $isRequiredProductInCart = $this->accessoryRequirementService->isRequiredProductInCollection((string)$requiredProductId, $original->getLineItems());

        $requiredProductOrderNumber = null;
        $isRequiredOrderValid = false;
        if ($lineItem->hasExtension(ProductExtension::NAME)) {
            $extension = $lineItem->getExtension(ProductExtension::NAME);
            \assert($extension instanceof ArrayStruct);
            $requiredProductOrderNumber = $extension['requiredProductOrderNumber'] ?? null;
        }

        if ($requiredProductOrderNumber) {
            $isRequiredOrderValid = $this->accessoryRequirementService->isRequiredOrderValid($requiredProductOrderNumber, $accessoryRequirementId, $context->getContext());
        }

        $lineItem->addExtension(
            ProductExtension::NAME,
            new ArrayStruct([
                'lineItemId' => $lineItem->getId(),
                'requiredProductId' => $requiredProductId,
                'accessoryRequirementId' => $accessoryRequirementId,
                'hasProductRequirement' => true,
                'requiredProductInCart' => $isRequiredProductInCart,
                'requiredProductOrderNumber' => $isRequiredOrderValid ? $requiredProductOrderNumber : null,
                'requiredOrderValid' => $isRequiredOrderValid,
                'requirementsFulfilled' => $isRequiredProductInCart || $isRequiredOrderValid,
            ])
        );
    }

    public function getAccessoryRequirementProductId(LineItem $lineItem, array $productsWithRequirements): ?string
    {
        $productId = $lineItem->getReferencedId();

        $requiredProductIds = array_keys($productsWithRequirements);

        if (\in_array($productId, $requiredProductIds, true)) {
            return $productId;
        }

        if (!$lineItem->hasChildren()) {
            return null;
        }

        $childProductIds = $this->accessoryRequirementService->getProductIds($lineItem->getChildren());

        $requiredProductInChilds = array_diff($requiredProductIds, array_diff($childProductIds, $requiredProductIds));

        if (\count($requiredProductInChilds) === 0) {
            return null;
        }

        if (isset($requiredProductInChilds[0])) {
            return (string)$requiredProductInChilds[0];
        }

        return null;
    }
}
