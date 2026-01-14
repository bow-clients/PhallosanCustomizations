<?php declare(strict_types=1);

namespace PhallosanCustomizations\Service;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Service for mapping countries to their respective Sales Channels (EU, Asia, World)
 * 
 * This service determines which Sales Channel a country belongs to and provides
 * the correct redirect URL including the default language for that country.
 */
class CountrySalesChannelMappingService
{
    public const REGION_EU = 'eu';
    public const REGION_ASIA = 'asia';
    public const REGION_WORLD = 'world';

    /**
     * Country ISO Code -> Region + Default Language Mapping
     * 
     * Format: 'ISO' => ['region' => 'eu|asia|world', 'language' => 'de|en|fr|...']
     */
    private const COUNTRY_MAPPING = [
        // EU Countries
        'DE' => ['region' => self::REGION_EU, 'language' => 'de'],
        'AT' => ['region' => self::REGION_EU, 'language' => 'de'],
        'CH' => ['region' => self::REGION_EU, 'language' => 'de'],
        'LI' => ['region' => self::REGION_EU, 'language' => 'de'],
        'LU' => ['region' => self::REGION_EU, 'language' => 'de'],
        
        'FR' => ['region' => self::REGION_EU, 'language' => 'fr'],
        'BE' => ['region' => self::REGION_EU, 'language' => 'fr'],
        'MC' => ['region' => self::REGION_EU, 'language' => 'fr'],
        
        'IT' => ['region' => self::REGION_EU, 'language' => 'it'],
        'SM' => ['region' => self::REGION_EU, 'language' => 'it'],
        'VA' => ['region' => self::REGION_EU, 'language' => 'it'],
        
        'ES' => ['region' => self::REGION_EU, 'language' => 'es'],
        'AD' => ['region' => self::REGION_EU, 'language' => 'es'],
        
        'PT' => ['region' => self::REGION_EU, 'language' => 'pt'],
        
        'NL' => ['region' => self::REGION_EU, 'language' => 'nl'],
        
        'PL' => ['region' => self::REGION_EU, 'language' => 'pl'],
        
        'GB' => ['region' => self::REGION_EU, 'language' => 'en'],
        'IE' => ['region' => self::REGION_EU, 'language' => 'en'],
        'MT' => ['region' => self::REGION_EU, 'language' => 'en'],
        
        'SE' => ['region' => self::REGION_EU, 'language' => 'sv'],
        'NO' => ['region' => self::REGION_EU, 'language' => 'no'],
        'DK' => ['region' => self::REGION_EU, 'language' => 'da'],
        'FI' => ['region' => self::REGION_EU, 'language' => 'fi'],
        'IS' => ['region' => self::REGION_EU, 'language' => 'en'],
        
        'GR' => ['region' => self::REGION_EU, 'language' => 'el'],
        'CY' => ['region' => self::REGION_EU, 'language' => 'el'],
        
        'CZ' => ['region' => self::REGION_EU, 'language' => 'cs'],
        'SK' => ['region' => self::REGION_EU, 'language' => 'sk'],
        'HU' => ['region' => self::REGION_EU, 'language' => 'hu'],
        'RO' => ['region' => self::REGION_EU, 'language' => 'ro'],
        'BG' => ['region' => self::REGION_EU, 'language' => 'bg'],
        'HR' => ['region' => self::REGION_EU, 'language' => 'hr'],
        'SI' => ['region' => self::REGION_EU, 'language' => 'sl'],
        'EE' => ['region' => self::REGION_EU, 'language' => 'et'],
        'LV' => ['region' => self::REGION_EU, 'language' => 'lv'],
        'LT' => ['region' => self::REGION_EU, 'language' => 'lt'],
        
        // Asia Countries
        'JP' => ['region' => self::REGION_ASIA, 'language' => 'ja'],
        'KR' => ['region' => self::REGION_ASIA, 'language' => 'ko'],
        'CN' => ['region' => self::REGION_ASIA, 'language' => 'zh'],
        'TW' => ['region' => self::REGION_ASIA, 'language' => 'zh'],
        'HK' => ['region' => self::REGION_ASIA, 'language' => 'en'],
        'SG' => ['region' => self::REGION_ASIA, 'language' => 'en'],
        'MY' => ['region' => self::REGION_ASIA, 'language' => 'en'],
        'TH' => ['region' => self::REGION_ASIA, 'language' => 'en'],
        'VN' => ['region' => self::REGION_ASIA, 'language' => 'en'],
        'PH' => ['region' => self::REGION_ASIA, 'language' => 'en'],
        'ID' => ['region' => self::REGION_ASIA, 'language' => 'en'],
        'IN' => ['region' => self::REGION_ASIA, 'language' => 'en'],
        
        // World Countries (Americas, Oceania, Africa, etc.)
        'US' => ['region' => self::REGION_WORLD, 'language' => 'en'],
        'CA' => ['region' => self::REGION_WORLD, 'language' => 'en'],
        'AU' => ['region' => self::REGION_WORLD, 'language' => 'en'],
        'NZ' => ['region' => self::REGION_WORLD, 'language' => 'en'],
        'ZA' => ['region' => self::REGION_WORLD, 'language' => 'en'],
        
        'MX' => ['region' => self::REGION_WORLD, 'language' => 'es'],
        'AR' => ['region' => self::REGION_WORLD, 'language' => 'es'],
        'CL' => ['region' => self::REGION_WORLD, 'language' => 'es'],
        'CO' => ['region' => self::REGION_WORLD, 'language' => 'es'],
        'PE' => ['region' => self::REGION_WORLD, 'language' => 'es'],
        
        'BR' => ['region' => self::REGION_WORLD, 'language' => 'pt'],
    ];

    /**
     * Domain configuration for each region
     * This should be configured via plugin config or environment
     */
    private array $regionDomains = [
        self::REGION_EU => 'https://eu.phallosan.com',
        self::REGION_ASIA => 'https://asia.phallosan.com',
        self::REGION_WORLD => 'https://world.phallosan.com',
    ];

    /**
     * Sales Channel IDs for each region
     * These need to be set after Sales Channels are created in Admin
     */
    private array $regionSalesChannelIds = [
        self::REGION_EU => null,
        self::REGION_ASIA => null,
        self::REGION_WORLD => null,
    ];

    public function __construct(
        private readonly EntityRepository $salesChannelDomainRepository,
        private readonly EntityRepository $countryRepository
    ) {
    }

    /**
     * Get the region for a country ISO code
     */
    public function getRegionForCountry(string $countryIso): string
    {
        $countryIso = strtoupper($countryIso);
        
        return self::COUNTRY_MAPPING[$countryIso]['region'] ?? self::REGION_WORLD;
    }

    /**
     * Get the default language for a country ISO code
     */
    public function getDefaultLanguageForCountry(string $countryIso): string
    {
        $countryIso = strtoupper($countryIso);
        
        return self::COUNTRY_MAPPING[$countryIso]['language'] ?? 'en';
    }

    /**
     * Get full mapping info for a country
     */
    public function getMappingForCountry(string $countryIso): array
    {
        $countryIso = strtoupper($countryIso);
        
        return self::COUNTRY_MAPPING[$countryIso] ?? [
            'region' => self::REGION_WORLD,
            'language' => 'en'
        ];
    }

    /**
     * Get the domain URL for a region
     */
    public function getDomainForRegion(string $region): string
    {
        return $this->regionDomains[$region] ?? $this->regionDomains[self::REGION_WORLD];
    }

    /**
     * Build the redirect URL for a country
     */
    public function getRedirectUrl(string $countryIso, ?string $path = null): string
    {
        $mapping = $this->getMappingForCountry($countryIso);
        $domain = $this->getDomainForRegion($mapping['region']);
        $language = $mapping['language'];
        
        $path = $path ?? '/';
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        
        return $domain . '/' . $language . $path;
    }

    /**
     * Check if a country belongs to the current Sales Channel's region
     */
    public function isCountryInCurrentRegion(string $countryIso, SalesChannelContext $context): bool
    {
        $currentRegion = $this->getRegionForSalesChannel($context->getSalesChannelId());
        $countryRegion = $this->getRegionForCountry($countryIso);
        
        return $currentRegion === $countryRegion;
    }

    /**
     * Get the region for a Sales Channel ID
     */
    public function getRegionForSalesChannel(string $salesChannelId): string
    {
        foreach ($this->regionSalesChannelIds as $region => $id) {
            if ($id === $salesChannelId) {
                return $region;
            }
        }
        
        // Default to world if not found
        return self::REGION_WORLD;
    }

    /**
     * Set Sales Channel IDs for regions (call from config or migration)
     */
    public function setRegionSalesChannelIds(array $ids): void
    {
        $this->regionSalesChannelIds = array_merge($this->regionSalesChannelIds, $ids);
    }

    /**
     * Set domain URLs for regions (call from config)
     */
    public function setRegionDomains(array $domains): void
    {
        $this->regionDomains = array_merge($this->regionDomains, $domains);
    }

    /**
     * Get all countries grouped by region for UI display
     */
    public function getCountriesGroupedByRegion(): array
    {
        $grouped = [
            self::REGION_EU => [],
            self::REGION_ASIA => [],
            self::REGION_WORLD => [],
        ];

        foreach (self::COUNTRY_MAPPING as $iso => $data) {
            $grouped[$data['region']][] = [
                'iso' => $iso,
                'language' => $data['language'],
            ];
        }

        return $grouped;
    }

    /**
     * Check if we have a mapping for a specific country
     */
    public function hasMapping(string $countryIso): bool
    {
        return isset(self::COUNTRY_MAPPING[strtoupper($countryIso)]);
    }

    /**
     * Get all supported country ISO codes
     */
    public function getSupportedCountries(): array
    {
        return array_keys(self::COUNTRY_MAPPING);
    }
}
