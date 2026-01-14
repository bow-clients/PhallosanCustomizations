<?php declare(strict_types=1);

namespace PhallosanCustomizations\Extension\TaxRule\Aggregate;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class TaxRuleExtensionEntity extends Entity
{
    use EntityIdTrait;

    protected string $taxRuleId;

    protected ?float $customsDuty = null;

    protected bool $applyCustomsDuty = false;

    protected ?float $importVat = null;

    protected bool $applyImportVat = false;

    public function getTaxRuleId(): string
    {
        return $this->taxRuleId;
    }

    public function setTaxRuleId(string $taxRuleId): void
    {
        $this->taxRuleId = $taxRuleId;
    }

    public function getCustomsDuty(): ?float
    {
        return $this->customsDuty;
    }

    public function setCustomsDuty(?float $customsDuty): void
    {
        $this->customsDuty = $customsDuty;
    }

    public function getApplyCustomsDuty(): bool
    {
        return $this->applyCustomsDuty;
    }

    public function setApplyCustomsDuty(bool $applyCustomsDuty): void
    {
        $this->applyCustomsDuty = $applyCustomsDuty;
    }

    public function getImportVat(): ?float
    {
        return $this->importVat;
    }

    public function setImportVat(?float $importVat): void
    {
        $this->importVat = $importVat;
    }

    public function getApplyImportVat(): bool
    {
        return $this->applyImportVat;
    }

    public function setApplyImportVat(bool $applyImportVat): void
    {
        $this->applyImportVat = $applyImportVat;
    }
}
