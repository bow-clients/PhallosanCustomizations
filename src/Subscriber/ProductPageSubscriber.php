<?php declare(strict_types=1);

namespace PhallosanCustomizations\Subscriber;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductPageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CartService $cartService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'onProductPageLoaded',
        ];
    }

    public function onProductPageLoaded(ProductPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $context = $event->getSalesChannelContext();

        /** @var SalesChannelProductEntity $product */
        $product = $page->getProduct();

        // Use random UUID as token to not interfere with default cart
        $cart = $this->cartService->createNew(Uuid::randomHex());

        $lineItem = new LineItem(
            $product->getId(),
            LineItem::PRODUCT_LINE_ITEM_TYPE,
            $product->getId()
        );
        $lineItem->setRemovable(true);

        $cart = $this->cartService->add($cart, $lineItem, $context);
        $shippingCost = $cart->getShippingCosts()->getTotalPrice();

        $page->addExtension('estimatedShipping', new ArrayEntity([
            'shippingCost' => $shippingCost,
        ]));
    }
}
