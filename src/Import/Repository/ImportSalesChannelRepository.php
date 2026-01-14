<?php declare(strict_types=1);

namespace PhallosanCustomizations\Import\Repository;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class ImportSalesChannelRepository
{
    public function __construct(
        private readonly EntityRepository $salesChannelRepository,
    ) {
    }

    public function getSalesChannelByMainCountryID(string $mainCountryId): SalesChannelEntity
    {
        $criteria = new Criteria();
        $criteria->addAssociation('currency');
        $criteria->addFilter(new EqualsFilter('countryId', $mainCountryId));

        $result = $this->salesChannelRepository->search($criteria, Context::createDefaultContext())->first();
        if (!$result instanceof SalesChannelEntity) {
            throw new \RuntimeException("Sales Channel with main country ID '$mainCountryId' not found.");
        }

        return $result;
    }
}
