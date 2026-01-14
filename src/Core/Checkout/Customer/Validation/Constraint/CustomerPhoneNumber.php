<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Checkout\Customer\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

class CustomerPhoneNumber extends Constraint
{
    public const PHONE_NUMBER_INVALID_ERROR = 'PHONE_NUMBER_INVALID_ERROR';

    protected const ERROR_NAMES = [
        self::PHONE_NUMBER_INVALID_ERROR => 'PHONE_NUMBER_INVALID_ERROR',
    ];

    public string $message = 'The phone number {{ phoneNumber }} is not valid for country {{ country }}.';

    public bool $caseSensitiveCheck = false;

    public ?string $countryId = null;

    public function validatedBy(): string
    {
        return CustomerPhoneNumberValidator::class;
    }
}
