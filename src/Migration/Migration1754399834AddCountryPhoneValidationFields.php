<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal
 */
class Migration1754399834AddCountryPhoneValidationFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1754399834;
    }

    public function update(Connection $connection): void
    {
        $customFieldSetId = Uuid::randomBytes();

        // Custom Field Set Config
        $customFieldSetConfig = [
            'label' => [
                'en-GB' => 'Phone Validation Settings',
                'de-DE' => 'Telefonvalidierungs-Einstellungen',
                'fr-FR' => 'Paramètres de validation du téléphone',
                'es-ES' => 'Configuración de validación de teléfono',
                'it-IT' => 'Impostazioni di validazione telefono',
                'nl-NL' => 'Telefoonvalidatie-instellingen',
                'pl-PL' => 'Ustawienia walidacji telefonu',
                'pt-PT' => 'Configurações de validação de telefone',
                'ru-RU' => 'Настройки валидации телефона',
                'zh-CN' => '电话验证设置',
                'ja-JP' => '電話番号検証設定',
            ],
        ];

        // Custom Field Set erstellen
        $connection->insert('custom_field_set', [
            'id' => $customFieldSetId,
            'name' => 'country_phone_validation',
            'config' => json_encode($customFieldSetConfig),
            'active' => 1,
            'position' => 1,
            'app_id' => null,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        // Custom Field Set Relationen zu Country Entity
        $connection->insert('custom_field_set_relation', [
            'id' => Uuid::randomBytes(),
            'set_id' => $customFieldSetId,
            'entity_name' => 'country',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        // Custom Field für Aktiv-Schalter
        $phoneValidationActiveId = Uuid::randomBytes();
        $connection->insert('custom_field', [
            'id' => $phoneValidationActiveId,
            'name' => 'custom_phone_validation_active',
            'type' => CustomFieldTypes::BOOL,
            'config' => json_encode([
                'label' => [
                    'en-GB' => 'Phone validation active',
                    'de-DE' => 'Telefonvalidierung aktiv',
                    'fr-FR' => 'Validation du téléphone active',
                    'es-ES' => 'Validación de teléfono activa',
                    'it-IT' => 'Validazione telefono attiva',
                    'nl-NL' => 'Telefoonvalidatie actief',
                    'pl-PL' => 'Walidacja telefonu aktywna',
                    'pt-PT' => 'Validação de telefone ativa',
                    'ru-RU' => 'Валидация телефона активна',
                    'zh-CN' => '电话验证激活',
                    'ja-JP' => '電話番号検証有効',
                ],
                'helpText' => [
                    'en-GB' => 'Enable phone number validation for this country',
                    'de-DE' => 'Telefonnummernvalidierung für dieses Land aktivieren',
                    'fr-FR' => 'Activer la validation du numéro de téléphone pour ce pays',
                    'es-ES' => 'Habilitar la validación del número de teléfono para este país',
                    'it-IT' => 'Abilita la validazione del numero di telefono per questo paese',
                    'nl-NL' => 'Telefoonnummervalidatie voor dit land inschakelen',
                    'pl-PL' => 'Włącz walidację numeru telefonu dla tego kraju',
                    'pt-PT' => 'Ativar validação de número de telefone para este país',
                    'ru-RU' => 'Включить проверку номера телефона для этой страны',
                    'zh-CN' => '为该国家启用电话号码验证',
                    'ja-JP' => 'この国の電話番号検証を有効にする',
                ],
                'componentName' => 'sw-field',
                'customFieldType' => 'switch',
                'customFieldPosition' => 1,
            ]),
            'active' => 1,
            'set_id' => $customFieldSetId,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'updated_at' => null,
        ]);

        // Custom Field für REGEX
        $phoneValidationRegexId = Uuid::randomBytes();
        $connection->insert('custom_field', [
            'id' => $phoneValidationRegexId,
            'name' => 'custom_phone_validation_regex',
            'type' => CustomFieldTypes::TEXT,
            'config' => json_encode([
                'label' => [
                    'en-GB' => 'Phone validation regex pattern',
                    'de-DE' => 'Telefonnummer Regex-Muster',
                    'fr-FR' => 'Modèle regex de validation du téléphone',
                    'es-ES' => 'Patrón regex de validación de teléfono',
                    'it-IT' => 'Pattern regex validazione telefono',
                    'nl-NL' => 'Telefoonvalidatie regex patroon',
                    'pl-PL' => 'Wzorzec regex walidacji telefonu',
                    'pt-PT' => 'Padrão regex de validação de telefone',
                    'ru-RU' => 'Regex шаблон валидации телефона',
                    'zh-CN' => '电话验证正则表达式模式',
                    'ja-JP' => '電話番号検証の正規表現パターン',
                ],
                'helpText' => [
                    'en-GB' => 'Regular expression pattern for phone number validation (e.g. ^\\+49[0-9]{10,11}$ for German numbers)',
                    'de-DE' => 'Regulärer Ausdruck für die Telefonnummernvalidierung (z.B. ^\\+49[0-9]{10,11}$ für deutsche Nummern)',
                    'fr-FR' => 'Expression régulière pour la validation du numéro de téléphone',
                    'es-ES' => 'Expresión regular para la validación del número de teléfono',
                    'it-IT' => 'Espressione regolare per la validazione del numero di telefono',
                    'nl-NL' => 'Reguliere expressie voor telefoonnummervalidatie',
                    'pl-PL' => 'Wyrażenie regularne do walidacji numeru telefonu',
                    'pt-PT' => 'Expressão regular para validação de número de telefone',
                    'ru-RU' => 'Регулярное выражение для валидации номера телефона',
                    'zh-CN' => '用于电话号码验证的正则表达式',
                    'ja-JP' => '電話番号検証用の正規表現',
                ],
                'placeholder' => [
                    'en-GB' => '^\\+[0-9]{1,3}[0-9]{4,14}$',
                    'de-DE' => '^\\+[0-9]{1,3}[0-9]{4,14}$',
                ],
                'componentName' => 'sw-field',
                'customFieldType' => 'text',
                'customFieldPosition' => 2,
                // Sichtbarkeit abhängig vom Aktiv-Schalter
                'displayCondition' => [
                    'type' => 'equals',
                    'field' => 'custom_phone_validation_active',
                    'value' => true,
                ],
            ]),
            'active' => 1,
            'set_id' => $customFieldSetId,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'updated_at' => null,
        ]);
    }
}
