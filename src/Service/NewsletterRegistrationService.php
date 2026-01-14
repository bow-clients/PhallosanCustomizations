<?php declare(strict_types=1);

namespace PhallosanCustomizations\Service;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Newsletter\SalesChannel\AbstractNewsletterSubscribeRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\RequestStack;

class NewsletterRegistrationService
{
    public function __construct(
        private readonly AbstractNewsletterSubscribeRoute $newsletterSubscribeRoute,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function subscribeFromCustomer(CustomerEntity $customer, SalesChannelContext $context): void
    {
        $dataBag = new RequestDataBag();
        $dataBag->set('option', NewsletterSubscribeRoute::OPTION_SUBSCRIBE);
        $dataBag->set('storefrontUrl', $this->requestStack->getCurrentRequest()?->attributes->get(RequestTransformer::STOREFRONT_URL));

        $this->newsletterSubscribeRoute->subscribe($this->hydrateFromCustomer($dataBag, $customer), $context, false);
    }

    private function hydrateFromCustomer(RequestDataBag $dataBag, CustomerEntity $customer): RequestDataBag
    {
        $dataBag->set('email', $customer->getEmail());
        $dataBag->set('salutationId', $customer->getSalutationId());
        $dataBag->set('title', $customer->getTitle());
        $dataBag->set('firstName', $customer->getFirstName());
        $dataBag->set('lastName', $customer->getLastName());
        $dataBag->set(
            'zipCode',
            $customer->getDefaultShippingAddress() ? $customer->getDefaultShippingAddress()->getZipcode() : ''
        );
        $dataBag->set(
            'city',
            $customer->getDefaultShippingAddress() ? $customer->getDefaultShippingAddress()->getCity() : ''
        );
        $dataBag->set(
            'street',
            $customer->getDefaultShippingAddress() ? $customer->getDefaultShippingAddress()->getStreet() : ''
        );

        return $dataBag;
    }
}
