<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Framework\Twig;

use PhallosanCustomizations\Service\CountrySalesChannelMappingService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class LanguageSwitchExtension extends AbstractExtension
{
    public function __construct(
        private readonly EntityRepository $domainRepository,
        private readonly EntityRepository $countryRepository,
        private readonly CountrySalesChannelMappingService $mappingService
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('getLanguageSwitch', [$this, 'getLanguageSwitch']),
            new TwigFunction('getCountrySwitch', [$this, 'getCountrySwitch']),
            new TwigFunction('getLanguagesForCurrentChannel', [$this, 'getLanguagesForCurrentChannel']),
            new TwigFunction('getCurrentCountryInfo', [$this, 'getCurrentCountryInfo']),
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

    /**
     * Get all countries for the country dropdown
     * Groups countries with their target Sales Channel based on mapping
     */
    public function getCountrySwitch(SalesChannelContext $salesChannelContext): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $countries = $this->countryRepository->search($criteria, $salesChannelContext->getContext())->getElements();

        $result = [];

        /** @var CountryEntity $country */
        foreach ($countries as $country) {
            $iso = $country->getIso();
            if (!$iso) {
                continue;
            }

            $mapping = $this->mappingService->getMappingForCountry($iso);
            
            $result[] = [
                'id' => $country->getId(),
                'iso' => $iso,
                'name' => $country->getTranslated()['name'] ?? $country->getName(),
                'region' => $mapping['region'],
                'defaultLanguage' => $mapping['language'],
                'redirectUrl' => $this->mappingService->getRedirectUrl($iso),
            ];
        }

        return $result;
    }

    /**
     * Get available languages for the current Sales Channel
     */
    public function getLanguagesForCurrentChannel(SalesChannelContext $salesChannelContext): array
    {
        $criteria = new Criteria();
        $criteria->addAssociation('language.translationCode');
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelContext->getSalesChannelId()));

        $domains = $this->domainRepository->search($criteria, $salesChannelContext->getContext())->getElements();

        $languages = [];
        $currentLanguageId = $salesChannelContext->getLanguageId();

        /** @var SalesChannelDomainEntity $domain */
        foreach ($domains as $domain) {
            $language = $domain->getLanguage();
            if (!$language) {
                continue;
            }

            $translationCode = $language->getTranslationCode();
            $languageCode = $translationCode?->getCode() ?? 'en-GB';
            $shortCode = substr($languageCode, 0, 2);

            $languages[] = [
                'id' => $language->getId(),
                'domainId' => $domain->getId(),
                'name' => $language->getTranslated()['name'] ?? $language->getName(),
                'code' => $languageCode,
                'shortCode' => $shortCode,
                'url' => $domain->getUrl(),
                'isActive' => $language->getId() === $currentLanguageId,
            ];
        }

        // Sort by name
        usort($languages, static fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return $languages;
    }

    /**
     * Get current country info from Sales Channel context
     */
    public function getCurrentCountryInfo(SalesChannelContext $salesChannelContext): array
    {
        $country = $salesChannelContext->getShippingLocation()->getCountry();
        $iso = $country->getIso() ?? 'DE';
        $region = $this->mappingService->getRegionForCountry($iso);

        return [
            'id' => $country->getId(),
            'iso' => $iso,
            'name' => $country->getTranslated()['name'] ?? $country->getName(),
            'region' => $region,
        ];
    }
}
