<?php declare(strict_types=1);

namespace PhallosanCustomizations\AccessoryRequirement\Subscriber;

use PhallosanCustomizations\AccessoryRequirement\Checkout\Cart\Validation\AccessoryRequirementValidator;
use PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirement\Extensions\ProductExtension;
use PhallosanCustomizations\AccessoryRequirement\Service\AccessoryRequirementService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CheckoutOrderSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AccessoryRequirementService $accessoryRequirementService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CartConvertedEvent::class => 'onCartConverted',
        ];
    }

    public function onCartConverted(CartConvertedEvent $event): void
    {
        $convertedCart = $event->getConvertedCart();
        $context = $event->getSalesChannelContext();

        /**
         * skip if the order is created from the administration
         */
        if (AccessoryRequirementValidator::isAdminContext($context)) {
            return;
        }

        /** @var Cart $cart */
        $cart = $event->getCart();

        $orderNumber = $convertedCart['orderNumber'];
        $orderId = $convertedCart['id'];

        /** @var LineItemCollection $lineItems */
        $lineItems = $cart->getLineItems();

        foreach ($lineItems as $lineItem) {
            if (!$lineItem->hasExtension(ProductExtension::NAME)) {
                continue;
            }

            $extension = $lineItem->getExtension(ProductExtension::NAME);
            \assert($extension instanceof ArrayStruct);

            $accessoryRequirementId = $extension['accessoryRequirementId'] ?? null;
            $requiredProductInCart = $extension['requiredProductInCart'] ?? false;
            $requiredProductOrderNumber = $extension['requiredProductOrderNumber'] ?? null;
            $requiredProductOrderId = null;

            /**
             * if the accessory was bought with the required product, it should directly be "redeemed"
             */
            if ($requiredProductInCart) {
                $requiredProductOrderNumber = $orderNumber;
                $requiredProductOrderId = $orderId;
            }
            if (!empty($requiredProductOrderNumber) && empty($requiredProductOrderId)) {
                $orderEntity = $this->accessoryRequirementService->getOrderByNumber($requiredProductOrderNumber, $context->getContext(), false);

                if ($orderEntity) {
                    $requiredProductOrderId = $orderEntity->getId();
                }
            }

            $accessoryRequirementOrderEntity = $this->accessoryRequirementService->getRequirementOrderByOrderNumber($requiredProductOrderNumber, $accessoryRequirementId, $context->getContext());

            $accessoryRequirementOrderId = Uuid::randomHex();
            if ($accessoryRequirementOrderEntity) {
                $accessoryRequirementOrderId = $accessoryRequirementOrderEntity->getId();
            }

            $payload = [
                'id' => $accessoryRequirementOrderId,
                'accessoryRequirementId' => $accessoryRequirementId ?? null,
                'redeemedOrderId' => $orderId,
                'redeemedOrderNumber' => $orderNumber,
                'redeemedAt' => new \DateTimeImmutable(),
                'orderNumber' => $requiredProductOrderNumber,
                'orderId' => $requiredProductOrderId,
            ];

            $this->accessoryRequirementService->upsertAccessoryRequirementOrder([$payload], $context->getContext());
        }
    }
}
