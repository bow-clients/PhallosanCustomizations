<?php declare(strict_types=1);

namespace PhallosanCustomizations\Decorator\Country;

use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\Event\CountryCriteriaEvent;
use Shopware\Core\System\Country\SalesChannel\CountryRoute;
use Shopware\Core\System\Country\SalesChannel\CountryRouteResponse;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CountryRouteDecorator extends CountryRoute
{
    public function __construct(
        private readonly EntityRepository $countryEntityRepository,
        private readonly SalesChannelRepository $countryRepository,
        private readonly EventDispatcherInterface $dispatcher
    ) {
        parent::__construct($this->countryRepository, $this->dispatcher);
    }

    public static function buildName(string $id): string
    {
        return 'country-route-' . $id;
    }

    public function load(Request $request, Criteria $criteria, SalesChannelContext $context): CountryRouteResponse
    {
        $this->dispatcher->dispatch(new AddCacheTagEvent(
            self::buildName($context->getSalesChannelId()),
            self::ALL_TAG
        ));

        $criteria->addFilter(new EqualsFilter('active', true));

        $this->dispatcher->dispatch(new CountryCriteriaEvent($request, $criteria, $context));
        /** @var EntitySearchResult<CountryCollection> $result */
        $result = $this->countryEntityRepository->search($criteria, $context->getContext());

        return new CountryRouteResponse($result);
    }
}
