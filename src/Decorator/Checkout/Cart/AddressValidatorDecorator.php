<?php declare(strict_types=1);

namespace PhallosanCustomizations\Decorator\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Address\Error\BillingAddressCountryRegionMissingError;
use Shopware\Core\Checkout\Cart\Address\Error\BillingAddressSalutationMissingError;
use Shopware\Core\Checkout\Cart\Address\Error\ShippingAddressBlockedError;
use Shopware\Core\Checkout\Cart\Address\Error\ShippingAddressCountryRegionMissingError;
use Shopware\Core\Checkout\Cart\Address\Error\ShippingAddressSalutationMissingError;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Content\Product\State;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Service\ResetInterface;

class AddressValidatorDecorator implements CartValidatorInterface, ResetInterface
{
    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        $country = $context->getShippingLocation()->getCountry();
        $customer = $context->getCustomer();
        $validateShipping = $cart->getLineItems()->count() === 0
            || $cart->getLineItems()->hasLineItemWithState(State::IS_PHYSICAL);

        if (!$country->getActive() && $validateShipping) {
            $errors->add(new ShippingAddressBlockedError((string) $country->getTranslation('name')));

            return;
        }

        if (!$country->getShippingAvailable() && $validateShipping) {
            $errors->add(new ShippingAddressBlockedError((string) $country->getTranslation('name')));

            return;
        }

        if (!$this->isSalesChannelCountry($country->getId(), $context) && $validateShipping) {
            $errors->add(new ShippingAddressBlockedError((string) $country->getTranslation('name')));

            return;
        }

        if ($customer === null) {
            return;
        }

        if ($customer->getActiveBillingAddress() === null || $customer->getActiveShippingAddress() === null) {
            // No need to add salutation-specific errors in this case
            return;
        }

        if (!$customer->getActiveBillingAddress()->getSalutationId()) {
            $errors->add(new BillingAddressSalutationMissingError($customer->getActiveBillingAddress()));

            return;
        }

        if (!$customer->getActiveShippingAddress()->getSalutationId() && $validateShipping) {
            $errors->add(new ShippingAddressSalutationMissingError($customer->getActiveShippingAddress()));
        }

        if ($customer->getActiveBillingAddress()->getCountry()?->getForceStateInRegistration()) {
            if (!$customer->getActiveBillingAddress()->getCountryState()) {
                $errors->add(new BillingAddressCountryRegionMissingError($customer->getActiveBillingAddress()));
            }
        }

        if ($customer->getActiveShippingAddress()->getCountry()?->getForceStateInRegistration()) {
            if (!$customer->getActiveShippingAddress()->getCountryState()) {
                $errors->add(new ShippingAddressCountryRegionMissingError($customer->getActiveShippingAddress()));
            }
        }
    }

    public function reset(): void
    {
    }

    private function isSalesChannelCountry(string $countryId, SalesChannelContext $context): bool
    {
        return true;
    }
}
