<?php declare(strict_types=1);

namespace PhallosanCustomizations\Controller;

use PhallosanCustomizations\PhallosanConstants;
use PhallosanCustomizations\Service\CountrySalesChannelMappingService;
use PhallosanCustomizations\Service\LanguageChannelSwitchService;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLogoutRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class LanguageSwitchController extends StorefrontController
{
    public const COOKIE_SELECTED_COUNTRY = 'phallosan_selected_country';
    public function __construct(
        private readonly AccountService $accountService,
        private readonly AbstractLogoutRoute $logoutRoute,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
        private readonly LanguageChannelSwitchService $languageChannelSwitchService,
        private readonly CountrySalesChannelMappingService $mappingService,
        private readonly AbstractContextSwitchRoute $contextSwitchRoute,
    ) {
    }

    /**
     * Legacy redirect route - keeps backward compatibility
     */
    #[Route(path: '/LanguageSwitch/redirect', name: 'frontend.language_switch.redirect', requirements: ['route' => '.+'], methods: ['POST'])]
    public function redirectCustomer(
        Request $request,
        SalesChannelContext $salesChannelContext
    ): Response {
        $response = new Response();
        $response->headers->set('X-Robots-Tag', 'noindex, follow');

        // Check if this is a language-only switch (from the language dropdown)
        $languageDomainId = $request->get('languageDomainId');

        if ($languageDomainId) {
            // Language switch: use the domain ID directly, keep current country
            $salesChannelDomainId = $languageDomainId;
            $countryId = $request->getSession()->get(PhallosanConstants::SESSION_SALES_CHANNEL_COUNTRY_ID, '');

            if (empty($countryId)) {
                // Fallback: get country from current sales channel
                $currentCountry = $salesChannelContext->getSalesChannel()->getCountry();
                $countryId = $currentCountry ? $currentCountry->getId() : '';
            }
        } else {
            // Country switch: get domain ID and country ID from combined value
            $salesChannelDomainIdAndCountryId = $request->get('salesChannelDomainIdAndCountryId');
            [$salesChannelDomainId, $countryId] = explode('-', (string) $salesChannelDomainIdAndCountryId);
        }

        $route = $this->languageChannelSwitchService->createRedirectRoute(
            $salesChannelContext,
            $salesChannelDomainId,
            $countryId,
            $request->getSession(),
            $request->get('requestUri'),
            $request->attributes->get('_route'),
            $request->get('fragment')
        );

        if ($route === false) {
            return $response;
        }

        $routeAvailable = $this->redirect($this->languageChannelSwitchService->domain->getUrl() . $route)->getStatusCode() === Response::HTTP_FOUND;

        $response = $routeAvailable
            ? $this->redirect($this->languageChannelSwitchService->domain->getUrl() . $route)
            : $this->redirect($this->languageChannelSwitchService->domain->getUrl());

        $response->headers->set('X-Robots-Tag', 'noindex, follow');


        if ($salesChannelContext->getCustomerId()) {
            $this->transferCustomerLogin($this->languageChannelSwitchService->domain, $salesChannelContext->getCustomerId(), $salesChannelContext->getToken());
        }

        return $response;
    }

    private function transferCustomerLogin(SalesChannelDomainEntity $domain, string $customerId, string $token): void
    {
        $options = [
            'currencyId' => $domain->getSalesChannel()?->getCurrencyId(),
            'languageId' => $domain->getSalesChannel()?->getLanguageId(),
        ];

        $newSalesChannelContext = $this->salesChannelContextFactory->create(
            $token,
            $domain->getSalesChannelId(),
            $options
        );

        if ($newSalesChannelContext->getCustomer()) {
            $newTokenResponse = $this->logoutRoute->logout($newSalesChannelContext, new RequestDataBag());

            $newSalesChannelContext = $this->salesChannelContextFactory->create($newTokenResponse->getToken(), $newSalesChannelContext->getSalesChannelId());
        }

        $this->accountService->loginById($customerId, $newSalesChannelContext);
    }

    /**
     * New route for country-based redirect using 3-SC mapping model
     * Called when user selects a country from the dropdown
     */
    #[Route(path: '/LanguageSwitch/country', name: 'frontend.language_switch.country', methods: ['POST'])]
    public function redirectByCountry(
        Request $request,
        SalesChannelContext $salesChannelContext
    ): Response {
        $response = new Response();
        $response->headers->set('X-Robots-Tag', 'noindex, follow');

        $countryIso = $request->get('countryIso');
        
        if (!$countryIso || !$this->mappingService->hasMapping($countryIso)) {
            // Fallback to current page
            return $this->redirect($request->headers->get('referer', '/'));
        }

        $redirectUrl = $this->languageChannelSwitchService->createRedirectRouteForCountry(
            $salesChannelContext,
            $countryIso,
            $request->getSession(),
            $request->get('requestUri', '/'),
            $request->attributes->get('_route', ''),
            $request->get('fragment')
        );

        // If user is logged in and changing Sales Channel, transfer login
        if ($salesChannelContext->getCustomerId() && 
            $this->languageChannelSwitchService->needsSalesChannelSwitch($countryIso, $salesChannelContext)) {
            // Note: Customer login transfer will happen when redirecting to the new domain
            // The new Sales Channel will handle re-login via shared customer pool
        }

        // Auto-switch currency based on static country->currency mapping
        // Pass target SC ID so the service can check availability and fall back to region default
        $targetScId = $this->mappingService->getSalesChannelIdForCountry($countryIso) ?? $salesChannelContext->getSalesChannelId();
        $currencyId = $this->mappingService->getCurrencyIdForCountry($countryIso, $targetScId);
        if ($currencyId) {
            try {
                $this->contextSwitchRoute->switchContext(
                    new RequestDataBag(['currencyId' => $currencyId]),
                    $salesChannelContext
                );
            } catch (\Exception $e) {
                // Currency switch failed, continue with redirect (domain default currency will apply)
            }
        }

        $response = new RedirectResponse($redirectUrl, Response::HTTP_FOUND);
        $response->headers->set('X-Robots-Tag', 'noindex, follow');
        
        // Get the cookie domain from current host (e.g., preview.phallosan.com -> .phallosan.com)
        $cookieDomain = $this->getCookieDomain($request->getHost());
        
        // Set cookie with selected country (valid for 30 days, shared across subdomains)
        $cookie = Cookie::create(self::COOKIE_SELECTED_COUNTRY)
            ->withValue(strtoupper($countryIso))
            ->withExpires(time() + (30 * 86400))
            ->withPath('/')
            ->withDomain($cookieDomain)
            ->withSecure($request->isSecure())
            ->withHttpOnly(false)
            ->withSameSite(Cookie::SAMESITE_LAX);
        $response->headers->setCookie($cookie);

        return $response;
    }
    
    /**
     * Get root domain for cookie sharing across subdomains
     * e.g., "preview.phallosan.com" -> ".phallosan.com"
     * e.g., "eu.phallosan.com" -> ".phallosan.com"
     */
    private function getCookieDomain(string $host): string
    {
        // Remove port if present
        $host = explode(':', $host)[0];
        
        // Split by dots
        $parts = explode('.', $host);
        
        // If we have at least 2 parts (domain.tld), take last 2
        if (count($parts) >= 2) {
            return '.' . implode('.', array_slice($parts, -2));
        }
        
        // Fallback to current host
        return $host;
    }

    /**
     * AJAX endpoint for getting redirect URL without performing redirect
     * Useful for JavaScript-based redirects
     */
    #[Route(path: '/LanguageSwitch/getRedirectUrl', name: 'frontend.language_switch.get_redirect_url', methods: ['POST'])]
    public function getRedirectUrl(
        Request $request,
        SalesChannelContext $salesChannelContext
    ): JsonResponse {
        $countryIso = $request->get('countryIso');
        $currentPath = $request->get('currentPath', '/');

        if (!$countryIso) {
            return new JsonResponse(['error' => 'Country ISO required'], Response::HTTP_BAD_REQUEST);
        }

        $redirectUrl = $this->mappingService->getRedirectUrl($countryIso, $currentPath);
        $needsChannelSwitch = $this->languageChannelSwitchService->needsSalesChannelSwitch($countryIso, $salesChannelContext);

        return new JsonResponse([
            'redirectUrl' => $redirectUrl,
            'needsChannelSwitch' => $needsChannelSwitch,
            'region' => $this->mappingService->getRegionForCountry($countryIso),
            'defaultLanguage' => $this->mappingService->getDefaultLanguageForCountry($countryIso),
        ]);
    }
}
