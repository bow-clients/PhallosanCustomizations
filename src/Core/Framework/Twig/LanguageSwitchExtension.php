<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Framework\Twig;

use PhallosanCustomizations\Controller\LanguageSwitchController;
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
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class LanguageSwitchExtension extends AbstractExtension
{
    public function __construct(
        private readonly EntityRepository $domainRepository,
        private readonly EntityRepository $countryRepository,
        private readonly CountrySalesChannelMappingService $mappingService,
        private readonly RequestStack $requestStack
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

    public function getFilters(): array
    {
        return [
            new TwigFilter('country_flag_emoji', [$this, 'countryFlagEmoji']),
        ];
    }

    /**
     * Convert country ISO code to flag emoji
     * Uses regional indicator symbols: A = 🇦 (U+1F1E6), B = 🇧 (U+1F1E7), etc.
     */
    public function countryFlagEmoji(string $countryIso): string
    {
        $countryIso = strtoupper(trim($countryIso));
        
        if (strlen($countryIso) !== 2) {
            return '🏳️'; // White flag as fallback
        }

        // Regional indicator symbols start at U+1F1E6 for 'A'
        $offset = 0x1F1E6 - ord('A');
        
        $flag = mb_chr(ord($countryIso[0]) + $offset) . mb_chr(ord($countryIso[1]) + $offset);
        
        return $flag;
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
     * Deduplicates by language short code to avoid showing same language multiple times
     */
    public function getLanguagesForCurrentChannel(SalesChannelContext $salesChannelContext): array
    {
        $criteria = new Criteria();
        $criteria->addAssociation('language');
        $criteria->addAssociation('language.locale');
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelContext->getSalesChannelId()));

        $domains = $this->domainRepository->search($criteria, $salesChannelContext->getContext())->getElements();

        $languages = [];
        $seenShortCodes = [];
        $currentLanguageId = $salesChannelContext->getLanguageId();

        /** @var SalesChannelDomainEntity $domain */
        foreach ($domains as $domain) {
            $language = $domain->getLanguage();
            if (!$language) {
                continue;
            }

            // Get locale code from language.locale (not translationCode)
            $locale = $language->getLocale();
            $languageCode = $locale?->getCode() ?? 'en-GB';
            $shortCode = strtolower(substr($languageCode, 0, 2));
            
            // Skip duplicate languages by short code (e.g. "en" appears once, not twice)
            if (isset($seenShortCodes[$shortCode])) {
                // But if this one is the active language, update the entry
                if ($language->getId() === $currentLanguageId) {
                    foreach ($languages as &$lang) {
                        if (strtolower($lang['shortCode']) === $shortCode) {
                            $lang['isActive'] = true;
                            $lang['url'] = $domain->getUrl();
                            break;
                        }
                    }
                }
                continue;
            }
            $seenShortCodes[$shortCode] = true;

            $languages[] = [
                'id' => $language->getId(),
                'domainId' => $domain->getId(),
                'name' => $language->getTranslated()['name'] ?? $language->getName(),
                'code' => $languageCode,
                'shortCode' => strtoupper($shortCode),
                'url' => $domain->getUrl(),
                'isActive' => $language->getId() === $currentLanguageId,
            ];
        }

        // Sort by name
        usort($languages, static fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return $languages;
    }

    /**
     * Get current country info - first checks cookie, then falls back to shipping location
     */
    public function getCurrentCountryInfo(SalesChannelContext $salesChannelContext): array
    {
        // First check if user has selected a country via cookie
        $request = $this->requestStack->getCurrentRequest();
        $selectedCountryIso = $request?->cookies->get(LanguageSwitchController::COOKIE_SELECTED_COUNTRY);
        
        if ($selectedCountryIso && strlen($selectedCountryIso) === 2) {
            // User has selected a country - use that
            $selectedCountryIso = strtoupper($selectedCountryIso);
            
            // Try to get country name from database
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('iso', $selectedCountryIso));
            $criteria->setLimit(1);
            
            $country = $this->countryRepository->search($criteria, $salesChannelContext->getContext())->first();
            
            if ($country instanceof CountryEntity) {
                return [
                    'id' => $country->getId(),
                    'iso' => $selectedCountryIso,
                    'name' => $country->getTranslated()['name'] ?? $country->getName(),
                    'region' => $this->mappingService->getRegionForCountry($selectedCountryIso),
                ];
            }
            
            // Country not found in DB - still use the ISO from cookie
            return [
                'id' => '',
                'iso' => $selectedCountryIso,
                'name' => $selectedCountryIso,
                'region' => $this->mappingService->getRegionForCountry($selectedCountryIso),
            ];
        }
        
        // Fallback: use shipping location from context
        $country = $salesChannelContext->getShippingLocation()->getCountry();
        $iso = $country->getIso() ?? 'US';
        $region = $this->mappingService->getRegionForCountry($iso);

        return [
            'id' => $country->getId(),
            'iso' => $iso,
            'name' => $country->getTranslated()['name'] ?? $country->getName(),
            'region' => $region,
        ];
    }
}
