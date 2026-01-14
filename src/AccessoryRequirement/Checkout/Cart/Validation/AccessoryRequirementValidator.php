<?php declare(strict_types=1);

namespace PhallosanCustomizations\AccessoryRequirement\Checkout\Cart\Validation;

use PhallosanCustomizations\AccessoryRequirement\Checkout\Cart\Error\AccessoryRequirementNotFulfilled;
use PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirement\AccessoryRequirementEntity;
use PhallosanCustomizations\AccessoryRequirement\Service\AccessoryRequirementService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Api\Context\AdminSalesChannelApiSource;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AccessoryRequirementValidator implements CartValidatorInterface
{
    public function __construct(
        private readonly AccessoryRequirementService $accessoryRequirementService,
        private readonly AbstractTranslator $translator
    ) {
    }

    public static function isAdminContext(SalesChannelContext $context): bool
    {
        $adminSalesChannelSource = (new AdminSalesChannelApiSource($context->getSalesChannelId(), $context->getContext()))->type;

        return $context->getContext()->getSource()->type === $adminSalesChannelSource;
    }

    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        $lineItems = $cart->getLineItems()->filter(
            closure: fn (LineItem $entity) => $entity->getType() !== LineItem::PROMOTION_LINE_ITEM_TYPE
        );

        $productsWithRequirements = $this->getProductsWithRequirements($lineItems);

        if (\count($productsWithRequirements) === 0) {
            return;
        }

        /**
         * skip validator if the order is created from the administration
         */
        if (self::isAdminContext($context)) {
            return;
        }

        $nonFulfilledAccessoryRequirements = $this->productRequirementListFulfilled($productsWithRequirements, $cart, $context);

        if (\count($nonFulfilledAccessoryRequirements) === 0) {
            return;
        }

        /**
         * @var string $accessoryProductId
         * @var AccessoryRequirementEntity $notFulfilledAccessoryRequirement
         */
        foreach ($nonFulfilledAccessoryRequirements as $accessoryProductId => $notFulfilledAccessoryRequirement) {
            $accessoryRequirementSnippetName = AccessoryRequirementNotFulfilled::getSnippetName($notFulfilledAccessoryRequirement->getId());
            $snippetName = 'checkout.' . $accessoryRequirementSnippetName;
            $snippetErrorMessage = $this->translator->trans(
                $snippetName
            );

            $errorMessageKey = null;
            if (!empty($snippetErrorMessage)
                && $snippetErrorMessage !== $snippetName) {
                $errorMessageKey = $accessoryRequirementSnippetName;
            }

            $errors->add(new AccessoryRequirementNotFulfilled($accessoryProductId, $errorMessageKey));
        }
    }

    protected function productRequirementListFulfilled(array $productsWithRequirements, Cart $cart, SalesChannelContext $context): array
    {
        $accessoryRequirementList = $this->accessoryRequirementService->getAccessoryRequirementList(array_values($productsWithRequirements), $context->getContext());

        if ($accessoryRequirementList->count() === 0) {
            return [];
        }

        $notFulfilledAccessoryRequirements = [];

        /** @var AccessoryRequirementEntity $accessoryRequirement */
        foreach ($accessoryRequirementList as $accessoryRequirement) {
            $isFulfilled = $this->accessoryRequirementService->isRequirementFulfilled($accessoryRequirement, $cart->getLineItems());

            if (!$isFulfilled) {
                $notFulfilledAccessoryRequirements[$accessoryRequirement->getProductId()] = $accessoryRequirement;
            }
        }

        return $notFulfilledAccessoryRequirements;
    }

    protected function getProductsWithRequirements(LineItemCollection $lineItems): array
    {
        $productIds = $this->accessoryRequirementService->getProductIds($lineItems);

        $productWithRequirements = $this->accessoryRequirementService->getRequirementsByProducts($productIds);

        return $productWithRequirements;
    }
}
