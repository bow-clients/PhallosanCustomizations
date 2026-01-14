<?php
declare(strict_types=1);

namespace PhallosanCustomizations\DataResolver;

use Magmodules\Shopreview\Core\Content\MagmodulesShopreview\Review\SalesChannel\AbstractReviewListRoute;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class CmsFactoryReviewElementResolver extends AbstractCmsElementResolver
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly ?AbstractReviewListRoute $abstractReviewListRoute,
        private readonly ?EntityRepository $formRepository
    ) {
    }

    public function getType(): string
    {
        return 'aku-cms-factory';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $this->enrichReviews($slot, $resolverContext);
        $this->enrichForms($slot, $resolverContext, $result);
    }

    public function enrichReviews(CmsSlotEntity $slot, ResolverContext $resolverContext): void
    {
        $cmsBlockId = $slot->getConfig()['cms_factory_element_id']['value'] ?? null;

        if (!$cmsBlockId) {
            return;
        }
        
        $configuredBlockId = $this->systemConfigService->get('PhallosanCustomizations.config.cmsFactoryElementReview') ?? null;
        
        if (!$configuredBlockId || $configuredBlockId !== $cmsBlockId) {
            return;
        }
        
        if (!$this->abstractReviewListRoute) {
            return;
        }
        
        $listRoute = $this->abstractReviewListRoute->load(
            new RequestDataBag(),
            $resolverContext->getSalesChannelContext()
        );
        
        $slot->addExtension('reviews', $listRoute);
    }

    public function enrichForms(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $cmsBlockId = $slot->getConfig()['cms_factory_element_id']['value'] ?? null;

        if (!$cmsBlockId) {
            return;
        }
        
        $configuredBlockId = $this->systemConfigService->get('PhallosanCustomizations.config.cmsFactoryElementReview') ?? null;
        
        if (!$configuredBlockId || $configuredBlockId !== $cmsBlockId) {
            return;
        }
        
        if (!$this->formRepository) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));

        $searchResult = $this->formRepository->search(
            $criteria,
            $resolverContext->getSalesChannelContext()->getContext()
        );

        if ($searchResult->count() <= 0) {
            return;
        }

        $slot->addExtension('reviewForm', $searchResult);
    }
}
