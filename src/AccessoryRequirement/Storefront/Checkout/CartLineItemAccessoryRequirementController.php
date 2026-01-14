<?php declare(strict_types=1);

namespace PhallosanCustomizations\AccessoryRequirement\Storefront\Checkout;

use PhallosanCustomizations\AccessoryRequirement\DAL\AccessoryRequirement\Extensions\ProductExtension;
use PhallosanCustomizations\AccessoryRequirement\Service\AccessoryRequirementService;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class CartLineItemAccessoryRequirementController extends StorefrontController
{
    public function __construct(
        private readonly AccessoryRequirementService $accessoryRequirementService,
        private readonly AbstractCartPersister $cartPersister,
    ) {
    }

    #[Route(path: '/checkout/line-item/accessory-requirement/{id}/orderNumber', name: 'frontend.checkout.line-item.accessory-requirement.order-number', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function addRequiredOrderNumber(Cart $cart, string $id, Request $request, SalesChannelContext $context): Response
    {
        //        return Profiler::trace('cart::change-quantity', function () use ($cart, $id, $request, $context) {
        try {
            $orderNumber = $request->get('orderNumber');
            $accessoryRequirementId = $request->get('accessoryRequirementId');

            if (empty($orderNumber)) {
                throw RoutingException::missingRequestParameter('orderNumber');
            }
            if (empty($accessoryRequirementId)) {
                throw RoutingException::missingRequestParameter('accessoryRequirementId');
            }

            if (!$cart->has($id)) {
                throw CartException::lineItemNotFound($id);
            }

            /** @var LineItem $lineItem */
            $lineItem = $cart->get($id);

            if (!$lineItem->hasExtension(ProductExtension::NAME)) {
                throw CartException::lineItemInvalid('Line Item Extension "' . ProductExtension::NAME . '" is missing!');
            }

            $extension = $lineItem->getExtension(ProductExtension::NAME);
            \assert($extension instanceof ArrayStruct);

            $lineItemAccessoryRequirementId = $extension['accessoryRequirementId'] ?? null;

            if (!$lineItemAccessoryRequirementId) {
                throw CartException::lineItemInvalid('Invalid accessoryRequirementId');
            }

            if ($lineItemAccessoryRequirementId !== $accessoryRequirementId) {
                throw CartException::lineItemInvalid('Invalid accessoryRequirementId');
            }

            $isOrderNumberValid = $this->accessoryRequirementService->isRequiredOrderValid($orderNumber, $accessoryRequirementId, $context->getContext());

            $requiredProductInCart = $extension['requiredProductInCart'] ?? false;

            $extension['requiredProductOrderNumber'] = $isOrderNumberValid ? $orderNumber : null;
            $extension['requiredOrderValid'] = $isOrderNumberValid;
            $extension['requirementsFulfilled'] = $isOrderNumberValid || $requiredProductInCart;

            $lineItem->addExtension(ProductExtension::NAME, $extension);

            $this->cartPersister->save($cart, $context);

            if (!$isOrderNumberValid) {
                throw new \Exception('Invalid OrderNumber');
            }

            $this->addFlash(self::SUCCESS, $this->trans('checkout.accessory-required-product-ordernumber-valid'));
        } catch (\Exception $e) {
            $this->addFlash(self::DANGER, $this->trans('checkout.accessory-required-product-ordernumber-invalid'));
            //                $this->addFlash(self::DANGER, $e->getMessage());
        }

        //        echo "Is called!";
        //        exit;

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => true, 'message' => 'Erfolgreich']);
        }

        return $this->createActionResponse($request);
        //        });
    }

    #[Route(path: '/checkout/line-item/accessory-requirement/{id}/reset', name: 'frontend.checkout.line-item.accessory-requirement.reset', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function resetRequiredOrderNumber(Cart $cart, string $id, Request $request, SalesChannelContext $context): Response
    {
        try {
            if (!$cart->has($id)) {
                throw CartException::lineItemNotFound($id);
            }

            /** @var LineItem $lineItem */
            $lineItem = $cart->get($id);

            if (!$lineItem->hasExtension(ProductExtension::NAME)) {
                throw CartException::lineItemInvalid('Line Item Extension "' . ProductExtension::NAME . '" is missing!');
            }

            $extension = $lineItem->getExtension(ProductExtension::NAME);
            \assert($extension instanceof ArrayStruct);

            $requiredProductInCart = $extension['requiredProductInCart'] ?? false;

            $extension['requiredProductOrderNumber'] = null;
            $extension['requiredOrderValid'] = false;
            $extension['requirementsFulfilled'] = $requiredProductInCart;

            $lineItem->addExtension(ProductExtension::NAME, $extension);

            $this->cartPersister->save($cart, $context);

            $this->addFlash(self::SUCCESS, $this->trans('checkout.accessory-required-product-ordernumber-removed'));
        } catch (\Exception $e) {
            $this->addFlash(self::DANGER, $this->trans('checkout.accessory-required-product-ordernumber-remove-failed'));
        }

        return $this->createActionResponse($request);
    }
}
