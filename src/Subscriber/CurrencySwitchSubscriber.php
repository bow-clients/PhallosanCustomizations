<?php declare(strict_types=1);

namespace PhallosanCustomizations\Subscriber;

use PhallosanCustomizations\Controller\LanguageSwitchController;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Handles currency switching after cross-Sales-Channel redirects.
 *
 * When a user switches country and gets redirected to a different Sales Channel,
 * the LanguageSwitchController sets a short-lived cookie with the target currency ID.
 * This subscriber reads that cookie on the target domain's first page load
 * and switches the currency via ContextSwitchRoute.
 */
class CurrencySwitchSubscriber implements EventSubscriberInterface
{
    private ?string $pendingCurrencyId = null;

    public function __construct(
        private readonly AbstractContextSwitchRoute $contextSwitchRoute,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // High priority: switch currency before page renders
            StorefrontRenderEvent::class => ['onStorefrontRender', 200],
            // Clear the cookie in the response
            KernelEvents::RESPONSE => ['onKernelResponse', -100],
        ];
    }

    public function onStorefrontRender(StorefrontRenderEvent $event): void
    {
        $request = $event->getRequest();
        $currencyId = $request->cookies->get(LanguageSwitchController::COOKIE_TARGET_CURRENCY);

        if (!$currencyId || !preg_match('/^[a-f0-9]{32}$/', $currencyId)) {
            return;
        }

        $salesChannelContext = $event->getSalesChannelContext();

        // Only switch if the currency differs from current
        if ($salesChannelContext->getCurrencyId() === $currencyId) {
            $this->pendingCurrencyId = $currencyId; // Mark for cookie cleanup
            return;
        }

        try {
            $this->contextSwitchRoute->switchContext(
                new RequestDataBag(['currencyId' => $currencyId]),
                $salesChannelContext
            );
            $this->pendingCurrencyId = $currencyId;
        } catch (\Exception $e) {
            // Currency not available in this SC — clear cookie anyway
            $this->pendingCurrencyId = $currencyId;
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if ($this->pendingCurrencyId === null) {
            return;
        }

        // Clear the one-time cookie
        $request = $event->getRequest();
        $host = $request->getHost();
        $parts = explode('.', explode(':', $host)[0]);
        $cookieDomain = count($parts) >= 2 ? '.' . implode('.', array_slice($parts, -2)) : $host;

        $event->getResponse()->headers->setCookie(
            Cookie::create(LanguageSwitchController::COOKIE_TARGET_CURRENCY)
                ->withValue('')
                ->withExpires(time() - 3600)
                ->withPath('/')
                ->withDomain($cookieDomain)
                ->withSecure($request->isSecure())
                ->withHttpOnly(true)
        );

        $this->pendingCurrencyId = null;
    }
}
