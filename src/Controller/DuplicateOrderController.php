<?php declare(strict_types=1);

namespace PhallosanCustomizations\Controller;

use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class DuplicateOrderController
{
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly OrderConverter $orderConverter,
        private readonly NumberRangeValueGeneratorInterface $numberRangesValueGenerator,
        private readonly InitialStateIdLoader $initialStateIdLoader,
    ) {
    }

    #[Route(path: '/api/phallosan/duplicate-order/{orderId}', name: 'api.phallosan.duplicate-order', methods: ['POST'])]
    public function duplicate(string $orderId, Context $context): JsonResponse
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociations([
            'lineItems',
            'deliveries',
            'deliveries.shippingOrderAddress',
            'deliveries.shippingOrderAddress.country',
            'deliveries.shippingMethod',
            'transactions',
            'transactions.paymentMethod',
            'transactions.stateMachineState',
            'currency',
            'addresses',
            'addresses.country',
            'billingAddress',
            'billingAddress.country',
            'shippingCosts',
            'language',
            'locale',
            'salesChannel',
            'orderCustomer',
            'orderCustomer.customer',
            'orderCustomer.salutation',
        ]);

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $context)->first();

        if (!$order) {
            return new JsonResponse(['error' => 'Order not found'], 404);
        }

        $paymentMethodId = $order->getTransactions()?->first()?->getPaymentMethodId();

        $salesChannelContext = $this->orderConverter->assembleSalesChannelContext(
            $order,
            $context
        );

        // convert the existing order to a cart
        $cart = $this->orderConverter->convertToCart($order, $context);

        $conversionContext = new OrderConversionContext();
        $conversionContext->setIncludeOrderDate(false);

        $orderData = $this->orderConverter->convertToOrder($cart, $salesChannelContext, $conversionContext);


        // adjust the order data (new ids needs to be inserted)
        $newOrderId = Uuid::randomHex();
        $newOrderNumber = $this->numberRangesValueGenerator->getValue(
            'order',
            $context,
            $order->getSalesChannelId()
        );
        $orderData['id'] = $newOrderId;
        $orderData['orderNumber'] = $newOrderNumber;
        $orderData['affiliateCode'] = '';
        $orderData['deepLinkCode'] = Random::getBase64UrlString(32);
        $orderData['orderDateTime'] = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $orderData['transactions'] = [];

        $transaction = [
            'paymentMethodId' => $paymentMethodId,
            'stateId' => $this->initialStateIdLoader->get(OrderTransactionStates::STATE_MACHINE),
            'amount' => $order->getShippingCosts(),
            'createdAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];
        $orderData['transactions'][] = $transaction;

        foreach ($orderData['lineItems'] as $lineItemIndex => $lineItem) {
            $orderData['lineItems'][$lineItemIndex]['id'] = Uuid::randomHex();
        }

        $currentBillingId = $orderData['billingAddressId'];

        foreach ($orderData['addresses'] as $addressIndex => $addressData) {
            $newUuid = Uuid::randomHex();
            if ($addressData['id'] === $currentBillingId) {
                $orderData['billingAddressId'] = $newUuid;
            }
            $orderData['addresses'][$addressIndex]['id'] = $newUuid;
        }

        /**
         * add devliery address
         */
        $firstDelivery = $order->getDeliveries()?->first();
        $deliveryAddressEntity = $firstDelivery?->getShippingOrderAddress();

        if ($deliveryAddressEntity) {
            $deliveryAddressId = Uuid::randomHex();
            $deliveryAddress = $this->convertAddressToArray($deliveryAddressEntity);
            $deliveryAddress['id'] = $deliveryAddressId;

            $orderData['addresses'][] = $deliveryAddress;

            $delivery = [
                'stateId' => $this->initialStateIdLoader->get(OrderDeliveryStates::STATE_MACHINE),
                'shippingMethodId' => $firstDelivery->getShippingMethodId(),
                'shippingCosts' => $firstDelivery->getShippingCosts(),
                'shippingDateEarliest' => date(\DATE_ISO8601),
                'shippingDateLatest' => date(\DATE_ISO8601),
                'shippingOrderAddress' => $deliveryAddress,
            ];

            $orderData['deliveries'][] = $delivery;
        }


        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($orderData): void {
            $this->orderRepository->create([$orderData], $context);
        });

        return new JsonResponse(['newOrderId' => $newOrderId]);
    }

    protected function convertAddressToArray(OrderAddressEntity $addressEntity): array
    {
        return [
            'id' => Uuid::randomHex(),
            'salutationId' => $addressEntity->getSalutationId(),
            'firstName' => $addressEntity->getFirstName(),
            'lastName' => $addressEntity->getLastName(),
            'title' => $addressEntity->getTitle(),
            'street' => $addressEntity->getStreet(),
            'city' => $addressEntity->getCity(),
            'zipcode' => $addressEntity->getZipcode(),
            'company' => $addressEntity->getCompany(),
            'department' => $addressEntity->getDepartment(),
            'vatId' => $addressEntity->getVatId(),
            'phoneNumber' => $addressEntity->getPhoneNumber(),
            'additionalAddressLine1' => $addressEntity->getAdditionalAddressLine1(),
            'additionalAddressLine2' => $addressEntity->getAdditionalAddressLine2(),
            'countryId' => $addressEntity->getCountryId(),
            'countryStateId' => $addressEntity->getCountryStateId(),
            'customFields' => $addressEntity->getCustomFields(),
        ];
    }
}
