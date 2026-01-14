<?php

declare(strict_types=1);

namespace PhallosanCustomizations\Subscriber;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class RouteSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onController',
        ];
    }

    public function onController(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        // Only intercept storefront requests with a route
        $routeName = $request->attributes->get('_route');
        if (!$routeName) {
            return;
        }

        $salesChannelId = $request->attributes->get('sw-sales-channel-id');

        $blockedRoutes = [
            'frontend.account.login.page',
            'frontend.account.register.page',
            'frontend.checkout.cart.page',
            'frontend.checkout.confirm.page',
        ];

        if (!\in_array($routeName, $blockedRoutes, true)) {
            return;
        }

        $externalCheckoutLink = $this->systemConfigService->getString(
            'PhallosanCustomizations.config.externalCheckoutLink',
            $salesChannelId
        );

        if (empty($externalCheckoutLink)) {
            return;
        }

        $redirectUrl = $this->getRedirectUrl($request, $externalCheckoutLink);

        $event->setController(function () use ($redirectUrl) {
            return new RedirectResponse($redirectUrl);
        });
    }

    private function getRedirectUrl(Request $request, ?string $externalCheckoutLink = null): string
    {
        if (!empty($externalCheckoutLink)) {
            return $externalCheckoutLink;
        }

        // fallback to storefront home
        $storefrontUrl = $request->attributes->get('sw-storefront-url');

        return rtrim($storefrontUrl, '/') . '/';
    }
}
