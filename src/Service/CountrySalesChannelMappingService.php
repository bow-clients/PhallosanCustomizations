<?php declare(strict_types=1);

namespace PhallosanCustomizations\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Service for mapping countries to their respective Sales Channels (EU, Asia, World)
 * 
 * Country-to-SC mapping is read DYNAMICALLY from the database (sales_channel_country table).
 * Sales Channel IDs are configured via Plugin Configuration.
 * Default language per country is configured as Custom Field on the Country entity.
 */
class CountrySalesChannelMappingService
{
    public const REGION_EU = 'eu';
    public const REGION_ASIA = 'asia';
    public const REGION_WORLD = 'world';
    
    // Plugin config keys
    private const CONFIG_KEY_SC_EU = 'PhallosanCustomizations.config.salesChannelEu';
    private const CONFIG_KEY_SC_ASIA = 'PhallosanCustomizations.config.salesChannelAsia';
    private const CONFIG_KEY_SC_WORLD = 'PhallosanCustomizations.config.salesChannelWorld';

    // Fallback Sales Channel IDs (used if not configured)
    private const FALLBACK_SC_ID_EU = '019a731a013a76fba1cbd2b571e03b7a';
    private const FALLBACK_SC_ID_ASIA = 'a68b9f35f14211f09b140cc47aaa18aa';
    private const FALLBACK_SC_ID_WORLD = '01966bcab632704199843939e278b496';

    /**
     * Cached SC IDs from config
     */
    private ?string $scIdEu = null;
    private ?string $scIdAsia = null;
    private ?string $scIdWorld = null;

    /**
     * Default language per country ISO code.
     * Used to determine which language to show when redirecting to a SC.
     * Falls back to 'en' if not configured via Custom Field.
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
     * Default currency ISO code per country.
     * Countries not listed here default to 'USD'.
     */
    private const DEFAULT_CURRENCY_BY_COUNTRY = [
        // EUR – European countries
        'AL' => 'EUR', 'AD' => 'EUR', 'AT' => 'EUR', 'BE' => 'EUR', 'BA' => 'EUR',
        'BG' => 'EUR', 'HR' => 'EUR', 'CY' => 'EUR', 'CZ' => 'EUR', 'DK' => 'EUR',
        'EE' => 'EUR', 'FI' => 'EUR', 'FR' => 'EUR', 'GF' => 'EUR', 'PF' => 'EUR',
        'TF' => 'EUR', 'GE' => 'EUR', 'DE' => 'EUR', 'GI' => 'EUR', 'GR' => 'EUR',
        'GL' => 'EUR', 'HU' => 'EUR', 'IS' => 'EUR', 'IE' => 'EUR', 'IT' => 'EUR',
        'LV' => 'EUR', 'LT' => 'EUR', 'LU' => 'EUR', 'MO' => 'EUR', 'MK' => 'EUR',
        'MT' => 'EUR', 'MH' => 'EUR', 'MD' => 'EUR', 'MC' => 'EUR', 'NL' => 'EUR',
        'NO' => 'EUR', 'PL' => 'EUR', 'PT' => 'EUR', 'RO' => 'EUR', 'SM' => 'EUR',
        'SK' => 'EUR', 'SI' => 'EUR', 'ES' => 'EUR', 'SH' => 'EUR', 'PM' => 'EUR',
        'SE' => 'EUR', 'VA' => 'EUR', 'AX' => 'EUR', 'RS' => 'EUR', 'ME' => 'EUR',
        'XK' => 'EUR',

        // CHF – Swiss Franc
        'LI' => 'CHF', 'CH' => 'CHF',

        // AUD – Australian Dollar
        'AU' => 'AUD',

        // JPY – Japanese Yen
        'JP' => 'JPY',

        // GBP – British Pound
        'IO' => 'GBP', 'GB' => 'GBP', 'VG' => 'GBP',
        'GG' => 'GBP', 'IM' => 'GBP', 'JE' => 'GBP',
    ];

    /**
     * Fallback currency ISO per region (used when the mapped currency is not available in the SC)
     */
    private const FALLBACK_CURRENCY_BY_REGION = [
        self::REGION_EU => 'EUR',
        self::REGION_ASIA => 'USD',
        self::REGION_WORLD => 'USD',
    ];

    /**
     * Custom Field name for default language per country
     */
    public const CUSTOM_FIELD_COUNTRY_DEFAULT_LANGUAGE = 'country_geoip_default_language';

    /**
     * Cached country->SC mapping from database
     * @var array<string, string>|null  [ISO => SC_ID]
     */
    private ?array $countryToScCache = null;

    /**
     * Cached country->language ID mapping from custom field
     * @var array<string, string|null>|null  [ISO => Language ID or null]
     */
    private ?array $countryToLanguageCache = null;

    /**
     * Cached currency ISO -> UUID mapping from database
     * @var array<string, string>|null  [ISO_CODE => Currency UUID]
     */
    private ?array $currencyIsoToIdCache = null;

    /**
     * Cached SC -> available currency IDs
     * @var array<string, array<string>>|null  [SC_ID => [Currency UUID, ...]]
     */
    private ?array $scCurrencyCache = null;

    /**
     * Cached language short codes (e.g. 'en', 'de')
     * @var array<string, string>|null [Language ID => Short Code]
     */
    private ?array $languageShortCodeCache = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly SystemConfigService $systemConfigService
    ) {
    }
    
    /**
     * Get the configured Sales Channel ID for EU region
     */
    public function getScIdEu(): string
    {
        if ($this->scIdEu === null) {
            $this->scIdEu = $this->systemConfigService->getString(self::CONFIG_KEY_SC_EU) ?: self::FALLBACK_SC_ID_EU;
        }
        return $this->scIdEu;
    }
    
    /**
     * Get the configured Sales Channel ID for Asia region
     */
    public function getScIdAsia(): string
    {
        if ($this->scIdAsia === null) {
            $this->scIdAsia = $this->systemConfigService->getString(self::CONFIG_KEY_SC_ASIA) ?: self::FALLBACK_SC_ID_ASIA;
        }
        return $this->scIdAsia;
    }
    
    /**
     * Get the configured Sales Channel ID for World region
     */
    public function getScIdWorld(): string
    {
        if ($this->scIdWorld === null) {
            $this->scIdWorld = $this->systemConfigService->getString(self::CONFIG_KEY_SC_WORLD) ?: self::FALLBACK_SC_ID_WORLD;
        }
        return $this->scIdWorld;
    }
    
    /**
     * Get SC ID -> Region mapping (dynamically built from config)
     */
    private function getScToRegionMap(): array
    {
        return [
            $this->getScIdEu() => self::REGION_EU,
            $this->getScIdAsia() => self::REGION_ASIA,
            $this->getScIdWorld() => self::REGION_WORLD,
        ];
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
        
        return $this->getScToRegionMap()[$scId] ?? self::REGION_WORLD;
    }

    /**
     * Get the default language short code for a country ISO code.
     * First checks Custom Field, then falls back to static mapping.
     */
    public function getDefaultLanguageForCountry(string $countryIso): string
    {
        $countryIso = strtoupper($countryIso);
        
        // Try to get from Custom Field first
        $languageId = $this->getDefaultLanguageIdForCountry($countryIso);
        
        if ($languageId !== null) {
            $shortCode = $this->getLanguageShortCode($languageId);
            if ($shortCode !== null) {
                return $shortCode;
            }
        }
        
        // Fallback to static mapping
        return self::DEFAULT_LANGUAGE_BY_COUNTRY[$countryIso] ?? 'en';
    }

    /**
     * Get the default language ID from Custom Field for a country ISO code
     */
    public function getDefaultLanguageIdForCountry(string $countryIso): ?string
    {
        $this->loadCountryLanguageMapping();
        
        return $this->countryToLanguageCache[strtoupper($countryIso)] ?? null;
    }

    /**
     * Get language short code (e.g. 'en', 'de') from language ID
     */
    private function getLanguageShortCode(string $languageId): ?string
    {
        $this->loadLanguageShortCodes();
        
        return $this->languageShortCodeCache[$languageId] ?? null;
    }

    /**
     * Load country -> language ID mapping from Custom Field
     * Note: custom_fields are stored in country_translation table, not in country table
     */
    private function loadCountryLanguageMapping(): void
    {
        if ($this->countryToLanguageCache !== null) {
            return;
        }

        $this->countryToLanguageCache = [];

        // Custom fields are in country_translation table, join with country for ISO
        $sql = '
            SELECT 
                c.iso,
                JSON_UNQUOTE(JSON_EXTRACT(ct.custom_fields, :customFieldPath)) as language_id
            FROM country c
            JOIN country_translation ct ON c.id = ct.country_id
            WHERE c.active = 1
              AND ct.custom_fields IS NOT NULL
              AND JSON_EXTRACT(ct.custom_fields, :customFieldPath) IS NOT NULL
        ';

        $result = $this->connection->fetchAllAssociative($sql, [
            'customFieldPath' => '$."' . self::CUSTOM_FIELD_COUNTRY_DEFAULT_LANGUAGE . '"',
        ]);

        foreach ($result as $row) {
            $iso = strtoupper($row['iso']);
            $languageId = $row['language_id'];
            
            if ($languageId && $languageId !== 'null') {
                $this->countryToLanguageCache[$iso] = $languageId;
            }
        }
    }

    /**
     * Load language short codes (locale code without region, e.g. 'en' from 'en-GB')
     */
    private function loadLanguageShortCodes(): void
    {
        if ($this->languageShortCodeCache !== null) {
            return;
        }

        $this->languageShortCodeCache = [];

        $sql = '
            SELECT 
                LOWER(HEX(l.id)) as language_id,
                SUBSTRING_INDEX(loc.code, "-", 1) as short_code
            FROM language l
            JOIN locale loc ON l.locale_id = loc.id
        ';

        $result = $this->connection->fetchAllAssociative($sql);

        foreach ($result as $row) {
            $this->languageShortCodeCache[$row['language_id']] = $row['short_code'];
        }
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
     * Build the redirect URL for a country.
     * Uses actual database domains instead of static configuration.
     */
    public function getRedirectUrl(string $countryIso, ?string $path = null): string
    {
        $countryIso = strtoupper($countryIso);
        $salesChannelId = $this->getSalesChannelIdForCountry($countryIso);
        
        if ($salesChannelId === null) {
            $salesChannelId = $this->getScIdWorld();
        }
        
        // Get target language short code
        $languageShortCode = $this->getDefaultLanguageForCountry($countryIso);
        
        // Find the domain URL from database
        $domainUrl = $this->findDomainUrlForSalesChannelAndLanguage($salesChannelId, $languageShortCode);
        
        if ($domainUrl === null) {
            // Fallback: Use first domain for SC or World SC
            $region = $this->getScToRegionMap()[$salesChannelId] ?? self::REGION_WORLD;
            $domainUrl = $this->getDomainForSalesChannel($salesChannelId) . '/' . $languageShortCode;
        }
        
        $path = $path ?? '/';
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        
        // If path already contains language prefix (e.g., /de/my-product), strip it
        $path = preg_replace('#^/([a-z]{2})/#', '/', $path);
        
        return rtrim($domainUrl, '/') . $path;
    }

    /**
     * Find domain URL from database for a specific sales channel and language
     */
    private function findDomainUrlForSalesChannelAndLanguage(string $salesChannelId, string $languageShortCode): ?string
    {
        $this->loadDomainCache();
        
        $domains = $this->domainCache[$salesChannelId] ?? [];
        
        // First, try exact match on language short code
        foreach ($domains as $domain) {
            if ($domain['language_code'] === $languageShortCode) {
                return $domain['url'];
            }
        }
        
        // Fallback: return first domain for this SC
        if (!empty($domains)) {
            return $domains[0]['url'];
        }
        
        return null;
    }

    /**
     * @var array<string, array<array{url: string, language_code: string}>>|null
     */
    private ?array $domainCache = null;

    /**
     * Load all domains with language codes from database
     */
    private function loadDomainCache(): void
    {
        if ($this->domainCache !== null) {
            return;
        }

        $this->domainCache = [];

        $sql = '
            SELECT 
                LOWER(HEX(scd.sales_channel_id)) as sales_channel_id,
                scd.url,
                SUBSTRING_INDEX(loc.code, "-", 1) as language_code
            FROM sales_channel_domain scd
            JOIN `language` la ON scd.language_id = la.id
            JOIN locale loc ON la.locale_id = loc.id
        ';

        $result = $this->connection->fetchAllAssociative($sql);

        foreach ($result as $row) {
            $scId = $row['sales_channel_id'];
            if (!isset($this->domainCache[$scId])) {
                $this->domainCache[$scId] = [];
            }
            $this->domainCache[$scId][] = [
                'url' => $row['url'],
                'language_code' => $row['language_code'],
            ];
        }
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
        return $this->getScToRegionMap()[$salesChannelId] ?? self::REGION_WORLD;
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

        $scToRegion = $this->getScToRegionMap();
        foreach ($this->countryToScCache as $iso => $scId) {
            $region = $scToRegion[$scId] ?? self::REGION_WORLD;
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
     * Get the currency UUID for a country, with SC availability check and region fallback.
     * Uses static DEFAULT_CURRENCY_BY_COUNTRY mapping.
     * If the mapped currency is not available in the target SC, falls back to the region default.
     */
    public function getCurrencyIdForCountry(string $countryIso, ?string $salesChannelId = null): ?string
    {
        $countryIso = strtoupper($countryIso);
        $currencyIso = self::DEFAULT_CURRENCY_BY_COUNTRY[$countryIso] ?? 'USD';
        $currencyId = $this->getCurrencyIdByIso($currencyIso);

        // If we have a target SC, check if the currency is available there
        if ($currencyId !== null && $salesChannelId !== null) {
            if (!$this->isCurrencyAvailableInSalesChannel($currencyId, $salesChannelId)) {
                // Fallback to region default currency
                $region = $this->getRegionForSalesChannel($salesChannelId);
                $fallbackIso = self::FALLBACK_CURRENCY_BY_REGION[$region] ?? 'USD';
                $currencyId = $this->getCurrencyIdByIso($fallbackIso);
            }
        }

        return $currencyId;
    }

    /**
     * Get the default currency ISO code for a country (e.g. 'EUR', 'USD')
     */
    public function getDefaultCurrencyIsoForCountry(string $countryIso): string
    {
        return self::DEFAULT_CURRENCY_BY_COUNTRY[strtoupper($countryIso)] ?? 'USD';
    }

    /**
     * Resolve a currency ISO code (e.g. 'EUR') to its Shopware UUID
     */
    private function getCurrencyIdByIso(string $isoCode): ?string
    {
        $this->loadCurrencyIsoToIdCache();

        return $this->currencyIsoToIdCache[strtoupper($isoCode)] ?? null;
    }

    /**
     * Check if a currency is available in a Sales Channel
     */
    private function isCurrencyAvailableInSalesChannel(string $currencyId, string $salesChannelId): bool
    {
        $this->loadScCurrencyCache();

        $available = $this->scCurrencyCache[$salesChannelId] ?? [];

        return in_array($currencyId, $available, true);
    }

    /**
     * Load currency ISO -> UUID mapping from database
     */
    private function loadCurrencyIsoToIdCache(): void
    {
        if ($this->currencyIsoToIdCache !== null) {
            return;
        }

        $this->currencyIsoToIdCache = [];

        $sql = 'SELECT LOWER(HEX(id)) as currency_id, iso_code FROM currency';
        $result = $this->connection->fetchAllAssociative($sql);

        foreach ($result as $row) {
            $this->currencyIsoToIdCache[strtoupper($row['iso_code'])] = $row['currency_id'];
        }
    }

    /**
     * Load Sales Channel -> available currency IDs mapping
     */
    private function loadScCurrencyCache(): void
    {
        if ($this->scCurrencyCache !== null) {
            return;
        }

        $this->scCurrencyCache = [];

        $sql = '
            SELECT 
                LOWER(HEX(sales_channel_id)) as sc_id,
                LOWER(HEX(currency_id)) as currency_id
            FROM sales_channel_currency
        ';

        $result = $this->connection->fetchAllAssociative($sql);

        foreach ($result as $row) {
            $this->scCurrencyCache[$row['sc_id']][] = $row['currency_id'];
        }
    }

    /**
     * Clear the cached country mapping (call after country assignments change)
     */
    public function clearCache(): void
    {
        $this->countryToScCache = null;
        $this->countryToLanguageCache = null;
        $this->languageShortCodeCache = null;
        $this->domainCache = null;
        $this->currencyIsoToIdCache = null;
        $this->scCurrencyCache = null;
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
            'scEu' => str_replace('-', '', $this->getScIdEu()),
            'scAsia' => str_replace('-', '', $this->getScIdAsia()),
            'scWorld' => str_replace('-', '', $this->getScIdWorld()),
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
            $this->getScIdEu() => 1,
            $this->getScIdAsia() => 2,
            $this->getScIdWorld() => 3,
            default => 99,
        };
    }
    
    /**
     * Get the primary domain URL for a Sales Channel (from database)
     */
    public function getDomainForSalesChannel(string $salesChannelId): string
    {
        $this->loadDomainCache();
        
        $domains = $this->domainCache[$salesChannelId] ?? [];
        
        // Return first domain or fallback
        if (!empty($domains)) {
            return rtrim($domains[0]['url'], '/');
        }
        
        // Ultimate fallback
        return 'https://preview.phallosan.com';
    }
}
