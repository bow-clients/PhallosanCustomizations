<?php declare(strict_types=1);

namespace PhallosanCustomizations\Service;

use PhallosanCustomizations\PhallosanConstants;
use Shopware\Core\Content\Product\Cart\ProductGatewayInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class LanguageChannelSwitchService
{
    public SalesChannelDomainEntity $domain;

    public function __construct(
        private readonly EntityRepository $domainRepository,
        private readonly EntityRepository $seoUrlRepository,
        private readonly EntityRepository $countryRepository,
        private readonly EntityRepository $productRepository,
        private readonly ProductGatewayInterface $productGateway,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
        private readonly CountrySalesChannelMappingService $mappingService,
    ) {
    }

    /**
     * Create redirect route for country/language switch
     * Uses the new 3-SC mapping model
     */
    public function createRedirectRoute(
        SalesChannelContext $salesChannelContext,
        string $salesChannelDomainId,
        string $countryId,
        SessionInterface $session,
        string $requestUri = '/',
        string $controller = '',
        ?string $fragment = ''
    ): string|false {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $salesChannelDomainId));
        $criteria->addAssociation('salesChannel');

        $country = $this->getCountryById($countryId, $salesChannelContext->getContext());

        if ($country) {
            $session->set('countryId', $countryId);

            $key = $salesChannelContext->getSalesChannelId() . PhallosanConstants::SESSION_SALES_CHANNEL_COUNTRY;
            $session->set($key, $country->getTranslated()['name']);
            $session->set(PhallosanConstants::SESSION_SALES_CHANNEL_COUNTRY_ID, $countryId);
        }

        $response = new Response();
        $response->headers->set('X-Robots-Tag', 'noindex, follow');

        $domain = $this->domainRepository->search($criteria, $salesChannelContext->getContext())->first();

        if (!$domain instanceof SalesChannelDomainEntity) {
            return false;
        }

        $this->domain = $domain;

        if ($fragment) {
            $fragment = '#' . $fragment;
        }

        $needSeoRoute = $this->needSeoRoute($controller, $_REQUEST['redirectTo'] ?? '');

        if ($needSeoRoute) {
            $seoUrl = $this->getSeoRoute($requestUri, $domain, $salesChannelContext);

            if (!$seoUrl && strpos($requestUri, '/detail/') === 0) {
                $productId = str_replace('/detail/', '', $requestUri);

                $randomToken = Uuid::randomHex();
                $targetSalesChannelContext = $this->salesChannelContextFactory->create($randomToken, $this->domain->getSalesChannelId());

                $alternativeId = $this->findAlternativeProduct($productId, $targetSalesChannelContext);

                if ($alternativeId) {
                    $requestUri = '/detail/' . $alternativeId;
                    $seoUrl = $this->getSeoRoute($requestUri, $domain, $salesChannelContext);
                }
            }

            return $seoUrl ? '/' . $seoUrl->getSeoPathInfo() . $fragment : '';
        }

        return $requestUri . $fragment;
    }

    private function findAlternativeProduct(string $productId, SalesChannelContext $salesChannelContext): ?string
    {
        $criteria = new Criteria([$productId]);

        /** @var EntitySearchResult $searchResult */
        $searchResult = $this->productRepository->search($criteria, Context::createDefaultContext());

        if ($searchResult->getTotal() === 0) {
            return null;
        }

        /** @var ProductEntity|null $productEntity */
        $productEntity = $searchResult->first();

        if (!$productEntity) {
            return null;
        }

        $productNumber = $alternativeProductNumber = $productEntity->getProductNumber();

        if (strpos($productNumber, '-US') !== false) {
            $alternativeProductNumber = str_replace('-US', '', $productNumber);
        } else {
            $alternativeProductNumber = $productNumber . '-US';
        }

        if ($productNumber === $alternativeProductNumber) {
            return null;
        }

        $alternativeProductId = $this->getProductIdByProductNumber($alternativeProductNumber);

        $alternativeProduct = $this->productGateway->get([$alternativeProductId], $salesChannelContext)->first();

        if (!$alternativeProduct) {
            return null;
        }

        return $alternativeProduct->getId();
    }

    private function getProductIdByProductNumber(string $productNumber): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('productNumber', $productNumber)
        );

        /** @var IdSearchResult $idSearchResult */
        $idSearchResult = $this->productRepository->searchIds($criteria, Context::createDefaultContext());

        if ($idSearchResult->getTotal() === 0) {
            return null;
        }

        return $idSearchResult->firstId();
    }

    private function getSeoRoute(string $requestUri, SalesChannelDomainEntity $domain, SalesChannelContext $salesChannelContext): ?SeoUrlEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('pathInfo', $requestUri));
        $criteria->addFilter(new EqualsFilter('salesChannelId', $domain->getSalesChannelId()));
        $criteria->addFilter(new EqualsFilter('languageId', $domain->getLanguageId()));
        $criteria->addFilter(new EqualsFilter('isDeleted', 0));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));


        /** @var SeoUrlEntity|null $seoUrl */
        $seoUrl = $this->seoUrlRepository->search($criteria, $salesChannelContext->getContext())->first();

        return $seoUrl;
    }

    private function getCountryById(string $countryId, Context $context): ?CountryEntity
    {
        /** @var CountryEntity|null $countryEntity */
        $countryEntity = $this->countryRepository->search(new Criteria([$countryId]), $context)->first();

        return $countryEntity;
    }

    private function needSeoRoute(string $controller, string $requestController = ''): bool
    {
        return !(str_contains($controller, 'checkout')
            || str_contains($controller, 'account')
            || str_contains($requestController, 'checkout')
            || str_contains($requestController, 'account')
            || str_contains($requestController, 'dvsn'));
    }

    /**
     * Create redirect route for country switch using new 3-SC mapping
     * This is the new method for the consolidated Sales Channel model
     */
    public function createRedirectRouteForCountry(
        SalesChannelContext $salesChannelContext,
        string $countryIso,
        SessionInterface $session,
        string $requestUri = '/',
        string $controller = '',
        ?string $fragment = ''
    ): string {
        $mapping = $this->mappingService->getMappingForCountry($countryIso);
        $targetRegion = $mapping['region'];
        $targetLanguage = $mapping['language'];

        // Get country entity by ISO
        $country = $this->getCountryByIso($countryIso, $salesChannelContext->getContext());
        
        if ($country) {
            $session->set('countryId', $country->getId());
            $key = $salesChannelContext->getSalesChannelId() . PhallosanConstants::SESSION_SALES_CHANNEL_COUNTRY;
            $session->set($key, $country->getTranslated()['name']);
            $session->set(PhallosanConstants::SESSION_SALES_CHANNEL_COUNTRY_ID, $country->getId());
        }

        // Build redirect URL
        $fragment = $fragment ? '#' . $fragment : '';
        $redirectUrl = $this->mappingService->getRedirectUrl($countryIso, $requestUri);
        
        return $redirectUrl . $fragment;
    }

    /**
     * Get country entity by ISO code
     */
    private function getCountryByIso(string $iso, Context $context): ?CountryEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', strtoupper($iso)));

        /** @var CountryEntity|null $countryEntity */
        $countryEntity = $this->countryRepository->search($criteria, $context)->first();

        return $countryEntity;
    }

    /**
     * Check if country change requires Sales Channel switch
     */
    public function needsSalesChannelSwitch(string $countryIso, SalesChannelContext $context): bool
    {
        return !$this->mappingService->isCountryInCurrentSalesChannel($countryIso, $context);
    }

    /**
     * Get the mapping service for external access
     */
    public function getMappingService(): CountrySalesChannelMappingService
    {
        return $this->mappingService;
    }
}
