<?php declare(strict_types=1);

namespace PhallosanCustomizations\DataExport\GoodwillDeliveries;

use PhallosanCustomizations\DataExport\Service\CsvCreator;
use PhallosanCustomizations\DataExport\Service\ExportMailerService;
use PhallosanCustomizations\DataExport\Service\OrderEntryHelper;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class MonthlyGoodWillDeliveriesService
{
    private \DateTime $firstDayOfLastMonth;

    private \DateTime $lastSecondOfLastMonth;

    private string $yearMonth;

    public function __construct(
        public readonly EntityRepository $orderRepository,
        private readonly ExportMailerService $exportMailerService,
    ) {
        $lastMonthDate = (new \DateTime())->modify('-1 month');

        $this->firstDayOfLastMonth = new \DateTime($lastMonthDate->format('Y-m-01 00:00:00'));

        $this->lastSecondOfLastMonth = (new \DateTime($lastMonthDate->format('Y-m-01 00:00:00')))->modify('+1 month')->modify('-1 second');

        $year = $lastMonthDate->format('Y');
        $month = $lastMonthDate->format('F');
        $this->yearMonth = $year . '-' . $month;
    }

    public function getMonthlyGoodWillDeliveries(): void
    {
        $fileName = $this->yearMonth . '-kulanz';
        $folderName = 'exports/monthlyGoodWillDeliveries/';

        if (!$orderData = $this->collectOrders()) {
            return;
        }

        $csvCreator = new CsvCreator($folderName, $fileName);

        $csvCreator->addSpacingRow(1);

        $csvCreator->addDataRow($this->getDescriptionLine());

        $csvCreator->addSpacingRow(2);

        $csvCreator->addDataRow($this->getHeadLine());

        $csvCreator->addSpacingRow(1);

        foreach ($orderData['orders'] as $order) {
            $csvCreator->addDataRow($order);
        }

        $csvCreator->addSpacingRow(3);

        $csvCreator->addDataRow([
            '',
            '',
            '',
            'Verkaufswert Waren:',
            '',
            $orderData['summary']['summedSales'],
            'EUR',
        ]);

        $csvCreator->addSpacingRow(1);

        $csvCreator->addDataRow([
            '',
            '',
            '',
            'Verkaufswert Versand:',
            '',
            $orderData['summary']['summedShipping'],
            'EUR',
        ]);

        $csvCreator->addSpacingRow(2);

        $csvCreator->addDataRow([
            '',
            '',
            '',
            'Gesamt Verkaufswert:',
            '',
            $orderData['summary']['summedTotal'],
            'EUR',
        ]);

        $csvCreator->endWriting();

        $csvFilePath = $csvCreator->fullPath;

        if (file_exists($csvFilePath)) {
            $this->exportMailerService->sendMailWithAttachment($csvFilePath, 'Monthly good will Export');
        }
    }

    public function getHeadLine(): array
    {
        return [
            'Bestellnummer',
            'Bestelldatum',
            'Kunden Name',
            'KulanzProdukte',
            'Lieferland',
            'Verkaufswert',
            'Währung',
        ];
    }

    public function getDescriptionLine(): array
    {
        return [
            'Bestellungen die aus Kulanz zum Betrag von 0,00 EUR versendet wurden: ' . $this->yearMonth,
            '',
            '',
            '',
            '',
            '',
            '',
        ];
    }

    public function collectOrders(): ?array
    {
        $criteria = new Criteria();
        $criteria->addAssociation('order.lineItems.');
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('lineItems.product.price');
        $criteria->addAssociation('documents.documentType');
        $criteria->addSorting(new FieldSorting('documents.createdAt', FieldSorting::ASCENDING));
        $criteria->addAssociation('currency');
        $criteria->addAssociation('deliveries.shippingOrderAddress.country');
        $criteria->addFilter(
            new RangeFilter('createdAt', [
                RangeFilter::GTE => $this->firstDayOfLastMonth->format('Y-m-d H:i:s'),
                RangeFilter::LTE => $this->lastSecondOfLastMonth->format('Y-m-d H:i:s'),
            ]),
            new EqualsAnyFilter(
                'deliveries.stateMachineState.technicalName',
                [OrderDeliveryStates::STATE_SHIPPED],
            ),
            new RangeFilter('positionPrice', [
                RangeFilter::GTE => 0,
                RangeFilter::LTE => 5,
            ])
        );

        $goodWillDeliveries = $this->orderRepository->search($criteria, Context::createDefaultContext());

        if ($goodWillDeliveries->count() === 0) {
            return null;
        }

        $orderData = [
            'orders' => [],
            'summary' => [
                'summedSales' => 0,
                'summedShipping' => 0,
                'summedTotal' => 0,
            ],
        ];

        foreach ($goodWillDeliveries as $order) {
            \assert($order instanceof OrderEntity);

            $orderHelper = new OrderEntryHelper($order);

            $orderNumber = $order->getOrderNumber();
            $orderDate = $order->getOrderDate()->format('Y-m-d H:i:s');
            $clientName = $orderHelper->getCustomerFullName();
            $lineItemDetails = $orderHelper->getOderLineItems();
            $country = $orderHelper->getDeliveryCountry();
            $amount = $this->getOriginalPositionPrices($order);
            $currency = $order->getCurrency()?->getIsoCode();

            $orderData['orders'][] = [
                $orderNumber,
                $orderDate,
                $clientName,
                $lineItemDetails,
                $country,
                $amount,
                $currency,
            ];

            $orderData['summary']['summedSales'] += $amount;
            $orderData['summary']['summedShipping'] = $order->getDeliveries()?->first()?->getShippingCosts()->getTotalPrice();
            $orderData['summary']['summedTotal'] = $orderData['summary']['summedSales'] + $orderData['summary']['summedShipping'];
        }

        return $orderData;
    }

    public function getOriginalPositionPrices(OrderEntity $order): float
    {
        $lineItems = $order->getLineItems();

        \assert($lineItems instanceof OrderLineItemCollection);
        foreach ($lineItems as $lineItem) {
            $productPrice = $lineItem->getProduct()?->getPrice()?->getCurrencyPrice('B7D2554B0CE847CD82F3AC9BD1C0DFCA')?->getGross();
        }

        return $productPrice ?? 0.0;
    }
}
