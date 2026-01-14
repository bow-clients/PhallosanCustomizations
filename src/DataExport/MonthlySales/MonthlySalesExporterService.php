<?php declare(strict_types=1);

namespace PhallosanCustomizations\DataExport\MonthlySales;

use PhallosanCustomizations\DataExport\Service\CsvCreator;
use PhallosanCustomizations\DataExport\Service\ExportMailerService;
use PhallosanCustomizations\DataExport\Service\OrderEntryHelper;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class MonthlySalesExporterService
{
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly ExportMailerService $exportMailerService,
    ) {
    }

    public function getMonthlySales(): void
    {
        $now = (new \DateTime())->modify('-7 day');

        $folder = 'exports/monthlySales/';

        $year = $now->format('Y');
        $monthName = $now->format('F');
        $yearMonth = $year . '-' . $monthName;
        $fileName = $yearMonth . '-orders';

        $csvCreator = new CsvCreator($folder, $fileName);

        if (!$exportData = $this->collectOrders($yearMonth)) {
            return;
        }

        $csvCreator->addDataRow([
            'INVOICE No.',
            'DATE',
            'CLIENT',
            'DETAILS',
            'COUNTRY',
            'AMOUNT',
            'CURRENCY',
        ]);

        //Adding Order Data
        foreach ($exportData['orderData'] as $order) {
            $csvCreator->addDataRow($order);
        }

        //add spacing
        $csvCreator->addSpacingRow(2);

        //Currency Header
        $csvCreator->addDataRow([
            '',
            'AMOUNT',
            '',
            '',
            '',
            '',
            '',
        ]);

        $csvCreator->addSpacingRow(1);

        //Adding Summaries
        foreach ($exportData['orderDataByCurrency'] as $currency) {
            $csvCreator->addDataRow($currency);
        }

        $csvCreator->addSpacingRow(1);

        $csvCreator->addDataRow([
            'SUMMARY [by COUNTRY]',
            'AMOUNT',
            'CURRENCY',
            '',
            '',
            '',
            '',
        ]);

        foreach ($exportData['orderDataByCountry'] as $country) {
            $csvCreator->addDataRow($country);
        }

        $csvCreator->endWriting();

        $csvFilePath = $csvCreator->fullPath;

        if (file_exists($csvFilePath)) {
            $now = new \DateTime();

            $day = $now->format('d');

            if ($day === '01') {
                try {
                    $this->exportMailerService->sendMailWithAttachment($csvFilePath, 'Monthly Sales Export');
                } catch (\Exception $e) {
                    throw new \Exception($e->getMessage());
                    //logging
                }
            }
        }
    }

    public function collectOrders(string $yearMonth): ?array
    {
        $now = (new \DateTime())->modify('-7 day');

        $firstDayOfCurrentMonth = new \DateTime($now->format('Y-m-01 00:00:00'));

        $lastSecondOfCurrentMonth = (new \DateTime($now->format('Y-m-01 00:00:00')))->modify('+1 month')->modify('-1 second');

        //exclude to own function
        $criteria = new Criteria();
        $criteria->addAssociation('order.lineItems');
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('documents.documentType');
        $criteria->addSorting(new FieldSorting('documents.createdAt', FieldSorting::ASCENDING));
        $criteria->addAssociation('currency');
        $criteria->addAssociation('deliveries.shippingOrderAddress.country');
        $criteria->addFilter(
            new RangeFilter('createdAt', [
                RangeFilter::GTE => $firstDayOfCurrentMonth->format(format: \DateTimeInterface::ATOM),
                RangeFilter::LTE => $lastSecondOfCurrentMonth->format(format: \DateTimeInterface::ATOM),
            ]),
            new EqualsAnyFilter(
                'transactions.stateMachineState.technicalName',
                [OrderTransactionStates::STATE_PAID],
            ),
        );

        $ordersOfMonth = $this->orderRepository->search($criteria, Context::createDefaultContext());

        if ($ordersOfMonth->count() === 0) {
            return null;
        }

        $orderData = [];
        $orderDataByCurrency = [];
        $orderDataByCountry = [];

        foreach ($ordersOfMonth as $order) {
            \assert($order instanceof OrderEntity);

            $orderHelper = new OrderEntryHelper($order);

            $invoiceNumber = $orderHelper->getInvoiceNumber();
            $orderDate = $order->getOrderDateTime()->format('Y-m-d H:i:s');
            $clientName = $orderHelper->getCustomerFullName();
            $lineItemDetails = $orderHelper->getOderLineItems();
            $country = $orderHelper->getDeliveryCountry();
            $amount = $order->getAmountTotal();
            $currency = $order->getCurrency()?->getIsoCode();

            $orderData[] = [
                $invoiceNumber,
                $orderDate,
                $clientName,
                $lineItemDetails,
                $country,
                $amount,
                $currency,
            ];

            if (!isset($orderDataByCurrency[$currency])) {
                $orderDataByCurrency[$currency]['label'] = 'TOTAL SALES ' . $yearMonth . ' in ' . $currency;
                $orderDataByCurrency[$currency]['amount'] = 0;
            }

            $orderDataByCurrency[$currency]['amount'] += $amount;

            if (!isset($orderDataByCountry[$country])) {
                $orderDataByCountry[$country]['label'] = $country;
                $orderDataByCountry[$country]['amount'] = 0;
                $orderDataByCountry[$country]['currency'] = $currency;
            }

            $orderDataByCountry[$country]['amount'] += $amount;
        }

        return [
            'orderData' => $orderData,
            'orderDataByCurrency' => $orderDataByCurrency,
            'orderDataByCountry' => $orderDataByCountry,
        ];
    }
}
