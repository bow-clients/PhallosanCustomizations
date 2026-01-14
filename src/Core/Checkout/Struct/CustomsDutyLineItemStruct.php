<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Checkout\Struct;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Util\FloatComparator;

class CustomsDutyLineItemStruct extends Struct
{
    protected float $customsDuty;

    protected float $customsDutyTotal;

    public function __construct(
        protected string $productId,
        protected string $taxRuleId,
        float $customsDuty,
        ?float $customsDutyTotal = null
    ) {
        $this->customsDuty = FloatComparator::cast($customsDuty);
        $this->customsDutyTotal = FloatComparator::cast($customsDutyTotal ?? 0);
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getTaxRuleId(): string
    {
        return $this->taxRuleId;
    }

    public function getCustomsDuty(): float
    {
        return FloatComparator::cast($this->customsDuty);
    }

    public function setCustomsDutyTotal(float $total): void
    {
        $this->customsDutyTotal = $total;
    }

    public function getCustomsDutyTotal(): float
    {
        return FloatComparator::cast($this->customsDutyTotal);
    }
}
