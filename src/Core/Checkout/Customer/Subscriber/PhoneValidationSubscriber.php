<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Checkout\Customer\Subscriber;

use PhallosanCustomizations\Core\Checkout\Customer\Validation\Constraint\CustomerPhoneNumber;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PhoneValidationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'framework.validation.address.create' => 'onAddressValidation',
            'framework.validation.address.update' => 'onAddressValidation',
            'framework.validation.customer.create' => 'onCustomerValidation',
            'framework.validation.customer.update' => 'onCustomerValidation',
        ];
    }

    public function onAddressValidation(BuildValidationEvent $event): void
    {
        $this->addPhoneValidation($event);
    }

    public function onCustomerValidation(BuildValidationEvent $event): void
    {
        $data = $event->getData();

        if ($data->has('billingAddress')) {
            return;
        }

        if ($data->has('phoneNumber')) {
            $this->addPhoneValidation($event);
        }
    }

    private function addPhoneValidation(BuildValidationEvent $event): void
    {
        $definition = $event->getDefinition();
        $data = $event->getData();

        if (!$data->has('phoneNumber')) {
            return;
        }

        $countryId = null;
        if ($data->has('countryId')) {
            $countryId = $data->get('countryId');
        } elseif ($data->has('billingAddress') && $data->get('billingAddress')->has('countryId')) {
            $countryId = $data->get('billingAddress')->get('countryId');
        } elseif ($data->has('shippingAddress') && $data->get('shippingAddress')->has('countryId')) {
            $countryId = $data->get('shippingAddress')->get('countryId');
        }

        $options = [
            'caseSensitiveCheck' => true,
        ];

        if ($countryId) {
            $options['countryId'] = $countryId;
        }

        $definition->add('phoneNumber', new CustomerPhoneNumber($options));
    }
}
