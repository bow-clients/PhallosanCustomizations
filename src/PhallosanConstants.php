<?php declare(strict_types=1);

namespace PhallosanCustomizations;

final class PhallosanConstants
{
    /**
     * Config
     */
    final public const PLUGIN_PREFIX = 'PhallosanCustomizations.config.';

    final public const PLUGIN_CONFIG_DEFAULT_NO_DETAIL_REDIRECT = self::PLUGIN_PREFIX . 'defaultNoDetailRedirect';
    final public const PLUGIN_CONFIG_CMS_FACTORY_ELEMENT_REVIEW = self::PLUGIN_PREFIX . 'cmsFactoryElementReview';
    final public const PLUGIN_CONFIG_ACCESS_KEY = self::PLUGIN_PREFIX . 'accessKey';
    final public const PLUGIN_CONFIG_SEO_URL_LANGUAGE = self::PLUGIN_PREFIX . 'seoUrlLanguage';
    final public const PLUGIN_CONFIG_AFFILIATE_GROUPS = self::PLUGIN_PREFIX . 'affiliateGroupRedirect';
    final public const PLUGIN_CONFIG_PATENT_EXPORT_PRODUCT = self::PLUGIN_PREFIX . 'patentExportProduct';
    final public const PLUGIN_CONFIG_MONTHLY_EU_SALES_TAX_OFFICE = self::PLUGIN_PREFIX . 'taxOfficeSalesEuLastExport';
    final public const PLUGIN_CONFIG_MONTHLY_WORLD_SALES_TAX_OFFICE = self::PLUGIN_PREFIX . 'taxOfficeSalesWorldLastExport';
    final public const PLUGIN_CONFIG_MONTHLY_CANCELLATIONS_TAX_OFFICE = self::PLUGIN_PREFIX . 'taxOfficeCancellationsDeLastExport';
    final public const PLUGIN_CONFIG_MONTHLY_WORLD_CANCELLATIONS_TAX_OFFICE = self::PLUGIN_PREFIX . 'taxOfficeCancellationsWorldLastExport';
    final public const PLUGIN_CONFIG_US_HS_CODE_SUFFIX = self::PLUGIN_PREFIX . 'hsCodeSuffix';
    final public const PLUGIN_CONFIG_US_HS_CODE_COUNTRY = self::PLUGIN_PREFIX . 'hsCodeSuffixCountry';

    final public const EXTENSION_SALES_CHANNEL_COUNTRY = 'salesChannelCountry';
    final public const SESSION_SALES_CHANNEL_COUNTRY = self::EXTENSION_SALES_CHANNEL_COUNTRY;
    final public const SESSION_SALES_CHANNEL_COUNTRY_ID = 'salesChannelCountryId';
    final public const SESSION_SALES_CHANNEL_COUNTRY_IS_EU = 'salesChannelCountryIsEu';

    /**
     * CustomFields
     */

    /**
     * @deprecated
     */
    final public const CUSTOM_FIELD_HS_CODE = 'product_hs_code';
    final public const CUSTOM_FIELD_HS_CODE_US = 'product_hs_code_us';
    final public const CUSTOM_FIELD_HS_CODE_EU = 'product_hs_code_eu';

    final public const CUSTOM_FIELD_ORDER_PACKAGING_INSTRUCTIONS = 'order_packaging_instructions';
    final public const CUSTOM_FIELD_ORDER_FINECOM_ERROR = 'order_finecom_error';
    final public const CUSTOM_FIELD_CUSTOMER_INTERNAL_NOTE = 'customer_internal_note';

    /**
     * Int: Position in the Produktslider
     */
    final public const CUSTOM_FIELD_PRODUCT_POSITION = 'product_custom_fields_position';

    /**
     * HTML: Productbox Bundle link
     */
    final public const CUSTOM_FIELD_PRODUCT_PRODUCTBOX_BUNDLE_LINK = 'product_custom_fields_productbox_link_bundle';

    /**
     * Entity: Product Bundle link
     */
    final public const CUSTOM_FIELD_PRODUCT_PRODUCTBUNDLE_LINK = 'product_custom_fields_product_link_productbundle';

    /**
     * Bool: Product Box Image cover display
     */
    final public const CUSTOM_FIELD_PRODUCT_PRODUCTBOX_IMAGE_SIZE = 'product_custom_fields_productbox_image_size';

    /**
     * Media: Product box Video
     */
    final public const CUSTOM_FIELD_PRODUCT_PRODUCTBOX_VIDEO = 'product_custom_fields_productbox_video';

    /**
     * HTML: Product box Text
     */
    final public const CUSTOM_FIELD_PRODUCT_PRODUCTBOX_TEXT = 'product_custom_fields_productbox_text';

    /**
     * HTML: Product box List
     */
    final public const CUSTOM_FIELD_PRODUCT_PRODUCTBOX_LIST = 'product_custom_fields_productbox_list';

    /**
     * HTML: Headline
     */
    final public const CUSTOM_FIELD_PRODUCT_HEADLINE = 'product_custom_fields_headline';

    /**
     * HTML: Buybox List
     */
    final public const CUSTOM_FIELD_PRODUCT_BUYBOX_LIST = 'product_custom_fields_buybox_list';

    /**
     * HTML: Note
     */
    final public const CUSTOM_FIELD_PRODUCT_NOTE = 'product_custom_fields_note';

    /**
     * Bool: Check Ordernumber (e.g. "phallosan plus" can only be bought if "phallosan forte" was bought previously)
     */
    final public const CUSTOM_FIELD_PRODUCT_CHECK_ORDERNUMBER = 'product_custom_fields_ordernumber_check';

    /**
     * Bool: No Detail Page
     */
    final public const CUSTOM_FIELD_PRODUCT_NO_DETAIL = 'product_custom_fields_no_detail';

    /**
     * Select: Redirect to this Category (if the pdp is called)
     */
    final public const CUSTOM_FIELD_PRODUCT_CATEGORY_REDIRECT = 'product_custom_fields_category_redirect';

    /**
     * Bool: Bulk Prices
     */
    final public const CUSTOM_FIELD_PRODUCT_BULK_PRICES = 'product_custom_fields_bulk_prices';

    /**
     * Entity: Country
     */
    final public const CUSTOM_FIELD_PRODUCT_COUNTRY = 'product_custom_fields_country';

    /**
     * HTML: Shipping Note
     */
    final public const CUSTOM_FIELD_PRODUCT_SHIPPING_NOTE = 'product_custom_shipping_note';

    /**
     * BOOL: Show Unit Price
     */
    final public const CUSTOM_FIELD_PRODUCT_SHOW_UNIT_PRICE = 'product_custom_show_unit_price';

    /**
     * BOOL: Bundle Produkt
     */
    final public const CUSTOM_FIELD_PRODUCT_BUNDLE_PRODUCT = 'product_custom_bundle_product';

    /**
     * TEXT: Add to Cart Link
     */
    final public const CUSTOM_FIELD_PRODUCT_ADD_TO_CART = 'product_custom_add_to_cart';

    /**
     * Media: Bundle Cover (EU)
     */
    final public const CUSTOM_FIELD_PRODUCT_BUNDLE_COVER_EU = 'product_custom_bundle_cover_eu';

    /**
     * TEXT: PackageUnit for FineCom
     */
    final public const CUSTOM_FIELD_PRODUCT_PACKAGE_UNIT_FINECOM = 'product_package_unit_finecom';

    /**
     * TEXT: Product Name in Documents
     */
    final public const CUSTOM_FIELD_DOCUMENTS_PRODUCT_NAME = 'product_documents_custom_fields_name';

    /**
     * TEXT: Product Name in Documents (non-EU)
     */
    final public const CUSTOM_FIELD_DOCUMENTS_PRODUCT_NAME_US = 'product_documents_custom_fields_name_us';

    /**
     * TEXT: Product Number
     */
    final public const CUSTOM_FIELD_US_PRODUCT_PRODUCT_NUMBER = 'us_product_custom_fields_product_number';

    /**
     * Entity: Product
     */
    final public const CUSTOM_FIELD_PRODUCT_ALTERNATIVE_PRODUCT = 'product_custom_fields_alternative_product';

    /**
     * TEXT: EAN
     */
    final public const CUSTOM_FIELD_US_PRODUCT_EAN = 'us_product_custom_fields_ean';

    /**
     * BOOL: Affiliate Customer Group
     */
    final public const CUSTOM_FIELD_CUSTOMER_GROUP_AFFILIATE = 'customer_group_affiliate';

    /**
     * HTML: SEO URL Customer Group
     */
    final public const CUSTOM_FIELD_CUSTOMER_GROUP_SEO_URL = 'customer_group_seo_url';

    /**
     * BOOL: Show only in EU
     */
    final public const CUSTOM_FIELD_CATEGORY_SHOW_ONLY_IN_EU = 'category_custom_field_show_only_in_eu';

    /**
     * HTML: Invoice Text
     */
    final public const CUSTOM_FIELD_SALES_CHANNEL_INVOICE_TEXT = 'sales_channel_custom_fields_text_invoice';

    /**
     * Tags
     * Equal to `Uuid::fromStringToHex(PhallosanConstants::TAG_FINECOM_FETCHABLE)`
     */
    final public const TAG_FINECOM_FETCHABLE = 'FINECOM_FETCHABLE';
    final public const TAG_FINECOM_FETCHABLE_ID = '3c8e96fa27d46ff0cf857a7b1fefbc84';
    final public const TAG_FINECOM_FETCHED = 'FINECOM_FETCHED';
    final public const TAG_FINECOM_FETCHED_ID = '24f13bf9ab5997b387b56e29f979df52';
    final public const TAG_DELIVERY_INCOMPLETE = 'DELIVERY_INCOMPLETE';
    final public const TAG_DELIVERY_INCOMPLETE_ID = 'ef46aa48d69b6afa67b5b2735393f5f5';
    final public const TAG_RETURNED = 'RETURNED';
    final public const TAG_RETURNED_ID = '130bf9fe94183caf7b1ac9548f2e5166';
    final public const TAG_ERROR = 'ERROR';
    final public const TAG_ERROR_ID = 'bb1ca97ec761fc37101737ba0aa2e7c5';
}
