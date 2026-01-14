<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Framework\Twig;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ProductMedia extends AbstractExtension
{
    public function __construct(
        private EntityRepository $salesChannelProductRepository
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('getProductMedia', [$this, 'getProductMedia']),
        ];
    }

    public function getProductMedia(string $productId, Context $context): array
    {
        $criteria = new Criteria([$productId]);
        $criteria
            ->getAssociation('media')
            ->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));
        $criteria->addAssociation('media.media');

        $result = $this->salesChannelProductRepository->search($criteria, $context);
        /** @var SalesChannelProductEntity|null $product */
        $product = $result->first();

        if (!$product) {
            return [];
        }

        $mediaItems = [];
        $productMedia = $product->getMedia();

        if ($productMedia !== null) {
            foreach ($productMedia as $mediaAssociation) {
                $media = $mediaAssociation->getMedia();
                if ($media !== null) {
                    $mediaItems[] = $media;
                }
            }
        }

        return $mediaItems;
    }
}
