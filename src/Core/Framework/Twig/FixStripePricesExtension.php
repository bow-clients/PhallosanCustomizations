<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Framework\Twig;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class FixStripePricesExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('fixStripePrices', [$this, 'fixStripePrices']),
        ];
    }

    /**
     * Ensures that all prices have only two digits (stripe payment wont work with more than 2 digits))
     */
    public function fixStripePrices(mixed $data, SalesChannelContext $context): array
    {
        $dataArray = json_decode((string)json_encode($data), true);

        $priceIndexes = [
            'netPrice',
            'totalPrice',
            'positionPrice',
            'rawTotal',
        ];

        if (!isset($dataArray['cart']) || !isset($dataArray['cart']['price'])) {
            return $dataArray;
        }

        foreach ($priceIndexes as $priceIndex) {
            if (!isset($dataArray['cart']['price'][$priceIndex])) {
                continue;
            }
            if ($context->getCurrency()->getIsoCode() === 'JPY') {
                $dataArray['cart']['price'][$priceIndex] = number_format($dataArray['cart']['price'][$priceIndex], 0, '.', '');
            } else {
                $dataArray['cart']['price'][$priceIndex] = number_format($dataArray['cart']['price'][$priceIndex], 2, '.', '');
            }
        }

        return $dataArray;
    }
}
