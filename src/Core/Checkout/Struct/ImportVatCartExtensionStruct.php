<?php declare(strict_types=1);

namespace PhallosanCustomizations\Core\Checkout\Struct;

use Shopware\Core\Framework\Struct\Struct;

class ImportVatCartExtensionStruct extends Struct
{
    /**
     * @param array<string, ImportVatLineItemStruct[]> $lineItemImportVat
     */
    public function __construct(
        /** @var array<string, ImportVatLineItemStruct[]> */
        protected array $lineItemImportVat
    ) {
    }

    /**
     * @return array<string, ImportVatLineItemStruct[]>
     */
    public function getLineItemImportVat(): array
    {
        return $this->lineItemImportVat;
    }

    /**
     * @return ImportVatLineItemStruct[]
     */
    public function getLineItemImportVats(string $lineItemId): array
    {
        return $this->lineItemImportVat[$lineItemId] ?? [];
    }
}
