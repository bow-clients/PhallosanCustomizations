<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Framework\Twig;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class LanguageSwitchExtension extends AbstractExtension
{
    public function __construct(
        private readonly EntityRepository $domainRepository
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('getLanguageSwitch', [$this, 'getLanguageSwitch']),
        ];
    }

    public function getLanguageSwitch(SalesChannelContext $salesChannelContext): array
    {
        $criteria = new Criteria();
        $criteria->addAssociation('language.translationCode.code');
        $criteria->addAssociation('salesChannel.country');
        $criteria->addAssociation('salesChannel.countries');
        $criteria->addAssociation('salesChannel.type');

        $criteria->addFilter(new EqualsFilter('salesChannel.active', true));
        $criteria->addFilter(new EqualsFilter('salesChannel.type.name', 'Storefront'));
        //Remove This to enable multiple Languages for one SalesChannel
        $criteria->addGroupField(new FieldGrouping('salesChannelId'));

        $languageDomains = $this->domainRepository->search($criteria, $salesChannelContext->getContext())->getElements();

        $countries = [];

        /** @var SalesChannelDomainEntity $languageDomain */
        foreach ($languageDomains as $languageDomain) {
            $country = $languageDomain->getSalesChannel()?->getCountry();

            $id = Uuid::randomHex();
            \assert($country instanceof CountryEntity);
            $countries[$id]['country'] = $country;
            $countries[$id]['salesChannel'] = $languageDomain->getSalesChannel();
            $countries[$id]['language'] = $languageDomain->getLanguage();
            $countries[$id]['id'] = $languageDomain->getId();
        }

        usort($countries, static function ($a, $b) {
            return strcasecmp($a['country']->getTranslated()['name'] ?? '', $b['country']->getTranslated()['name'] ?? '');
        });

        return $countries;
    }
}
