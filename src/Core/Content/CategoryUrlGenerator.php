<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Content;

use PhallosanCustomizations\PhallosanConstants;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\AbstractCategoryUrlGenerator;
use Shopware\Core\Content\Category\Service\CategoryUrlGenerator as OriginalCategoryUrlGenerator;
use Shopware\Core\Content\Product\Cart\ProductGatewayInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class CategoryUrlGenerator extends OriginalCategoryUrlGenerator
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCategoryUrlGenerator $decorated,
        private readonly EntityRepository $productRepository,
        private readonly ProductGatewayInterface $productGateway,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
    ) {
    }

    public function generate(CategoryEntity $category, ?SalesChannelEntity $salesChannel): ?string
    {
        if (!$salesChannel || $category->getType() !== CategoryDefinition::TYPE_LINK) {
            return $this->decorated->generate($category, $salesChannel);
        }

        $linkType = $category->getTranslation('linkType');

        if ($linkType !== CategoryDefinition::LINK_TYPE_PRODUCT) {
            return $this->decorated->generate($category, $salesChannel);
        }

        $productId = $category->getTranslation('internalLink');



        $randomToken = Uuid::randomHex();
        $salesChannelContext = $this->salesChannelContextFactory->create($randomToken, $salesChannel->getId());

        /** @var SalesChannelProductCollection $product */
        $product = $this->productGateway->get([$productId], $salesChannelContext);

        if ($product->count() !== 0) {
            return $this->decorated->generate($category, $salesChannel);
        }

        $productEntity = $this->getProductByProductId($productId);

        if (!$productEntity) {
            return $this->decorated->generate($category, $salesChannel);
        }


        $productCustomFields = $productEntity->getCustomFields();
        $alternativeProductId = $productCustomFields[PhallosanConstants::CUSTOM_FIELD_PRODUCT_ALTERNATIVE_PRODUCT] ?? null;

        $productNumber = $alternativeProductNumber = $productEntity->getProductNumber();
        if (!$alternativeProductId) {
            if (strpos($productNumber, '-US') !== false) {
                $alternativeProductNumber = str_replace('-US', '', $productNumber);
            } else {
                $alternativeProductNumber = $productNumber . '-US';
            }

            if ($productNumber !== $alternativeProductNumber) {
                $alternativeProductId = $this->getProductIdByProductNumber($alternativeProductNumber);
            }
        }

        if (!$alternativeProductId && $productNumber !== $alternativeProductNumber) {
            return $this->decorated->generate($category, $salesChannel);
        }

        $alternativeProduct = $this->productGateway->get([$alternativeProductId], $salesChannelContext)->first();

        if (!$alternativeProduct) {
            return $this->decorated->generate($category, $salesChannel);
        }

        $category->addTranslated('internalLink', $alternativeProduct->getId());

        return $this->decorated->generate($category, $salesChannel);
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

    private function getProductByProductId(string $productId): ?ProductEntity
    {
        $criteria = new Criteria([$productId]);

        /** @var EntitySearchResult $searchResult */
        $searchResult = $this->productRepository->search($criteria, Context::createDefaultContext());

        if ($searchResult->getTotal() === 0) {
            return null;
        }

        /** @var ProductEntity $productEntity */
        $productEntity = $searchResult->first();

        return $productEntity;
    }
}
