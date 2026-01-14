<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Checkout\Struct;

use Shopware\Core\Framework\Struct\Struct;

class CustomsDutyCartExtensionStruct extends Struct
{
    /**
     * @param array<string, CustomsDutyLineItemStruct[]> $lineItemCustomDuties
     */
    public function __construct(
        /** @var array<string, CustomsDutyLineItemStruct[]> */
        protected array $lineItemCustomDuties
    ) {
    }

    /**
     * @return array<string, CustomsDutyLineItemStruct[]>
     */
    public function getLineItemCustomDuties(): array
    {
        return $this->lineItemCustomDuties;
    }

    /**
     * @return CustomsDutyLineItemStruct[]
     */
    public function getLineItemCustomDuty(string $lineItemId): array
    {
        return $this->lineItemCustomDuties[$lineItemId] ?? [];
    }
}
