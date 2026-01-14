<?php declare(strict_types=1);

namespace PhallosanCustomizations\Subscriber;

use PhallosanCustomizations\PhallosanConstants;
use PhallosanCustomizations\Service\LanguageChannelSwitchService;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLogoutRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CheckoutPaymentSubscriber extends StorefrontController implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityRepository $countryRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly LanguageChannelSwitchService $languageChannelSwitchService,
        private readonly AccountService $accountService,
        private readonly AbstractLogoutRoute $logoutRoute,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'onCheckoutConfirmPageLoaded',
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onCheckoutConfirmPageLoaded(CheckoutConfirmPageLoadedEvent $event): void
    {
        $customer = $event->getSalesChannelContext()->getCustomer();
        if (!$customer) {
            return;
        }

        $salesChannelContext = $event->getSalesChannelContext();
        $shippingCountry = $customer->getActiveShippingAddress()?->getCountry();
        \assert($shippingCountry instanceof CountryEntity);
        $shippingCountryId = $shippingCountry->getId();
        $salesChannelCountryId = $event->getSalesChannelContext()->getSalesChannel()->getCountryId();
        $criteria = new Criteria([$salesChannelCountryId]);
        $salesChannelCountry = $this->countryRepository->search($criteria, $salesChannelContext->getContext())->first();

        if (!$salesChannelCountry) {
            return;
        }

        \assert($salesChannelCountry instanceof CountryEntity);
        if ($shippingCountryId === $salesChannelCountry->getId()) {
            return;
        }

        $request = $event->getRequest();

        /** @var SessionInterface $session */
        $session = $request->getSession();

        $session->set('countryId', $shippingCountryId);

        $key = $salesChannelContext->getSalesChannelId() . PhallosanConstants::SESSION_SALES_CHANNEL_COUNTRY;
        $session->set($key, $shippingCountry->getTranslated()['name']);
        $session->set(PhallosanConstants::SESSION_SALES_CHANNEL_COUNTRY_ID, $shippingCountryId);

        $response = new Response();
        $response->headers->set('X-Robots-Tag', 'noindex, follow');

        $criteria = new Criteria();
        $criteria->addAssociation('domains.salesChannel');
        $criteria->addFilter(new EqualsFilter('countryId', $shippingCountryId));

        $salesChannel = $this->salesChannelRepository->search($criteria, $salesChannelContext->getContext())->first();

        if (!$salesChannel) {
            return;
        }

        \assert($salesChannel instanceof SalesChannelEntity);
        $domain = $salesChannel->getDomains()?->first();

        if (!$domain instanceof SalesChannelDomainEntity) {
            return;
        }

        $route = $this->languageChannelSwitchService->createRedirectRoute(
            $salesChannelContext,
            $domain->getId(),
            $shippingCountryId,
            $session,
            $request->getRequestUri(),
            $request->attributes->get('_route')
        );

        $routeAvailable = $this->redirect($this->languageChannelSwitchService->domain->getUrl() . $route)->getStatusCode() === Response::HTTP_FOUND;

        if ($routeAvailable) {
            $event->getRequest()->attributes->add(['redirect_shipping_sales_channel' => $this->languageChannelSwitchService->domain->getUrl() . $route]);
            $event->getRequest()->attributes->add(['redirect_shipping_sales_channel_domain' => $domain]);
            $event->getRequest()->attributes->add(['redirect_shipping_sales_channel_customerId' => $salesChannelContext->getCustomerId()]);
            $event->getRequest()->attributes->add(['redirect_shipping_sales_channel_token' => $salesChannelContext->getToken()]);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->attributes->has('redirect_shipping_sales_channel')) {
            $response = new Response();
            $response->headers->set('X-Robots-Tag', 'noindex, follow');
            $response = $this->redirect($request->attributes->get('redirect_shipping_sales_channel'));

            if ($request->attributes->has('redirect_shipping_sales_channel_customerId')) {
                $domain = $request->attributes->get('redirect_shipping_sales_channel_domain');
                $customerId = $request->attributes->get('redirect_shipping_sales_channel_customerId');
                $token = $request->attributes->get('redirect_shipping_sales_channel_token');
                $this->transferCustomerLogin($domain, $customerId, $token);
            }

            $event->setResponse($response);
        }
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
