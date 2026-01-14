<?php declare(strict_types=1);

namespace PhallosanCustomizations\AccessoryRequirement\Subscriber;

use PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirement\Extensions\ProductExtension;
use PhallosanCustomizations\AccessoryRequirement\Service\AccessoryRequirementService;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;

class ProductLoadedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AccessoryRequirementService $accessoryRequirementService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_LOADED_EVENT => 'onProductsLoaded',
            OffcanvasCartPageLoadedEvent::class => 'onOffcanvasCartPageLoaded',
        ];
    }

    public function onOffcanvasCartPageLoaded(OffcanvasCartPageLoadedEvent $event): void
    {
        $request = $event->getRequest();
        $session = $request->getSession();

        if (!$session->isStarted()) {
            return;
        }

        /** @var FlashBagAwareSessionInterface $session */
        $session->getFlashBag()->clear();
    }

    public function onProductsLoaded(EntityLoadedEvent $event): void
    {
        if (\count($event->getEntities()) === 0) {
            return;
        }

        /** @var array<ProductEntity> $entities */
        $entities = $event->getEntities();

        $productIds = array_map(static fn ($productEntity) => $productEntity->getId(), $entities);
        $productsWithRequirements = $this->accessoryRequirementService->getRequirementsByProducts($productIds);

        foreach ($entities as $productEntity) {
            $hasProductRequirement = false;
            if (\count($productsWithRequirements) !== 0
                && \array_key_exists($productEntity->getId(), $productsWithRequirements)) {
                $hasProductRequirement = true;
            }

            $productEntity->addExtension(
                ProductExtension::NAME,
                new ArrayStruct([
                    'hasProductRequirement' => $hasProductRequirement,
                ])
            );
        }
    }
}
