<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Checkout\Customer\Validation;

use PhallosanCustomizations\Core\Checkout\Customer\Validation\Constraint\CustomerPhoneNumber;
use Shopware\Core\Checkout\Customer\Validation\AddressValidationFactory;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AddressValidationDecorator extends AddressValidationFactory
{
    public function __construct(
        private readonly AddressValidationFactory $decorated
    ) {
    }

    public function create(SalesChannelContext $context): DataValidationDefinition
    {
        $definition = $this->decorated->create($context);

        $definition->add('phoneNumber', new CustomerPhoneNumber());

        return $definition;
    }

    public function update(SalesChannelContext $context): DataValidationDefinition
    {
        $definition = $this->decorated->update($context);

        $definition->add('phoneNumber', new CustomerPhoneNumber());

        return $definition;
    }
}
