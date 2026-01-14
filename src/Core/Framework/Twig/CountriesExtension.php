<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Framework\Twig;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CountriesExtension extends AbstractExtension
{
    private Context $context;

    public function __construct(
        private EntityRepository $salesChannelRepository,
    ) {
        $this->context = Context::createDefaultContext();
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('getCountries', [$this, 'getCountries']),
        ];
    }

    public function getCountries(string $salesChannelId, ?Context $context = null): ?CountryEntity
    {
        if ($context === null) {
            $context = $this->context;
        }
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $salesChannelId));
        $criteria->addAssociation('translations');
        $criteria->addAssociation('countries.translations');

        $salesChannel = $this->salesChannelRepository->search($criteria, $context)->getEntities()->first();

        if (!$salesChannel) {
            return null;
        }
        \assert($salesChannel instanceof SalesChannelEntity);

        return $salesChannel->getCountries()?->first();
    }
}
