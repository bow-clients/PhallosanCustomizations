// Modules
import './module/accessory-requirement';
import './module/sw-order/component/sw-order-line-items-grid'
import './component/hscode-modal';

import './module/sw-order';
import './extension/return-line-items-grid-extension';
import './module/sw-order/page/sw-order-detail';
import './module/sw-order/page/sw-order-list';
import './module/sw-order-state/component/delivery-status.plugin';
import './module/sw-order/component/sw-order-select-document-type-modal';
import './module/sw-customer/component/sw-customer-base-info';
import './component/sw-settings-tax-rule-modal';
import './component/sw-tax-rule-card';
import './app/filter/remove-doc-types.filter'


//Added third parameter "priority" to load template override after swag commercial
Shopware.Component.override('sw-customer-base-info', () => import('./module/sw-customer/component/sw-customer-base-info'), 100);
