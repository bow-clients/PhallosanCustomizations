<?php declare(strict_types=1);

namespace PhallosanCustomizations\Service;

use PhallosanCustomizations\PhallosanConstants;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class CategoryRedirectService
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $categoryRepository,
        private readonly EntityRepository $productRepository
    ) {
    }

    public function hasDetailPage(string $productId, Context $context): bool
    {
        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('customFields');
        /** @var ProductEntity|null $product */
        $product = $this->productRepository->search($criteria, $context)->first();

        if (!$product) {
            return true;
        }

        $customFields = $product->getTranslated()['customFields'] ?? [];
        
        if (!isset($customFields[PhallosanConstants::CUSTOM_FIELD_PRODUCT_NO_DETAIL]) && $product->getParentId()) {
            return $this->hasDetailPage($product->getParentId(), $context);
        }
        
        return !((bool)($customFields[PhallosanConstants::CUSTOM_FIELD_PRODUCT_NO_DETAIL] ?? false));
    }

    public function getRedirectCategoryId(string $productId, Context $context): ?string
    {
        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('customFields');
        $product = $this->productRepository->search($criteria, $context)->first();

        if (!$product) {
            return null;
        }

        $customFields = $product->getTranslated()['customFields'] ?? [];
        $categoryId = $customFields[PhallosanConstants::CUSTOM_FIELD_PRODUCT_CATEGORY_REDIRECT] ?? null;
        if (empty($customFields[PhallosanConstants::CUSTOM_FIELD_PRODUCT_CATEGORY_REDIRECT])) {
            $categoryId = $this->systemConfigService->get(PhallosanConstants::PLUGIN_CONFIG_DEFAULT_NO_DETAIL_REDIRECT) ?? null;
        }

        return $categoryId;
    }

    public function getSeoUrlByCategoryId(string $categoryId, string $languageId, Context $context): ?string
    {
        $criteria = new Criteria([$categoryId]);
        $criteria->addAssociation('seoUrls');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        /** @var CategoryEntity|null $redirectToCategory */
        $redirectToCategory = $this->categoryRepository->search($criteria, $context)->first();

        if (!$redirectToCategory) {
            return null;
        }

        $redirectToUrl = null;
        if ($redirectToCategory->getLinkType() === CategoryDefinition::LINK_TYPE_EXTERNAL) {
            $redirectToUrl = $redirectToCategory->getExternalLink();
        } else {
            /** @var SeoUrlCollection $seoUrls */
            $seoUrls = $redirectToCategory->getSeoUrls();

            /** @var SeoUrlCollection $categoryUrls */
            $categoryUrls = $seoUrls->filter(function ($url) use ($languageId) {
                \assert($url instanceof SeoUrlEntity);

                return $url->getLanguageId() === $languageId;
            });

            if ($categoryUrls->count() !== 0) {
                $redirectToUrl = '/' . $categoryUrls->first()?->getSeoPathInfo();
            }
        }

        if (empty($redirectToUrl)) {
            return null;
        }

        return $redirectToUrl;
    }
}
