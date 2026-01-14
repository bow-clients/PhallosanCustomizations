<?php declare(strict_types=1);

namespace PhallosanCustomizations\Decorator\HrefLangLoader;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Seo\Hreflang\HreflangCollection;
use Shopware\Core\Content\Seo\Hreflang\HreflangStruct;
use Shopware\Core\Content\Seo\HreflangLoader;
use Shopware\Core\Content\Seo\HreflangLoaderParameter;
use Symfony\Component\Routing\RouterInterface;

class HrefLandLoaderDecorator extends HreflangLoader
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly Connection $connection
    ) {
        parent::__construct($this->router, $this->connection);
    }

    public function load(HreflangLoaderParameter $parameter): HreflangCollection
    {
        $salesChannelContext = $parameter->getSalesChannelContext();

        /*if (!$salesChannelContext->getSalesChannel()->isHreflangActive()) {
            return new HreflangCollection();
        }*/

        $domains = $this->fetchSalesChannelDomains();

        $mainDomainId = $this->fetchMainDomain()['id'];

        if ($parameter->getRoute() === 'frontend.home.page') {
            return $this->getHreflangForHomepage($domains, $mainDomainId);
        }

        $pathInfo = $this->router->generate($parameter->getRoute(), $parameter->getRouteParameters(), RouterInterface::ABSOLUTE_PATH);

        $languageToDomainMapping = $this->getLanguageToDomainMapping($domains);
        $seoUrls = $this->fetchSeoUrls($pathInfo, $salesChannelContext->getSalesChannelId(), array_keys($languageToDomainMapping));

        // We need at least two links
        if (\count($seoUrls) <= 1) {
            return new HreflangCollection();
        }

        $hreflangCollection = new HreflangCollection();

        /** @var array{seoPathInfo: string, languageId: string} $seoUrl */
        foreach ($seoUrls as $seoUrl) {
            if (\array_key_exists($seoUrl['languageId'], $languageToDomainMapping)) {
                foreach ($languageToDomainMapping[$seoUrl['languageId']] as $domain) {
                    $this->addHreflangForDomain(
                        $domain,
                        $seoUrl,
                        $mainDomainId,
                        $hreflangCollection
                    );
                }
            }
        }

        return $hreflangCollection;
    }

    private function getHreflangForHomepage(array $domains, ?string $defaultDomainId): HreflangCollection
    {
        $collection = new HreflangCollection();

        if (\count($domains) <= 1) {
            return new HreflangCollection();
        }

        /** @var array{languageId: string, id: string, url: string, locale: string, onlyLocale: bool} $domain */
        foreach ($domains as $domain) {
            $this->addHreflangForDomain(
                $domain,
                null,
                $defaultDomainId,
                $collection
            );
        }

        return $collection;
    }

    private function fetchSalesChannelDomains(): array
    {
        /** @var list<array{languageId: string, id: string, url: string, locale: string, onlyLocale: bool}> $result */
        $result = $this->connection->fetchAllAssociative(
            'SELECT `domain`.`language_id` AS languageId,
                          `domain`.`id` AS id,
                          `domain`.`url` AS url,
                          `domain`.`hreflang_use_only_locale` AS onlyLocale,
                          `locale`.`code` AS locale,
                          country.iso AS iso
            FROM `sales_channel_domain` AS `domain`
            INNER JOIN `sales_channel` ON `sales_channel`.`id` = `domain`.`sales_channel_id`
            INNER JOIN `country` ON `country`.id = `sales_channel`.`country_id`
            INNER JOIN `language` ON `language`.`id` = `domain`.`language_id`
            INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`;'
        );

        return $result;
    }

    private function fetchMainDomain(): array
    {
        /** @var list<array{languageId: string, id: string, url: string, locale: string, onlyLocale: bool, iso: string, priority: int}> $result */
        $result = $this->connection->fetchAssociative(
            'SELECT `domain`.`language_id` AS languageId,
                          `domain`.`id` AS id,
                          `domain`.`url` AS url,
                          `domain`.`hreflang_use_only_locale` AS onlyLocale,
                          `locale`.`code` AS locale,
                          `country`.`iso` AS iso,
                          `prio`.`priority` AS `priority`
            FROM `sales_channel_domain` AS `domain`
            INNER JOIN `sales_channel` ON `sales_channel`.`id` = `domain`.`sales_channel_id`
            INNER JOIN `country` ON `country`.id = `sales_channel`.`country_id`
            INNER JOIN `language` ON `language`.`id` = `domain`.`language_id`
            INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
            INNER JOIN `neti_language_detector_sales_channel_domain_priority` `prio` ON `domain`.id = `prio`.`sales_channel_domain_id`
            ORDER BY prio.`priority` DESC
            LIMIT 1'
        );

        return $result;
    }

    private function getLanguageToDomainMapping(array $domains): array
    {
        $mapping = [];

        foreach ($domains as $domain) {
            $mapping[$domain['languageId']][] = $domain;
        }

        return $mapping;
    }

    private function addHreflangForDomain(
        array $domain,
        ?array $seoUrl,
        ?string $defaultDomainId,
        HreflangCollection $collection
    ): void {
        $hrefLang = new HreflangStruct();

        if (substr($domain['url'], -1) !== '/') {
            $hrefLang->setUrl($domain['url'] . '/');
        } else {
            $hrefLang->setUrl($domain['url']);
        }

        if ($seoUrl) {
            $hrefLang->setUrl($domain['url'] . '/' . $seoUrl['seoPathInfo']);
        }
        $locale = $domain['locale'];

        if ($domain['onlyLocale']) {
            $locale = mb_substr($locale, 0, 2);
        }

        if (!empty($domain['iso'])) {
            $lang = mb_substr($locale, 0, 2);
            $iso = strtolower($domain['iso']);

            $locale = $lang . '-' . $iso;
        }

        if ($domain['id'] === $defaultDomainId) {
            $mainLang = clone $hrefLang;
            $mainLang->setLocale('x-default');
            $collection->add($mainLang);
        }

        $hrefLang->setLocale($locale);
        $collection->add($hrefLang);
    }

    private function fetchSeoUrls(string $pathInfo, string $salesChannelId, array $languageIds): array
    {
        /** @var list<array{seoPathInfo: string, languageId: string}> $result */
        $result = $this->connection->fetchAllAssociative(
            'SELECT `seo_path_info` AS seoPathInfo, `language_id` AS languageId
            FROM `seo_url`
            WHERE `path_info` = :pathInfo AND `is_canonical` = 1
            GROUP BY languageId',
            ['pathInfo' => $pathInfo]
        );

        return $result;
    }
}
