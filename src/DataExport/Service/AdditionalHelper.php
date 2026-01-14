<?php declare(strict_types=1);

namespace PhallosanCustomizations\DataExport\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;

class AdditionalHelper
{
    public function __construct(
        private readonly EntityRepository $countryRepository,
    ) {
    }

    public function getEuCountries(bool $isEu): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('isEu', $isEu));
        //Exclude Swiss and Liechtenstein
        $criteria->addFilter(new NotFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('iso', 'CH'),
                new EqualsFilter('iso', 'LI'),
            ]
        ));

        $euCountries = $this->countryRepository->search($criteria, Context::createDefaultContext());

        return array_keys($euCountries->getElements());
    }

    public function getWorldCountries(bool $world): array
    {
        $criteria = new Criteria();
        //Exclude Swiss and Liechtenstein
        $criteria->addFilter(new NotFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('iso', 'CH'),
                new EqualsFilter('iso', 'LI'),
            ]
        ));
        if ($world) {
            $criteria->addFilter(new NotFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new EqualsFilter('iso', 'DE'),
                ]
            ));
        } else {
            $criteria->addFilter(new EqualsFilter('iso', 'DE'));
        }

        $worldCountries = $this->countryRepository->search($criteria, Context::createDefaultContext());

        return array_keys($worldCountries->getElements());
    }
}
