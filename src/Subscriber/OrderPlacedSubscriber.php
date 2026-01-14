<?php declare(strict_types=1);

namespace PhallosanCustomizations\Subscriber;

use Doctrine\DBAL\Connection;
use PhallosanCustomizations\PhallosanConstants;
use PhallosanCustomizations\Service\NewsletterRegistrationService;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class OrderPlacedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly Connection $connection,
        private readonly RequestStack $requestStack,
        private readonly NewsletterRegistrationService $newsletterRegistrationService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'orderPlaced',
        ];
    }

    public function orderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();
        $request = $this->requestStack->getCurrentRequest();
        $customer = $salesChannelContext->getCustomer();

        if ($request && $request->get('newsletterOptIn') && $customer instanceof CustomerEntity) {
            $this->newsletterRegistrationService->subscribeFromCustomer($customer, $salesChannelContext);
        }

        $order = $event->getOrder();

        /** @var OrderLineItemCollection $lineItems */
        $lineItems = $order->getLineItems();

        if (!$lineItems->count()) {
            return;
        }

        $hsCodePrefixCountry = $this->systemConfigService->get(PhallosanConstants::PLUGIN_CONFIG_US_HS_CODE_COUNTRY);
        $hsCodeSuffix = $this->systemConfigService->get(PhallosanConstants::PLUGIN_CONFIG_US_HS_CODE_SUFFIX);

        foreach ($lineItems as $lineItem) {
            $payload = $lineItem->getPayload();
            if (!isset($payload['customFields'][PhallosanConstants::CUSTOM_FIELD_HS_CODE_US])) {
                continue;
            }

            if ($order->getDeliveries()?->first()?->getShippingOrderAddress()?->getCountryId() === $hsCodePrefixCountry) {
                $payload['customFields'][PhallosanConstants::CUSTOM_FIELD_HS_CODE_US] .= $hsCodeSuffix;

                $this->connection->fetchOne(
                    'UPDATE order_line_item SET payload = :payload
                    WHERE id = :id',
                    ['payload' => json_encode($payload), 'id' => hex2bin($lineItem->getId())],
                );
            }
        }
    }
}
