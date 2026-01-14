<?php declare(strict_types=1);

namespace PhallosanCustomizations\Subscriber;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\Sitemap\Struct\Sitemap;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Page\Sitemap\SitemapPage;
use Shopware\Storefront\Page\Sitemap\SitemapPageLoadedEvent;
use Symfony\Component\Asset\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Overwrite the Sitemap response and return all active SalesChannel Sitemap URLs
 */
class SitemapSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly FilesystemOperator $filesystem,
        private readonly Package $package,
        private readonly EntityRepository $salesChannelRepository,
        private readonly CacheInterface $cache,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SitemapPageLoadedEvent::class => 'onSitemapPageLoaded',
        ];
    }

    public function onSitemapPageLoaded(SitemapPageLoadedEvent $event): void
    {
        /** @var SitemapPage $page */
        $page = $event->getPage();

        $cacheKey = 'all-sitemap-list';
        $context = $event->getContext();

        $value = $this->cache->get($cacheKey, function (ItemInterface $item) use ($context) {
            $allSitemaps = $this->getAllSitemaps($context);

            $item->tag(['all-sitemaps']);

            return CacheValueCompressor::compress($allSitemaps);
        });

        $allSitemaps = CacheValueCompressor::uncompress($value);

        $page->setSitemaps($allSitemaps);
    }

    public function getAllSalesChannel(Context $context): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addAssociations([
            'domains',
            'languages',
        ]);
        $criteria->addFilter(
            new EqualsFilter('active', true)
        );

        return $this->salesChannelRepository->search($criteria, $context);
    }

    public function getAllSitemaps(Context $context): array
    {
        $salesChannelList = $this->getAllSalesChannel($context);

        $sitemapList = [];
        /** @var SalesChannelEntity $salesChannel */
        foreach ($salesChannelList as $salesChannel) {
            if (!$salesChannel->getLanguages() || !$salesChannel->getDomains()) {
                continue;
            }

            foreach ($salesChannel->getLanguages() as $language) {
                $sitemapList = $this->addSitemapsBySalesChannel(
                    $sitemapList,
                    $salesChannel->getId(),
                    $language->getId(),
                    $salesChannel->getDomains()
                );
            }
        }

        return $sitemapList;
    }

    public function addSitemapsBySalesChannel(
        array $sitemapList,
        string $salesChannelId,
        string $languageId,
        SalesChannelDomainCollection $domains
    ): array {
        $files = $this->filesystem->listContents('sitemap/salesChannel-' . $salesChannelId . '-' . $languageId);

        foreach ($files as $file) {
            if ($file->isDir()) {
                continue;
            }

            $filename = basename($file->path());

            $exploded = explode('-', $filename);

            if (isset($exploded[1]) && $domains->has($exploded[1])) {
                /** @var SalesChannelDomainEntity $domain */
                $domain = $domains->get($exploded[1]);

                $sitemapList[] = new Sitemap($domain->getUrl() . '/' . $file->path(), 0, new \DateTime('@' . ($file->lastModified() ?? time())));

                continue;
            }

            $sitemapList[] = new Sitemap($this->package->getUrl($file->path()), 0, new \DateTime('@' . ($file->lastModified() ?? time())));
        }

        return $sitemapList;
    }
}
