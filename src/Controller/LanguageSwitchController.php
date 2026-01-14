<?php declare(strict_types=1);

namespace PhallosanCustomizations\Controller;

use PhallosanCustomizations\Service\LanguageChannelSwitchService;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLogoutRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class LanguageSwitchController extends StorefrontController
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly AbstractLogoutRoute $logoutRoute,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
        private readonly LanguageChannelSwitchService $languageChannelSwitchService,
    ) {
    }

    #[Route(path: '/LanguageSwitch/redirect', name: 'frontend.language_switch.redirect', requirements: ['route' => '.+'], methods: ['POST'])]
    public function redirectCustomer(
        Request $request,
        SalesChannelContext $salesChannelContext
    ): Response {
        $response = new Response();
        $response->headers->set('X-Robots-Tag', 'noindex, follow');

        $salesChannelDomainIdAndCountryId = $request->get('salesChannelDomainIdAndCountryId');
        [$salesChannelDomainId, $countryId] = explode('-', $salesChannelDomainIdAndCountryId);

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
}
