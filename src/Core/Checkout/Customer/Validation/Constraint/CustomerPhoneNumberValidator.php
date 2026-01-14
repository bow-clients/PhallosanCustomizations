<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Checkout\Customer\Validation\Constraint;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Country\CountryEntity;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class CustomerPhoneNumberValidator extends ConstraintValidator
{
    public function __construct(
        private readonly EntityRepository $countryRepository
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CustomerPhoneNumber) {
            throw new UnexpectedTypeException($constraint, CustomerPhoneNumber::class);
        }

        if (!\is_scalar($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $phoneNumber = (string) $value;

        if ($constraint->countryId === null) {
            return;
        }

        $country = $this->getCountry($constraint->countryId);
        if (!$country) {
            return;
        }

        $countryName = (string) ($country->getTranslation('name') ?? $country->getName() ?? '');

        $customFields = $country->getTranslation('customFields');
        if (!\is_array($customFields)) {
            return;
        }

        $isActive = (bool) ($customFields['custom_phone_validation_active'] ?? false);

        if (!$isActive) {
            return;
        }

        $pattern = $customFields['custom_phone_validation_regex'] ?? null;
        if ($pattern === null || $pattern === '') {
            return;
        }

        $patterns = explode('|', $pattern);
        $isFound = false;
        $caseSensitive = $constraint->caseSensitiveCheck ?? false;
        $flags = $caseSensitive ? '' : '/i';

        try {
            foreach ($patterns as $pattern) {
                if (preg_match("/^{$pattern}$/" . $flags, $phoneNumber) === 1) {
                    $isFound = true;

                    break;
                }
            }
        } catch (\Exception $e) {
            return;
        }


        if ($isFound === true) {
            return;
        }

        $this->context
            ->buildViolation($constraint->message)
            ->setParameter('%phoneNumber%', (string) $phoneNumber)
            ->setParameter('%country%', (string) $countryName)
            ->setCode(CustomerPhoneNumber::PHONE_NUMBER_INVALID_ERROR)
            ->addViolation();
    }

    private function getCountry(string $countryId): ?CountryEntity
    {
        $criteria = new Criteria([$countryId]);
        $criteria->addAssociation('translations');

        $context = Context::createDefaultContext();

        /** @var CountryEntity|null $country */
        $country = $this->countryRepository->search($criteria, $context)->first();

        return $country;
    }
}
