<?php declare(strict_types=1);

namespace PhallosanCustomizations\DataExport\TaxOffice;

use PhallosanCustomizations\DataExport\Service\AdditionalHelper;
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
use Shopware\Core\System\SystemConfig\SystemConfigService;

class MonthlySalesExportService
{
    public string $yearMonth;

    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly AdditionalHelper $additionalHelper,
        private readonly ExportMailerService $exportMailerService,
        private readonly SystemConfigService $systemConfigService,
    ) {
        $now = new \DateTime();
        $year = $now->format('Y');
        $month = $now->format('m');
        $this->yearMonth = $year . '-' . $month;
    }

    public function getMonthlySales(string $region, bool $eu): void
    {
        $fileName = 'monthly-' . $region . '-' . $this->yearMonth;

        if (!$orderData = $this->collectOrders($eu)) {
            return;
        }

        $csvCreator = new CsvCreator($this->getFolderName(), $fileName);

        $csvCreator->addDataRow($this->getSalesHeadLine());

        //Adding Order Data
        foreach ($orderData as $order) {
            $csvCreator->addDataRow($order);
        }

        $csvCreator->endWriting();

        $csvFilePath = $csvCreator->fullPath;

        if (file_exists($csvFilePath)) {
            $this->exportMailerService->sendMailWithAttachment($csvFilePath, 'Monthly Sales Export', false);
        }
    }

    public function getMonthlyCancellations(string $region, bool $world): void
    {
        $fileName = 'negative-booking-' . $region . '-' . $this->yearMonth;

        if (!$orderData = $this->collectCancellations($world)) {
            return;
        }

        $csvCreator = new CsvCreator($this->getCancellationsFolderName(), $fileName);

        $csvCreator->addDataRow($this->getSalesHeadLine());

        //Adding Order Data
        foreach ($orderData as $order) {
            $csvCreator->addDataRow($order);
        }

        $csvCreator->endWriting();

        $csvFilePath = $csvCreator->fullPath;

        if (file_exists($csvFilePath)) {
            $this->exportMailerService->sendMailWithAttachment($csvFilePath, 'Monthly negative booking Export', false);
        }
    }

    public function getFolderName(): string
    {
        return 'exports/tax-office/monthly-sales/';
    }

    public function getCancellationsFolderName(): string
    {
        return 'exports/tax-office/monthly-cancellations';
    }

    public function getSalesHeadLine(): array
    {
        return [
            'Name',
            'Country',
            'Invoice number',
            'Invoice date',
            'VAT',
            'Order Total',
        ];
    }

    public function getCancellationsHeadLine(): array
    {
        return [
            'Name',
            'Country',
            'Invoice date',
            'Order Date',
            'Negative Booking Date',
            'Invoice number',
            'TAX',
            'Total',
            'Total Format',
        ];
    }

    public function collectOrders(bool $isEu = true): ?array
    {
        $lastMonthDate = (new \DateTime())->modify('-1 month');

        $firstDayOfLastMonth = new \DateTime($lastMonthDate->format('Y-m-01 00:00:00'));

        $lastSecondOfLastMonth = (new \DateTime($lastMonthDate->format('Y-m-01 00:00:00')))->modify('+1 month')->modify('-1 second');

        $criteria = new Criteria();
        $criteria->addAssociation('order.lineItems');
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('documents.documentType');
        $criteria->addSorting(new FieldSorting('documents.createdAt', FieldSorting::ASCENDING));
        $criteria->addAssociation('currency');
        $criteria->addAssociation('deliveries.shippingOrderAddress.country');
        $criteria->addFilter(
            new RangeFilter('createdAt', [
                RangeFilter::GTE => $firstDayOfLastMonth->format('Y-m-d H:i:s'),
                RangeFilter::LTE => $lastSecondOfLastMonth->format('Y-m-d H:i:s'),
            ]),
            new EqualsAnyFilter(
                'transactions.stateMachineState.technicalName',
                [OrderTransactionStates::STATE_PAID],
            ),
        );

        $ordersOfMonth = $this->orderRepository->search($criteria, Context::createDefaultContext());

        $filteredOrders = $ordersOfMonth->filter(function (OrderEntity $order) use ($isEu) {
            $euIso = $this->additionalHelper->getEuCountries($isEu);
            $countryIso = $order->getDeliveries()?->first()?->getShippingOrderAddress()?->getCountry()?->getId();

            if (\in_array($countryIso, $euIso, true)) {
                return true;
            }
        });

        if ($ordersOfMonth->count() === 0) {
            return null;
        }

        $orderData = [];

        foreach ($filteredOrders as $order) {
            \assert($order instanceof OrderEntity);

            $orderHelper = new OrderEntryHelper($order);

            $invoiceNumber = $orderHelper->getInvoiceNumber();
            $invoiceDate = $orderHelper->getInvoiceDate()?->format('Y-m-d') ?? '';
            $clientName = $orderHelper->getCustomerFullName();
            $country = $orderHelper->getDeliveryCountry();
            $amount = $order->getAmountTotal();
            $currency = $order->getCurrency()?->getIsoCode();

            $orderData[] = [
                $clientName,
                $country,
                $invoiceNumber,
                $invoiceDate,
                '',
                $amount . ' ' . $currency,
            ];
        }

        return $orderData;
    }

    public function getLastRuntime(string $key): string
    {
        $lastExportDate = $this->systemConfigService->get($key) ?? '';

        if (\is_array($lastExportDate)) {
            return '';
        }

        $lastExportDate = (string) $lastExportDate;

        if (!$lastExportDate || !strtotime($lastExportDate)) {
            return '';
        }

        return (new \DateTime($lastExportDate))->format('m');
    }

    private function collectCancellations(bool $world): ?array
    {
        $lastMonthDate = (new \DateTime())->modify('-2 month');

        $firstDayOfLastMonth = new \DateTime($lastMonthDate->format('Y-m-01 00:00:00'));

        $lastSecondOfLastMonth = (new \DateTime($lastMonthDate->format('Y-m-01 00:00:00')))->modify('+1 month')->modify('-1 second');

        $criteria = new Criteria();
        $criteria->addAssociation('order.lineItems');
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('documents.documentType');
        $criteria->addSorting(new FieldSorting('documents.createdAt', FieldSorting::ASCENDING));
        $criteria->addAssociation('currency');
        $criteria->addAssociation('deliveries.shippingOrderAddress.country');
        $criteria->addFilter(
            new RangeFilter('createdAt', [
                RangeFilter::GTE => $firstDayOfLastMonth->format('Y-m-d H:i:s'),
                RangeFilter::LTE => $lastSecondOfLastMonth->format('Y-m-d H:i:s'),
            ]),
            new EqualsAnyFilter(
                'stateMachineState.technicalName',
                [OrderTransactionStates::STATE_CANCELLED],
            )
        );

        $ordersOfMonth = $this->orderRepository->search($criteria, Context::createDefaultContext());

        $filteredOrders = $ordersOfMonth->filter(function (OrderEntity $order) use ($world) {
            $iso = $this->additionalHelper->getWorldCountries($world);
            $countryIso = $order->getDeliveries()?->first()?->getShippingOrderAddress()?->getCountry()?->getId();

            if (\in_array($countryIso, $iso, true)) {
                return true;
            }
        });

        if ($ordersOfMonth->count() === 0) {
            return null;
        }

        $orderData = [];

        foreach ($filteredOrders as $order) {
            \assert($order instanceof OrderEntity);

            $orderHelper = new OrderEntryHelper($order);

            $invoiceNumber = $orderHelper->getInvoiceNumber();
            $invoiceDate = $orderHelper->getInvoiceDate()?->format('Y-m-d') ?? '';
            $clientName = $orderHelper->getCustomerFullName();
            $country = $orderHelper->getDeliveryCountry();
            $amount = $order->getAmountTotal();
            $currency = $order->getCurrency()?->getIsoCode();

            $orderData[] = [
                $clientName,
                $country,
                $invoiceNumber,
                $invoiceDate,
                '',
                $amount . ' ' . $currency,
            ];
        }

        return $orderData;
    }
}
