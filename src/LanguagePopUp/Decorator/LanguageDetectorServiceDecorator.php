<?php declare(strict_types=1);

namespace PhallosanCustomizations\LanguagePopUp\Decorator;

use NetInventors\NetiNextLanguageDetector\Extension\Content\SalesChannelDomain\SalesChannelDomainExtension;
use NetInventors\NetiNextLanguageDetector\Service\LanguageDetectorService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;

class LanguageDetectorServiceDecorator extends LanguageDetectorService
{
    public function __construct(
        EntityRepository $languageRepository,
        private readonly EntityRepository $domainRepository,
        EntityRepository $productVisibilityRepository
    ) {
        parent::__construct(
            $languageRepository,
            $domainRepository,
            $productVisibilityRepository
        );
    }

    public function getSalesChannelsDomains(string $salesChannelId, Context $context): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addAssociations(
            [
                'language.locale',
                'language.locale.translations',
                'language.translationCode',
                'salesChannel.country',
                'salesChannel',
                SalesChannelDomainExtension::SALES_CHANNEL_DOMAIN_EXTENSION_NAME,
            ],
        );
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));

        return $this->domainRepository->search($criteria, $context);
    }

    public function getOtherSalesChannelsDomains(array $domains, string $salesChannelId, Context $context): array
    {
        $languageIds = [];

        if ($domains !== []) {
            /** @var SalesChannelDomainEntity $domain */
            foreach ($domains as $domain) {
                $languageId = $domain->getLanguageId();
                $languageIds[$languageId] = $languageId;
            }
        }

        $criteria = new Criteria();
        $criteria->addAssociations(
            [
                'language.locale',
                'language.locale.translations',
                'language.translationCode',
                'salesChannel.type',
                'salesChannel.country',
                SalesChannelDomainExtension::SALES_CHANNEL_DOMAIN_EXTENSION_NAME,
            ],
        );

        $criteria->addFilter(new EqualsFilter('salesChannel.active', true));
        /** @psalm-suppress InternalClass */
        $criteria->addFilter(new EqualsFilter('salesChannel.type.id', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        $criteria->addFilter(
            new NotFilter(
                MultiFilter::CONNECTION_AND,
                [new EqualsAnyFilter('languageId', $languageIds)],
            ),
        );

        if ($languageIds !== []) {
            $criteria->addFilter(
                new NotFilter(
                    MultiFilter::CONNECTION_AND,
                    [new EqualsFilter('salesChannelId', $salesChannelId)],
                ),
            );
        }

        return $this->domainRepository->search($criteria, $context)->getElements();
    }
}
