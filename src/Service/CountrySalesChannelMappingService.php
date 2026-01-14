<?php declare(strict_types=1);

namespace PhallosanCustomizations\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Service for mapping countries to their respective Sales Channels (EU, Asia, World)
 * 
 * Country-to-SC mapping is read DYNAMICALLY from the database (sales_channel_country table).
 * Only the default language per country is configured statically.
 * 
 * Sales Channels:
 * - EU:    019a731a013a76fba1cbd2b571e03b7a (EUR, GBP, CHF)
 * - Asia:  019a732c988676e086ad2873ece26058 (USD)
 * - World: 019bbc28b4177179b3fa977326ce37ba (USD)
 */
class CountrySalesChannelMappingService
{
    public const REGION_EU = 'eu';
    public const REGION_ASIA = 'asia';
    public const REGION_WORLD = 'world';

    // Sales Channel IDs
    public const SC_ID_EU = '019a731a013a76fba1cbd2b571e03b7a';
    public const SC_ID_ASIA = '019a732c988676e086ad2873ece26058';
    public const SC_ID_WORLD = '019bbc28b4177179b3fa977326ce37ba';

    /**
     * SC ID -> Region name mapping
     */
    private const SC_TO_REGION = [
        self::SC_ID_EU => self::REGION_EU,
        self::SC_ID_ASIA => self::REGION_ASIA,
        self::SC_ID_WORLD => self::REGION_WORLD,
    ];

    /**
     * Default language per country ISO code.
     * Used to determine which language to show when redirecting to a SC.
     * Falls back to 'en' if not configured.
     */
    private const DEFAULT_LANGUAGE_BY_COUNTRY = [
        // German-speaking
        'DE' => 'de', 'AT' => 'de', 'CH' => 'de', 'LI' => 'de', 'LU' => 'de',
        
        // French-speaking
        'FR' => 'fr', 'BE' => 'fr', 'MC' => 'fr',
        'SN' => 'fr', 'CI' => 'fr', 'CM' => 'fr', 'CD' => 'fr', 'CG' => 'fr',
        'GA' => 'fr', 'BJ' => 'fr', 'TG' => 'fr', 'BF' => 'fr', 'ML' => 'fr',
        'NE' => 'fr', 'TD' => 'fr', 'CF' => 'fr', 'GN' => 'fr', 'MR' => 'fr',
        'MG' => 'fr', 'KM' => 'fr', 'DJ' => 'fr', 'BI' => 'fr', 'RE' => 'fr',
        'YT' => 'fr', 'HT' => 'fr', 'GP' => 'fr', 'MQ' => 'fr', 'GF' => 'fr',
        'BL' => 'fr', 'MF' => 'fr', 'MA' => 'fr', 'DZ' => 'fr', 'TN' => 'fr',
        
        // Italian-speaking
        'IT' => 'it', 'SM' => 'it', 'VA' => 'it',
        
        // Spanish-speaking
        'ES' => 'es', 'AD' => 'es',
        'MX' => 'es', 'GT' => 'es', 'SV' => 'es', 'HN' => 'es', 'NI' => 'es',
        'CR' => 'es', 'PA' => 'es', 'CU' => 'es', 'DO' => 'es', 'PR' => 'es',
        'AR' => 'es', 'CL' => 'es', 'CO' => 'es', 'PE' => 'es', 'VE' => 'es',
        'EC' => 'es', 'BO' => 'es', 'PY' => 'es', 'UY' => 'es',
        'GQ' => 'es', 'EH' => 'es',
        
        // Portuguese-speaking
        'BR' => 'pt', 'AO' => 'pt', 'CV' => 'pt', 'GW' => 'pt', 'ST' => 'pt',
        
        // Dutch-speaking
        'NL' => 'nl',
        
        // Polish-speaking
        'PL' => 'pl',
        
        // Japanese
        'JP' => 'ja',
        
        // Korean
        'KR' => 'ko', 'KP' => 'ko',
        
        // Chinese
        'CN' => 'zh', 'TW' => 'zh', 'HK' => 'zh', 'MO' => 'zh',
        
        // Thai
        'TH' => 'th',
        
        // Hindi
        'IN' => 'hi',
        
        // Arabic
        'AE' => 'ar', 'SA' => 'ar', 'QA' => 'ar', 'KW' => 'ar', 'BH' => 'ar',
        'OM' => 'ar', 'YE' => 'ar', 'JO' => 'ar', 'LB' => 'ar', 'SY' => 'ar',
        'IQ' => 'ar', 'PS' => 'ar',
        
        // Turkish
        'TR' => 'tr',
        
        // Russian
        'RU' => 'ru',
        
        // Greek
        'GR' => 'el', 'CY' => 'el',
        
        // Czech
        'CZ' => 'cs',
        
        // Hungarian
        'HU' => 'hu',
        
        // Swedish
        'SE' => 'sv',
        
        // Norwegian
        'NO' => 'no',
        
        // Finnish
        'FI' => 'fi',
        
        // Croatian
        'HR' => 'hr',
        
        // Lithuanian
        'LT' => 'lt',
        
        // Slovak
        'SK' => 'sk',
    ];

    /**
     * Domain URLs for each region
     */
    private array $regionDomains = [
        self::REGION_EU => 'https://eu.phallosan.com',
        self::REGION_ASIA => 'https://asia.phallosan.com',
        self::REGION_WORLD => 'https://world.phallosan.com',
    ];

    /**
     * Cached country->SC mapping from database
     * @var array<string, string>|null  [ISO => SC_ID]
     */
    private ?array $countryToScCache = null;

    public function __construct(
        private readonly Connection $connection
    ) {
    }

    /**
     * Get the Sales Channel ID for a country ISO code (from database)
     */
    public function getSalesChannelIdForCountry(string $countryIso): ?string
    {
        $this->loadCountryMappingFromDatabase();
        
        $countryIso = strtoupper($countryIso);
        
        return $this->countryToScCache[$countryIso] ?? null;
    }

    /**
     * Get the region for a country ISO code
     */
    public function getRegionForCountry(string $countryIso): string
    {
        $scId = $this->getSalesChannelIdForCountry($countryIso);
        
        if ($scId === null) {
            return self::REGION_WORLD; // Fallback
        }
        
        return self::SC_TO_REGION[$scId] ?? self::REGION_WORLD;
    }

    /**
     * Get the default language for a country ISO code
     */
    public function getDefaultLanguageForCountry(string $countryIso): string
    {
        $countryIso = strtoupper($countryIso);
        
        return self::DEFAULT_LANGUAGE_BY_COUNTRY[$countryIso] ?? 'en';
    }

    /**
     * Get full mapping info for a country
     */
    public function getMappingForCountry(string $countryIso): array
    {
        $countryIso = strtoupper($countryIso);
        
        return [
            'region' => $this->getRegionForCountry($countryIso),
            'language' => $this->getDefaultLanguageForCountry($countryIso),
            'salesChannelId' => $this->getSalesChannelIdForCountry($countryIso),
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
     * Check if a country belongs to the current Sales Channel
     */
    public function isCountryInCurrentSalesChannel(string $countryIso, SalesChannelContext $context): bool
    {
        $countrySalesChannelId = $this->getSalesChannelIdForCountry($countryIso);
        
        return $countrySalesChannelId === $context->getSalesChannelId();
    }

    /**
     * Get the region for a Sales Channel ID
     */
    public function getRegionForSalesChannel(string $salesChannelId): string
    {
        return self::SC_TO_REGION[$salesChannelId] ?? self::REGION_WORLD;
    }

    /**
     * Get all countries for a specific Sales Channel (from database)
     * 
     * @return array<string> List of country ISO codes
     */
    public function getCountriesForSalesChannel(string $salesChannelId): array
    {
        $this->loadCountryMappingFromDatabase();
        
        $countries = [];
        foreach ($this->countryToScCache as $iso => $scId) {
            if ($scId === $salesChannelId) {
                $countries[] = $iso;
            }
        }
        
        return $countries;
    }

    /**
     * Get all countries grouped by region
     */
    public function getCountriesGroupedByRegion(): array
    {
        $this->loadCountryMappingFromDatabase();
        
        $grouped = [
            self::REGION_EU => [],
            self::REGION_ASIA => [],
            self::REGION_WORLD => [],
        ];

        foreach ($this->countryToScCache as $iso => $scId) {
            $region = self::SC_TO_REGION[$scId] ?? self::REGION_WORLD;
            $grouped[$region][] = [
                'iso' => $iso,
                'language' => $this->getDefaultLanguageForCountry($iso),
            ];
        }

        return $grouped;
    }

    /**
     * Check if we have a mapping for a specific country
     */
    public function hasMapping(string $countryIso): bool
    {
        return $this->getSalesChannelIdForCountry($countryIso) !== null;
    }

    /**
     * Get all supported country ISO codes (from database)
     */
    public function getSupportedCountries(): array
    {
        $this->loadCountryMappingFromDatabase();
        
        return array_keys($this->countryToScCache ?? []);
    }

    /**
     * Set domain URLs for regions (call from config)
     */
    public function setRegionDomains(array $domains): void
    {
        $this->regionDomains = array_merge($this->regionDomains, $domains);
    }

    /**
     * Clear the cached country mapping (call after country assignments change)
     */
    public function clearCache(): void
    {
        $this->countryToScCache = null;
    }

    /**
     * Load country->SC mapping from the database
     */
    private function loadCountryMappingFromDatabase(): void
    {
        if ($this->countryToScCache !== null) {
            return;
        }

        $this->countryToScCache = [];

        // Query all country assignments for our 3 Sales Channels
        $sql = '
            SELECT 
                c.iso,
                LOWER(HEX(scc.sales_channel_id)) as sales_channel_id
            FROM sales_channel_country scc
            JOIN country c ON scc.country_id = c.id
            WHERE scc.sales_channel_id IN (
                UNHEX(:scEu),
                UNHEX(:scAsia),
                UNHEX(:scWorld)
            )
            AND c.active = 1
        ';

        $result = $this->connection->fetchAllAssociative($sql, [
            'scEu' => self::SC_ID_EU,
            'scAsia' => self::SC_ID_ASIA,
            'scWorld' => self::SC_ID_WORLD,
        ]);

        foreach ($result as $row) {
            $iso = strtoupper($row['iso']);
            $scId = $row['sales_channel_id'];
            
            // If a country is assigned to multiple SCs, prioritize: EU > Asia > World
            if (!isset($this->countryToScCache[$iso])) {
                $this->countryToScCache[$iso] = $scId;
            } else {
                // Priority handling
                $currentPriority = $this->getSalesChannelPriority($this->countryToScCache[$iso]);
                $newPriority = $this->getSalesChannelPriority($scId);
                
                if ($newPriority < $currentPriority) {
                    $this->countryToScCache[$iso] = $scId;
                }
            }
        }
    }

    /**
     * Get priority for SC (lower = higher priority)
     */
    private function getSalesChannelPriority(string $salesChannelId): int
    {
        return match ($salesChannelId) {
            self::SC_ID_EU => 1,
            self::SC_ID_ASIA => 2,
            self::SC_ID_WORLD => 3,
            default => 99,
        };
    }
}
