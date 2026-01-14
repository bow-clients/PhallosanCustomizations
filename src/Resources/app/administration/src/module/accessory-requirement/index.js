
// Components
Shopware.Component.register('accessory-requirement-sidebar-menu', () => import('./component/accessory-requirement-sidebar-menu'));

// Pages
Shopware.Component.register('accessory-requirement-list', () => import('./page/accessory-requirement-list'))
Shopware.Component.register('accessory-requirement-detail', () => import('./page/accessory-requirement-detail'))
Shopware.Component.extend('accessory-requirement-create', 'accessory-requirement-detail', () => import('./page/accessory-requirement-create'))
Shopware.Component.register('accessory-requirement-order', () => import('./page/accessory-requirement-order'))
Shopware.Component.register('accessory-requirement-order-list', () => import('./page/accessory-requirement-order-list'))
Shopware.Component.register('accessory-requirement-order-detail', () => import('./page/accessory-requirement-order-detail'))
Shopware.Component.extend('accessory-requirement-order-create', 'accessory-requirement-order-detail', () => import('./page/accessory-requirement-order-create'))

// Locales
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);

// Add new CustomField Entities
Shopware.Component.override('sw-admin-menu', {
    inject: [
        'customFieldDataProviderService'
    ],

    mounted: function() {
        this.customFieldDataProviderService.addEntityName('accessory_requirement');
        this.customFieldDataProviderService.addEntityName('accessory_requirement_order');
    }
});

// Add Administration Module

Shopware.Module.register('accessory-requirement', {
    type: 'core',
    name: 'AccessoryRequirement',
    title: 'accessory-requirement.mainMenuItem',
    description: 'accessory-requirement.descriptionTextModule',
    color: '#39fd94',
    icon: 'default-basic-shape-heart',

    routes: {
        list: {
            component: 'accessory-requirement-list',
            path: 'list',
            meta: {
                privilege: 'accessory-requirement.viewer',
                appSystem: {
                    view: 'list',
                },
            },
        },
        detail: {
            component: 'accessory-requirement-detail',
            path: 'detail/:id',
            meta: {

                privilege: 'accessory-requirement.editor',
                parentPath: 'accessory.requirement.list'
            }
        },
        create: {
            component: 'accessory-requirement-create',
            path: 'create',
            meta: {
                privilege: 'accessory-requirement.editor',
                parentPath: 'accessory.requirement.list'
            }
        },
        order_list: {
            component: 'accessory-requirement-order-list',
            path: 'order/list',
            meta: {
                privilege: 'accessory-requirement.viewer',
            }
        },
        order_create: {
            component: 'accessory-requirement-order-create',
            path: 'order/create',
            meta: {
                privilege: 'accessory-requirement.viewer',
            }
        },
        order_detail: {
            component: 'accessory-requirement-order-detail',
            path: 'order/detail/:id',
            meta: {
                privilege: 'accessory-requirement.viewer',
            }
        }
    },

    navigation: [{
        id: 'accessory-requirement',
        path: 'accessory.requirement.list',
        label: 'accessory-requirement.mainMenuItem',
        parent: 'sw-catalogue',
        color: '#FFD700',
        position: 100,
        privilege: 'accessory-requirement.viewer',
    }],
});
