<?php declare(strict_types=1);

namespace PhallosanCustomizations\Migration;

use Doctrine\DBAL\Connection;
use PhallosanCustomizations\Migration\Trait\MigrationTrait;
use PhallosanCustomizations\PhallosanConstants;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class Migration1742401479CustomFields extends MigrationStep
{
    use MigrationTrait;

    final public const CUSTOM_FIELDS = [
        [
            'name' => 'product_custom_fields',
            'config' => [
                'label' => [
                    'de-DE' => 'Produkt Zusatzfelder',
                ],
                'translated' => false,
            ],
            'relations' => [
                [
                    'entityName' => ProductDefinition::ENTITY_NAME,
                ],
            ],
            'customFields' => [
                [
                    'name' => PhallosanConstants::CUSTOM_FIELD_PRODUCT_POSITION,
                    'type' => CustomFieldTypes::INT,
                    'config' => [
                        'customFieldType' => 'number',
                        'customFieldPosition' => 0,
                        'label' => [
                            'de-DE' => 'Position im Produktslider',
                            'en-GB' => 'Position in the Slider',
                        ],
                        'componentName' => 'sw-field',
                        'type' => 'number',
                        'numberType' => 'int',
                        'min' => 0,
                    ],
                ],
                [
                    'name' => PhallosanConstants::CUSTOM_FIELD_PRODUCT_PRODUCTBOX_BUNDLE_LINK,
                    'type' => CustomFieldTypes::HTML,
                    'config' => [
                        'customFieldType' => 'text',
                        'customFieldPosition' => 1,
                        'label' => [
                            'de-DE' => 'Produktbox Bundle Verlinkung',
                            'en-GB' => 'Productbox Bundle link',
                        ],
                        'componentName' => 'sw-field',
                        'type' => 'text',
                    ],
                ],
                [
                    'name' => PhallosanConstants::CUSTOM_FIELD_PRODUCT_PRODUCTBOX_IMAGE_SIZE,
                    'type' => CustomFieldTypes::BOOL,
                    'config' => [
                        'customFieldType' => 'checkbox',
                        'customFieldPosition' => 2,
                        'label' => [
                            'de-DE' => 'Produktbox Bild Darstellung Cover',
                            'en-GB' => 'Productbox Image cover display',
                        ],
                        'componentName' => 'sw-field',
                        'type' => 'checkbox',
                    ],
                ],
                [
                    'name' => PhallosanConstants::CUSTOM_FIELD_PRODUCT_PRODUCTBOX_VIDEO,
                    'type' => CustomFieldTypes::MEDIA,
                    'config' => [
                        'customFieldType' => 'media',
                        'customFieldPosition' => 3,
                        'label' => [
                            'de-DE' => 'Produktbox Video',
                            'en-GB' => 'Productbox Video',
                        ],
                        'componentName' => 'sw-media-field',
                    ],
                ],
                [
                    'name' => PhallosanConstants::CUSTOM_FIELD_PRODUCT_PRODUCTBOX_TEXT,
                    'type' => CustomFieldTypes::HTML,
                    'config' => [
                        'customFieldType' => 'textEditor',
                        'customFieldPosition' => 4,
                        'label' => [
                            'de-DE' => 'Produktbox Text',
                            'en-GB' => 'Productbox Text',
                        ],
                        'componentName' => 'sw-text-editor',
                    ],
                ],
                [
                    'name' => PhallosanConstants::CUSTOM_FIELD_PRODUCT_PRODUCTBOX_LIST,
                    'type' => CustomFieldTypes::HTML,
                    'config' => [
                        'customFieldType' => 'textEditor',
                        'customFieldPosition' => 5,
                        'label' => [
                            'de-DE' => 'Produktbox Liste',
                            'en-GB' => 'Productbox List',
                        ],
                        'componentName' => 'sw-text-editor',
                    ],
                ],
                [
                    'name' => PhallosanConstants::CUSTOM_FIELD_PRODUCT_HEADLINE,
                    'type' => CustomFieldTypes::HTML,
                    'config' => [
                        'customFieldType' => 'text',
                        'customFieldPosition' => 6,
                        'label' => [
                            'de-DE' => 'Überschrift',
                            'en-GB' => 'Headline',
                        ],
                        'componentName' => 'sw-field',
                        'type' => 'text',
                    ],
                ],
                [
                    'name' => PhallosanConstants::CUSTOM_FIELD_PRODUCT_BUYBOX_LIST,
                    'type' => CustomFieldTypes::HTML,
                    'config' => [
                        'customFieldType' => 'textEditor',
                        'customFieldPosition' => 7,
                        'label' => [
                            'de-DE' => 'Buybox Liste',
                            'en-GB' => 'Buybox List',
                        ],
                        'componentName' => 'sw-text-editor',
                    ],
                ],
                [
                    'name' => PhallosanConstants::CUSTOM_FIELD_PRODUCT_NOTE,
                    'type' => CustomFieldTypes::HTML,
                    'config' => [
                        'customFieldType' => 'text',
                        'customFieldPosition' => 8,
                        'label' => [
                            'de-DE' => 'Hinweis',
                            'en-GB' => 'Note',
                        ],
                        'componentName' => 'sw-field',
                        'type' => 'text',
                    ],
                ],
                [
                    'name' => PhallosanConstants::CUSTOM_FIELD_PRODUCT_CHECK_ORDERNUMBER,
                    'type' => CustomFieldTypes::BOOL,
                    'config' => [
                        'customFieldType' => 'checkbox',
                        'customFieldPosition' => 9,
                        'label' => [
                            'de-DE' => 'Bestellnummer überprüfen',
                            'en-GB' => 'Check Ordernumber',
                        ],
                        'helpText' => [
                            'de-DE' => 'Wenn diese Option aktiviert ist, darf der Artikel nur gekauft werden, wenn eine vorherige Bestellung mit einem bestimmten Produkt gemacht worden ist',
                            'en-GB' => 'If this option is activated, the item may only be purchased if a previous order has been placed with a specific product',
                        ],
                        'componentName' => 'sw-field',
                        'type' => 'checkbox',
                    ],
                ],
                [
                    'name' => PhallosanConstants::CUSTOM_FIELD_PRODUCT_NO_DETAIL,
                    'type' => CustomFieldTypes::BOOL,
                    'config' => [
                        'customFieldType' => 'checkbox',
                        'customFieldPosition' => 10,
                        'label' => [
                            'de-DE' => 'Keine Detailseite',
                            'en-GB' => 'No Detail-Page',
                        ],
                        'placeholder' => [
                            'de-DE' => 'Artikeldetailseite für diesen Artikel nicht anzeigen',
                            'en-GB' => 'Do not show item detail page for this product',
                        ],
                        'componentName' => 'sw-field',
                        'type' => 'checkbox',
                    ],
                ],
                [
                    'name' => PhallosanConstants::CUSTOM_FIELD_PRODUCT_CATEGORY_REDIRECT,
                    'type' => CustomFieldTypes::SELECT,
                    'config' => [
                        'customFieldType' => 'entity',
                        'customFieldPosition' => 11,
                        'entity' => 'category',
                        'label' => [
                            'de-DE' => 'Weiterleitung auf diese Kategorie, wenn es keine Detailseite für diesen Artikel gibt',
                            'en-GB' => 'Redirect to this Category, if the Product has no Detail Page',
                        ],
                        'componentName' => 'sw-entity-single-select',
                        'type' => 'entity',
                    ],
                ],
                [
                    'name' => PhallosanConstants::CUSTOM_FIELD_PRODUCT_BULK_PRICES,
                    'type' => CustomFieldTypes::BOOL,
                    'config' => [
                        'customFieldType' => 'checkbox',
                        'customFieldPosition' => 12,
                        'label' => [
                            'de-DE' => 'Staffelpreise (Packungen)',
                            'en-GB' => 'Bulk prices (Packages)',
                        ],
                        'componentName' => 'sw-field',
                        'type' => 'checkbox',
                    ],
                ],
            ],
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1742401479;
    }

    public function update(Connection $connection): void
    {
        $this->createOrUpdateCustomFields(self::CUSTOM_FIELDS, $connection);
    }
}
