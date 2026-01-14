<?php declare(strict_types=1);

namespace PhallosanCustomizations\DataExport\PatentLicences;

use PhallosanCustomizations\DataExport\Service\CsvCreator;
use PhallosanCustomizations\DataExport\Service\ExportMailerService;
use PhallosanCustomizations\DataExport\Service\OrderEntryHelper;
use PhallosanCustomizations\PhallosanConstants;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class PatentLicencesExporterService
{
    public \DateTime $lastYear;

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $productRepository,
        private readonly ExportMailerService $exportMailerService,
    ) {
        $this->lastYear = (new \DateTime())->modify('-7 day');
    }

    public function getPatentLicences(): void
    {
        $folder = 'exports/patent-licences/';
        $year = $this->lastYear->format('Y');

        $products = $this->systemConfigService->get(PhallosanConstants::PLUGIN_CONFIG_PATENT_EXPORT_PRODUCT);

        if (!\is_array($products)) {
            return;
        }

        foreach ($products as $productId) {
            $productNumber = $this->fetchProduct($productId)->getProductNumber();

            $fileName = $productNumber . '-lizenz-' . $year;

            $csvCreator = new CsvCreator($folder, $fileName);

            if (!$exportData = $this->fetchOrders($productId)) {
                return;
            }

            $csvCreator->addDataRow([
                'SUMMARY (by CURRENCY)',
                'AMOUNT (NET)',
                '',
            ]);

            $csvCreator->addSpacingRow(1);

            foreach ($exportData['orderDataByCurrency'] as $currency) {
                $csvCreator->addDataRow($currency);
            }

            $csvCreator->addSpacingRow(2);

            $csvCreator->addDataRow([
                'SUMMARY (by COUNTRY)',
                'AMOUNT (NET)',
                'CURRENCY',
            ]);

            $csvCreator->addSpacingRow(1);

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
                        $this->exportMailerService->sendMailWithAttachment($csvFilePath, 'Monthly Patent Export');
                    } catch (\Exception $e) {
                        throw new \Exception($e->getMessage());
                        //logging
                    }
                }
            }
        }
    }

    private function fetchProduct(string $productId): ProductEntity
    {
        $criteria = new Criteria([$productId]);

        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->first();

        \assert($product instanceof ProductEntity);

        return $product;
    }

    private function fetchOrders(string $relevantProductId): array
    {
        $orders = $this->getOrderData($relevantProductId);

        $orderDataByCurrency = [];
        $orderDataByCountry = [];

        foreach ($orders as $order) {
            $orderHelper = new OrderEntryHelper($order);

            $currency = $order->getCurrency()?->getIsoCode();
            $country = $orderHelper->getDeliveryCountry();

            foreach ($order->getLineItems() ?? new OrderLineItemCollection([]) as $orderLineItem) {
                if ($orderLineItem->getProductId() !== $relevantProductId) {
                    continue;
                }

                $amount = $orderLineItem->getTotalPrice();

                if (!isset($orderDataByCurrency[$currency])) {
                    $orderDataByCurrency[$currency]['label'] = 'TOTAL SALES ' . $orderLineItem->getProduct()?->getProductNumber() . ' in ' . $currency;
                    $orderDataByCurrency[$currency]['amount'] = 0;
                    $orderDataByCurrency[$currency][] = '';
                }
                $orderDataByCurrency[$currency]['amount'] += $amount;

                if (!isset($orderDataByCountry[$country])) {
                    $orderDataByCountry[$country]['label'] = $country;
                    $orderDataByCountry[$country]['amount'] = 0;
                    $orderDataByCountry[$country]['currency'] = $currency;
                }
                $orderDataByCountry[$country]['amount'] += $amount;
            }
        }

        return [
            'orderDataByCurrency' => $orderDataByCurrency,
            'orderDataByCountry' => $orderDataByCountry,
        ];
    }

    private function getOrderData(string $relevantProduct): OrderCollection
    {
        $firstDayOfLastYear = $this->lastYear->format('Y-01-01 00:00:00');

        $lastSecondOfLastYear = $this->lastYear->format('Y-12-31 23:59:59');

        $criteria = new Criteria();
        $criteria->addAssociation('order.lineItems');
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('deliveries.shippingOrderAddress.country');
        $criteria->addFilter(
            new RangeFilter('createdAt', [
                RangeFilter::GTE => $firstDayOfLastYear,
                RangeFilter::LTE => $lastSecondOfLastYear,
            ]),
            new EqualsAnyFilter(
                'transactions.stateMachineState.technicalName',
                [OrderTransactionStates::STATE_PAID],
            ),
            new EqualsAnyFilter(
                'lineItems.productId',
                [$relevantProduct],
            ),
        );

        $ordersOfYear = $this->orderRepository->search($criteria, Context::createDefaultContext());

        if ($ordersOfYear->count() === 0) {
            return new OrderCollection();
        }

        $orders = $ordersOfYear->getEntities();
        \assert($orders instanceof OrderCollection);

        return $orders;
    }
}
