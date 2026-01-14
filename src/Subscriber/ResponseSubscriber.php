<?php declare(strict_types=1);

namespace PhallosanCustomizations\Subscriber;

use PhallosanCustomizations\Service\CategoryRedirectService;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ResponseSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CategoryRedirectService $categoryRedirectService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'redirectToCategory',
        ];
    }

    public function redirectToCategory(ProductPageLoadedEvent $event): void
    {
        $request = $event->getRequest();
        $attributes = $event->getRequest()->attributes;

        $productId = $attributes->get('productId') ?? '';

        if ($this->categoryRedirectService->hasDetailPage($productId, $event->getContext())) {
            return;
        }

        $redirectCategoryId = $this->categoryRedirectService->getRedirectCategoryId($productId, $event->getContext());

        if (!$redirectCategoryId) {
            return;
        }

        $languageId = (string)$request->headers->get('sw-language-id');
        $seoCategoryUrl = $this->categoryRedirectService->getSeoUrlByCategoryId($redirectCategoryId, $languageId, $event->getContext());

        /** @var SalesChannelContext $salesChannelContext */
        $salesChannelContext = $event->getSalesChannelContext();
        /** @var SalesChannelDomainCollection $domainsForLanguage */
        $domainsForLanguage = $salesChannelContext->getSalesChannel()->getDomains()?->filter(function ($domain) use ($languageId) {
            return $domain->getLanguageId() === $languageId;
        });

        $domainsForHttps = $domainsForLanguage->filter(function ($domain) {
            return str_contains($domain->getUrl(), 'https://');
        });

        if ($domainsForHttps->count() === 0) {
            $domainsForHttps = $domainsForLanguage;
        }

        $domain = $domainsForHttps->first()?->getUrl();


        if (empty($seoCategoryUrl)) {
            return;
        }

        $redirectResponse = new RedirectResponse($domain . $seoCategoryUrl);
        $redirectResponse->send();
    }
}
