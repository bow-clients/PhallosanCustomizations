<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Checkout\Struct;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Util\FloatComparator;

class ImportVatLineItemStruct extends Struct
{
    protected float $importVat;

    public function __construct(
        protected string $productId,
        protected string $taxRuleId,
        float $importVat
    ) {
        $this->importVat = FloatComparator::cast($importVat);
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getTaxRuleId(): string
    {
        return $this->taxRuleId;
    }

    public function getImportVat(): float
    {
        return FloatComparator::cast($this->importVat);
    }
}
