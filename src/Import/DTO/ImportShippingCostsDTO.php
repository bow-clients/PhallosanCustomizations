<?php declare(strict_types=1);

namespace PhallosanCustomizations\Import\DTO;

use Symfony\Component\Serializer\Annotation\SerializedName;

class ImportShippingCostsDTO
{
    public function __construct(
        #[SerializedName('countries_name')]
        private string $countryName,
        #[SerializedName('countries_iso_code_2')]
        private string $countryIsoCode2,
        #[SerializedName('countries_iso_code_3')]
        private string $countryIsoCode3,
        #[SerializedName('Versandkosten_Phallosan_fort')]
        private float $mainProductShippingCosts,
        #[SerializedName('Versandkosten Nebenprodukt')]
        private float $minorProductShippingCosts,
    ) {
    }

    public function getCountryName(): string
    {
        return $this->countryName;
    }

    public function getCountryIsoCode2(): string
    {
        return $this->countryIsoCode2;
    }

    public function getCountryIsoCode3(): string
    {
        return $this->countryIsoCode3;
    }

    public function getMainProductShippingPrice(): float
    {
        return $this->mainProductShippingCosts;
    }

    public function getMinorProductShippingPrice(): float
    {
        return $this->minorProductShippingCosts;
    }
}
