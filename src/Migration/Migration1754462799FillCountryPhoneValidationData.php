<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1754462799FillCountryPhoneValidationData extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1754462799;
    }

    public function update(Connection $connection): void
    {
        $countryPhonePatterns = [
            // Europa
            'DE' => '^\\+49[1-9][0-9]{1,14}$', // Deutschland
            'AT' => '^\\+43[1-9][0-9]{3,12}$', // Österreich
            'CH' => '^\\+41[1-9][0-9]{8}$', // Schweiz
            'FR' => '^\\+33[1-9][0-9]{8}$', // Frankreich
            'IT' => '^\\+39[0-9]{6,12}$', // Italien
            'ES' => '^\\+34[6-9][0-9]{8}$', // Spanien
            'PT' => '^\\+351[0-9]{9}$', // Portugal
            'NL' => '^\\+31[1-9][0-9]{8}$', // Niederlande
            'BE' => '^\\+32[1-9][0-9]{7,8}$', // Belgien
            'LU' => '^\\+352[0-9]{8,9}$', // Luxemburg
            'GB' => '^\\+44[1-9][0-9]{9,10}$', // Großbritannien
            'IE' => '^\\+353[1-9][0-9]{7,8}$', // Irland
            'DK' => '^\\+45[2-9][0-9]{7}$', // Dänemark
            'SE' => '^\\+46[1-9][0-9]{7,9}$', // Schweden
            'NO' => '^\\+47[2-9][0-9]{7}$', // Norwegen
            'FI' => '^\\+358[1-9][0-9]{7,8}$', // Finnland
            'PL' => '^\\+48[0-9]{9}$', // Polen
            'CZ' => '^\\+420[0-9]{9}$', // Tschechien
            'SK' => '^\\+421[0-9]{9}$', // Slowakei
            'HU' => '^\\+36[0-9]{8,9}$', // Ungarn
            'RO' => '^\\+40[0-9]{9}$', // Rumänien
            'BG' => '^\\+359[0-9]{8,9}$', // Bulgarien
            'GR' => '^\\+30[0-9]{10}$', // Griechenland
            'HR' => '^\\+385[0-9]{8,9}$', // Kroatien
            'SI' => '^\\+386[0-9]{8}$', // Slowenien
            'EE' => '^\\+372[0-9]{7,8}$', // Estland
            'LV' => '^\\+371[0-9]{8}$', // Lettland
            'LT' => '^\\+370[0-9]{8}$', // Litauen
            'MT' => '^\\+356[0-9]{8}$', // Malta
            'CY' => '^\\+357[0-9]{8}$', // Zypern
            'IS' => '^\\+354[0-9]{7}$', // Island
            'LI' => '^\\+423[0-9]{7}$', // Liechtenstein
            'MC' => '^\\+377[0-9]{8,9}$', // Monaco
            'SM' => '^\\+378[0-9]{6,10}$', // San Marino
            'VA' => '^\\+39[0-9]{6,12}$', // Vatikanstadt
            'AD' => '^\\+376[0-9]{6}$', // Andorra
            'AL' => '^\\+355[0-9]{8,9}$', // Albanien
            'BA' => '^\\+387[0-9]{8}$', // Bosnien und Herzegowina
            'XK' => '^\\+383[0-9]{8}$', // Kosovo
            'ME' => '^\\+382[0-9]{8}$', // Montenegro
            'MK' => '^\\+389[0-9]{8}$', // Nordmazedonien
            'RS' => '^\\+381[0-9]{8,9}$', // Serbien
            'BY' => '^\\+375[0-9]{9}$', // Belarus
            'MD' => '^\\+373[0-9]{8}$', // Moldawien
            'UA' => '^\\+380[0-9]{9}$', // Ukraine
            'RU' => '^\\+7[0-9]{10}$', // Russland

            // Nordamerika
            'US' => '^\\+1[2-9][0-9]{9}$', // USA
            'CA' => '^\\+1[2-9][0-9]{9}$', // Kanada
            'MX' => '^\\+52[0-9]{10}$', // Mexiko

            // Mittelamerika & Karibik
            'GT' => '^\\+502[0-9]{8}$', // Guatemala
            'BZ' => '^\\+501[0-9]{7}$', // Belize
            'SV' => '^\\+503[0-9]{8}$', // El Salvador
            'HN' => '^\\+504[0-9]{8}$', // Honduras
            'NI' => '^\\+505[0-9]{8}$', // Nicaragua
            'CR' => '^\\+506[0-9]{8}$', // Costa Rica
            'PA' => '^\\+507[0-9]{8}$', // Panama
            'CU' => '^\\+53[0-9]{8}$', // Kuba
            'DO' => '^\\+1809[0-9]{7}$|^\\+1829[0-9]{7}$|^\\+1849[0-9]{7}$', // Dominikanische Republik
            'HT' => '^\\+509[0-9]{8}$', // Haiti
            'JM' => '^\\+1876[0-9]{7}$', // Jamaika
            'PR' => '^\\+1787[0-9]{7}$|^\\+1939[0-9]{7}$', // Puerto Rico
            'TT' => '^\\+1868[0-9]{7}$', // Trinidad und Tobago
            'BB' => '^\\+1246[0-9]{7}$', // Barbados
            'BS' => '^\\+1242[0-9]{7}$', // Bahamas

            // Südamerika
            'AR' => '^\\+54[0-9]{10,11}$', // Argentinien
            'BR' => '^\\+55[0-9]{10,11}$', // Brasilien
            'CL' => '^\\+56[0-9]{9}$', // Chile
            'CO' => '^\\+57[0-9]{10}$', // Kolumbien
            'EC' => '^\\+593[0-9]{9}$', // Ecuador
            'GY' => '^\\+592[0-9]{7}$', // Guyana
            'PY' => '^\\+595[0-9]{9}$', // Paraguay
            'PE' => '^\\+51[0-9]{9}$', // Peru
            'SR' => '^\\+597[0-9]{6,7}$', // Suriname
            'UY' => '^\\+598[0-9]{8}$', // Uruguay
            'VE' => '^\\+58[0-9]{10}$', // Venezuela
            'BO' => '^\\+591[0-9]{8}$', // Bolivien

            // Asien (wichtigste Länder)
            'CN' => '^\\+86[0-9]{11}$', // China
            'JP' => '^\\+81[0-9]{10,11}$', // Japan
            'KR' => '^\\+82[0-9]{9,10}$', // Südkorea
            'IN' => '^\\+91[6-9][0-9]{9}$', // Indien
            'ID' => '^\\+62[0-9]{9,12}$', // Indonesien
            'TH' => '^\\+66[0-9]{9}$', // Thailand
            'VN' => '^\\+84[0-9]{9,10}$', // Vietnam
            'PH' => '^\\+63[0-9]{10}$', // Philippinen
            'MY' => '^\\+60[0-9]{9,10}$', // Malaysia
            'SG' => '^\\+65[0-9]{8}$', // Singapur
            'HK' => '^\\+852[0-9]{8}$', // Hongkong
            'TW' => '^\\+886[0-9]{9}$', // Taiwan
            'PK' => '^\\+92[0-9]{10}$', // Pakistan
            'BD' => '^\\+880[0-9]{10}$', // Bangladesch
            'LK' => '^\\+94[0-9]{9}$', // Sri Lanka
            'NP' => '^\\+977[0-9]{10}$', // Nepal
            'AF' => '^\\+93[0-9]{9}$', // Afghanistan

            // Naher Osten
            'AE' => '^\\+971[0-9]{8,9}$', // Vereinigte Arabische Emirate
            'SA' => '^\\+966[0-9]{9}$', // Saudi-Arabien
            'QA' => '^\\+974[0-9]{8}$', // Katar
            'KW' => '^\\+965[0-9]{8}$', // Kuwait
            'BH' => '^\\+973[0-9]{8}$', // Bahrain
            'OM' => '^\\+968[0-9]{8}$', // Oman
            'YE' => '^\\+967[0-9]{9}$', // Jemen
            'IQ' => '^\\+964[0-9]{10}$', // Irak
            'IR' => '^\\+98[0-9]{10}$', // Iran
            'IL' => '^\\+972[0-9]{9}$', // Israel
            'JO' => '^\\+962[0-9]{9}$', // Jordanien
            'LB' => '^\\+961[0-9]{7,8}$', // Libanon
            'TR' => '^\\+90[0-9]{10}$', // Türkei

            // Afrika (wichtigste Länder)
            'EG' => '^\\+20[0-9]{10}$', // Ägypten
            'ZA' => '^\\+27[0-9]{9}$', // Südafrika
            'NG' => '^\\+234[0-9]{10}$', // Nigeria
            'KE' => '^\\+254[0-9]{9}$', // Kenia
            'MA' => '^\\+212[0-9]{9}$', // Marokko
            'TN' => '^\\+216[0-9]{8}$', // Tunesien
            'DZ' => '^\\+213[0-9]{9}$', // Algerien

            // Ozeanien
            'AU' => '^\\+61[0-9]{9}$', // Australien
            'NZ' => '^\\+64[0-9]{8,10}$', // Neuseeland
        ];

        $languages = $connection->fetchAllAssociative('SELECT id, locale_id FROM language');

        foreach ($countryPhonePatterns as $iso => $regex) {
            $countryId = $connection->fetchOne('SELECT id FROM country WHERE iso = :iso', ['iso' => $iso]);

            if (!$countryId) {
                continue;
            }

            $customFields = [
                'custom_phone_validation_active' => 1,
                'custom_phone_validation_regex' => $regex,
            ];

            foreach ($languages as $language) {
                $translationExists = $connection->fetchOne(
                    'SELECT COUNT(*) FROM country_translation WHERE country_id = :countryId AND language_id = :languageId',
                    ['countryId' => $countryId, 'languageId' => $language['id']]
                );

                if ($translationExists > 0) {
                    $existingCustomFields = $connection->fetchOne(
                        'SELECT custom_fields FROM country_translation WHERE country_id = :countryId AND language_id = :languageId',
                        ['countryId' => $countryId, 'languageId' => $language['id']]
                    );

                    $mergedCustomFields = [];
                    if ($existingCustomFields) {
                        $mergedCustomFields = json_decode($existingCustomFields, true) ?: [];
                    }
                    $mergedCustomFields = array_merge($mergedCustomFields, $customFields);

                    $connection->executeStatement(
                        'UPDATE country_translation
                         SET custom_fields = :customFields,
                             updated_at = :updatedAt
                         WHERE country_id = :countryId AND language_id = :languageId',
                        [
                            'customFields' => json_encode($mergedCustomFields),
                            'updatedAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                            'countryId' => $countryId,
                            'languageId' => $language['id'],
                        ]
                    );
                } else {
                    $countryName = $connection->fetchOne(
                        'SELECT name FROM country_translation WHERE country_id = :countryId LIMIT 1',
                        ['countryId' => $countryId]
                    );

                    if (!$countryName) {
                        $countryName = $iso;
                    }

                    $connection->insert('country_translation', [
                        'country_id' => $countryId,
                        'language_id' => $language['id'],
                        'name' => $countryName,
                        'custom_fields' => json_encode($customFields),
                        'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    ]);
                }
            }
        }

        $genericPattern = '^\\+[0-9]{1,3}[0-9]{4,14}$';
        $genericCustomFields = [
            'custom_phone_validation_active' => 1,  // Als Integer
            'custom_phone_validation_regex' => $genericPattern,
        ];

        $allCountries = $connection->fetchAllAssociative('SELECT id FROM country WHERE active = 1');

        foreach ($allCountries as $country) {
            foreach ($languages as $language) {
                $translation = $connection->fetchAssociative(
                    'SELECT custom_fields, name FROM country_translation
                     WHERE country_id = :countryId AND language_id = :languageId',
                    ['countryId' => $country['id'], 'languageId' => $language['id']]
                );

                if ($translation !== false) {
                    $currentCustomFields = [];
                    if ($translation['custom_fields']) {
                        $currentCustomFields = json_decode($translation['custom_fields'], true) ?: [];
                    }

                    if (!isset($currentCustomFields['custom_phone_validation_active'])) {
                        $mergedCustomFields = array_merge($currentCustomFields, $genericCustomFields);

                        $connection->executeStatement(
                            'UPDATE country_translation
                             SET custom_fields = :customFields,
                                 updated_at = :updatedAt
                             WHERE country_id = :countryId AND language_id = :languageId',
                            [
                                'customFields' => json_encode($mergedCustomFields),
                                'updatedAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                                'countryId' => $country['id'],
                                'languageId' => $language['id'],
                            ]
                        );
                    }
                } else {
                    $countryData = $connection->fetchAssociative(
                        'SELECT iso, iso3 FROM country WHERE id = :countryId',
                        ['countryId' => $country['id']]
                    );

                    $countryName = $connection->fetchOne(
                        'SELECT name FROM country_translation WHERE country_id = :countryId LIMIT 1',
                        ['countryId' => $country['id']]
                    );

                    if (!$countryName && $countryData) {
                        $countryName = $countryData['iso'] ?: $countryData['iso3'] ?: 'Unknown';
                    }

                    $connection->insert('country_translation', [
                        'country_id' => $country['id'],
                        'language_id' => $language['id'],
                        'name' => $countryName ?: 'Unknown',
                        'custom_fields' => json_encode($genericCustomFields),
                        'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    ]);
                }
            }
        }
    }
}
