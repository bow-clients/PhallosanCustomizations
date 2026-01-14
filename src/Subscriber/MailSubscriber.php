<?php

declare(strict_types=1);

namespace PhallosanCustomizations\Subscriber;

use PhallosanCustomizations\Core\Content\OrderComment\OrderCommentCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MailSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<OrderCommentCollection> $orderCommentRepository
     */
    public function __construct(
        private readonly EntityRepository $orderCommentRepository,
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            MailBeforeValidateEvent::class => 'addDataToOrder',
        ];
    }

    public function addDataToOrder(MailBeforeValidateEvent $event): void
    {
        $templateData = $event->getTemplateData();
        $order = $templateData['order'] ?? null;
        if (!$order instanceof OrderEntity) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $order->getId()));
        $criteria->addFilter(new EqualsFilter('isPublic', true));
        $orderComments = $this->orderCommentRepository->search($criteria, $event->getContext())->getElements();
        if (empty($orderComments)) {
            return;
        }

        $order->addArrayExtension('orderComments', $orderComments);
        $templateData['order'] = $order;
        $event->setTemplateData($templateData);
    }
}
