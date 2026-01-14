<?php declare(strict_types=1);

namespace PhallosanCustomizations\Import\Repository;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Country\CountryEntity;

class ImportCountryRepository
{
    public function __construct(
        private readonly EntityRepository $countryRepository,
    ) {
    }

    public function getCountryByIso2(string $iso): CountryEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', $iso));

        $result = $this->countryRepository->search($criteria, Context::createDefaultContext())->first();
        if (!$result instanceof CountryEntity) {
            throw new \RuntimeException("Country with ISO 2 code '$iso' not found.");
        }

        return $result;
    }
}
