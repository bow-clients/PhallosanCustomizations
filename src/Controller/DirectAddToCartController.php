<?php declare(strict_types=1);

namespace PhallosanCustomizations\Controller;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class DirectAddToCartController extends StorefrontController
{
    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly CartService $cartService
    ) {
    }

    #[Route(
        path: '/checkout/directAddToCart/{productNumber}',
        name: 'frontend.checkout.direct-add-to-cart',
        methods: ['GET']
    )]
    public function directAddToCart(
        string $productNumber,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): RedirectResponse {
        try {
            // search product with productnumber
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('productNumber', $productNumber));

            $product = $this->productRepository->search($criteria, $salesChannelContext->getContext())->first();

            if (!$product instanceof ProductEntity) {
                $this->addFlash('danger', $this->trans('checkout.directCart.productNotFound'));

                return $this->redirectToRoute('frontend.home.page');
            }

            // grab the quantity from parameter or set default to 1
            $quantity = max(1, min(100, $request->query->getInt('quantity', 1)));

            $lineItem = new LineItem($product->getId(), LineItem::PRODUCT_LINE_ITEM_TYPE, $product->getId(), $quantity);
            $lineItem->setStackable(true);
            $lineItem->setRemovable(true);

            // add to cart
            $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);
            $cart = $this->cartService->add($cart, $lineItem, $salesChannelContext);

            // redirect to checkout
            return $this->redirectToRoute('frontend.checkout.cart.page');
        } catch (\Exception $e) {
            $this->addFlash('danger', $this->trans('checkout.directCart.error'));

            return $this->redirectToRoute('frontend.home.page');
        }
    }
}
