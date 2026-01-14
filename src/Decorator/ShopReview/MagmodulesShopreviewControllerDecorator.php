<?php declare(strict_types=1);

namespace PhallosanCustomizations\Decorator\ShopReview;

use Magmodules\Shopreview\Storefront\Controller\MagmodulesShopreviewController;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class MagmodulesShopreviewControllerDecorator
{
    public function __construct(
        private MagmodulesShopreviewController $decorated,
        private TranslatorInterface $translator,
        private Environment $twig
    ) {
    }

    /**
     * @param array<mixed> $args
     */
    public function __call(string $method, array $args): mixed
    {
        return $this->decorated->{$method}(...$args);
    }

    public function sendMagmodulesShopreviewForm(RequestDataBag $data, SalesChannelContext $context): JsonResponse
    {
        try {
            $this->decorated->sendMagmodulesShopreviewForm($data, $context);

            $response = [[
                'type' => 'success',
                'alert' => $this->twig->render('@Storefront/storefront/utilities/alert.html.twig', [
                    'type' => 'success',
                    'content' => $this->translator->trans('magmodules-shopreview.form.review.success'),
                ]),
            ]];
        } catch (\Exception $exception) {
            $response = [[
                'type' => 'danger',
                'alert' => $this->twig->render('@Storefront/storefront/utilities/alert.html.twig', [
                    'type' => 'danger',
                    'content' => $this->translator->trans('magmodules-shopreview.form.review.error'),
                ]),
            ]];
        }

        return new JsonResponse($response);
    }

    public function getMagmodulesShopreviewList(
        Request $request,
        RequestDataBag $data,
        SalesChannelContext $context
    ): Response {
        return $this->decorated->getMagmodulesShopreviewList($request, $data, $context);
    }
}
