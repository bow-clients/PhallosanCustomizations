<?php declare(strict_types=1);

namespace PhallosanCustomizations\DataExport\Service;

use Shopware\Core\Checkout\Order\OrderEntity;

class OrderEntryHelper
{
    public function __construct(
        public OrderEntity $order
    ) {
    }

    public function getOderLineItems(): string
    {
        if (!$this->order->getLineItems()) {
            return '';
        }

        $orderLineItemString = '';
        foreach ($this->order->getLineItems() as $orderLineItem) {
            if (\array_key_exists('productNumber', $orderLineItem->getPayload() ?? [])) {
                $orderLineItemString .= \sprintf('%sx no. %s -', $orderLineItem->getQuantity(), $orderLineItem->getPayload()['productNumber']);
            }
        }

        return $orderLineItemString;
    }

    public function getDeliveryCountry(): string
    {
        $delivery = $this->order->getDeliveries()?->first();
        if (!$delivery) {
            return '';
        }

        $shippingAddress = $delivery->getShippingOrderAddress();

        return $shippingAddress?->getCountry()?->getName() ?? '';
    }

    public function getInvoiceNumber(): string
    {
        return $this->order->getDocuments()?->filter(
            fn ($document) => $document->getDocumentType()->getTechnicalName() === 'invoice'
        )?->first()?->getDocumentNumber() ?? '';
    }

    public function getInvoiceDate(): ?\DateTimeInterface
    {
        return $this->order->getDocuments()?->filter(
            fn ($document) => $document->getDocumentType()->getTechnicalName() === 'invoice'
        )?->first()?->getCreatedAt();
    }

    public function getCustomerFullName(): string
    {
        if (!$this->order->getOrderCustomer()) {
            return '';
        }

        return $this->order->getOrderCustomer()->getFirstName() . ' ' . $this->order->getOrderCustomer()->getLastName();
    }
}
