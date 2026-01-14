<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use PhallosanCustomizations\Migration\Trait\MigrationTrait;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1748330806NonEuropeanCountriesNoTax extends MigrationStep
{
    use MigrationTrait;

    public function getCreationTimestamp(): int
    {
        return 1748330806;
    }

    public function update(Connection $connection): void
    {
        $europeanCountryNames = [
            'Austria' => 'EU',
            'Belgium' => 'EU',
            'Bulgaria' => 'EU',
            'Czech Republic' => 'EU',
            'Croatia' => 'EU',
            'Cyprus' => 'EU',
            'Denmark' => 'EU',
            'Estonia' => 'EU',
            'Finland' => 'EU',
            'France' => 'EU',
            'Germany' => 'EU',
            'Greece' => 'EU',
            'Hungary' => 'EU',
            'Italy' => 'EU',
            'Ireland' => 'EU',
            'Jersey' => 'EU',
            'Luxembourg' => 'EU',
            'Latvia' => 'EU',
            'Liechtenstein' => 'CH',
            'Lithuania' => 'EU',
            'Malta' => 'EU',
            'Monaco' => 'EU',
            'Netherlands' => 'EU',
            'Portugal' => 'EU',
            'Poland' => 'EU',
            'Romania' => 'EU',
            'Slovakia (Slovak Republic)' => 'EU',
            'Spain' => 'EU',
            'Sweden' => 'EU',
            'Switzerland' => 'CH',
            'Slovenia' => 'EU',
            'San Marino' => 'EU',
            'Vatican City State (Holy See)' => 'EU',
        ];

        $countryNames = array_keys($europeanCountryNames);
        $europeanCountries = $this->getCountryByName($connection, $countryNames);
        $europeanCountries = array_flip(array_column($europeanCountries, 'country_id'));

        $countries = $connection->fetchAllAssociative(
            'SELECT `id`, `is_eu`, `customer_tax`, `company_tax`, `iso` FROM `country`'
        );

        foreach ($countries as $country) {
            $customerTaxFree = json_decode($country['customer_tax'], true, 512, \JSON_THROW_ON_ERROR);
            $companyTaxFree = json_decode($country['company_tax'], true, 512, \JSON_THROW_ON_ERROR);
            if (\array_key_exists($country['id'], $europeanCountries)) {
                $customerTaxFree['enabled'] = 0;
                $companyTaxFree['enabled'] = 0;
            } else {
                $customerTaxFree['enabled'] = 1;
                $companyTaxFree['enabled'] = 1;
            }


            $connection->update(
                'country',
                [
                    'customer_tax' => json_encode($customerTaxFree, \JSON_THROW_ON_ERROR),
                    'company_tax' => json_encode($companyTaxFree, \JSON_THROW_ON_ERROR),
                ],
                ['id' => $country['id']]
            );
        }
    }
}
