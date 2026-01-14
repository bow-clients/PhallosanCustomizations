<?php declare(strict_types=1);

namespace PhallosanCustomizations\Import\Storer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class ImportShippingMethodPriceStorer
{
    public function __construct(
        private readonly EntityRepository $shippingMethodPriceRepository,
    ) {
    }

    public function store(array $data): void
    {
        $this->shippingMethodPriceRepository->upsert($data, Context::createDefaultContext());
    }

    public function buildDataArray(
        string $shippingMethodPriceId,
        string $currencyId,
        float $price,
    ): array {
        $dataCurrencyPrices = [
            [
                'currencyId' => $currencyId,
                'gross' => $price,
                'net' => $price,
                'linked' => false,
            ],
        ];

        // Shopware 6 requirement
        if ($currencyId !== Defaults::CURRENCY) {
            $dataCurrencyPrices[] = [
                'currencyId' => Defaults::CURRENCY,
                'gross' => 0.0,
                'net' => 0.0,
                'linked' => false,
            ];
        }

        return [
            'id' => $shippingMethodPriceId,
            'currencyPrice' => $dataCurrencyPrices,
        ];
    }
}
