import template from './sw-order-detail-general.html.twig';

const {Component, Mixin} = Shopware;
const {mapGetters, mapState} = Shopware.Component.getComponentHelper();
const { Criteria } = Shopware.Data;


// Locales
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);

Component.override('sw-order-detail-general', {
    template,

    compatConfig: Shopware.compatConfig,

    mixins: [
        Mixin.getByName('notification'),
    ],

    inject: [
        'repositoryFactory',
        'customSnippetApiService',
        'swOrderDetailOnSaveAndReload',
        'swOrderDetailOnSaveEdits'
    ],

    props: {
        orderId: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            shippingAddress: undefined,
            billingAddress: undefined,
            formattingShippingAddress: '',
            formattingBillingAddress: '',
            trackingCodes: null,
            showCommissionLogModal: false,
            commissionLogModalString: '',
            pendingCommission: {},
            affiliate: null,
        };
    },

    created() {
        this.createdComponent();
    },

    watch: {
        orderReturns(newValue) {
            if (!newValue.length) {
                this.redirectToGeneralTab();
            }
        }
    },

    computed: {
        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        ...mapGetters('swOrderDetail', [
            'isLoading'
        ]),

        ...mapState('swOrderDetail', [
            'order'
        ]),

        commissions() {
            return this.order.extensions.dvsnAffiliateCommissions;
        },

        commissionsColumns() {
            return this.getCommissionsColumns();
        },

        pendingCommissionsColumns() {
            return this.getPendingCommissionsColumns();
        },

        systemCurrencyISOCode() {
            return Shopware.Context.app.systemCurrencyISOCode;
        },

        currencyFilter() {
            return Shopware.Filter.getByName('currency');
        },

        dateFilter() {
            return Shopware.Filter.getByName('date');
        },

        orderReturns() {
            return this.order?.extensions?.returns || [];
        },
    },

    emits: ['save-and-reload', 'save-edits'],

    methods: {
        createdComponent() {
            this.billingAddress = this.order.billingAddress;
            this.shippingAddress = this.order.deliveries[0].shippingOrderAddress;
            if (this.shippingAddress === undefined) {
                this.shippingAddress = this.order.billingAddress
            }
            this.renderFormattingAddress();
            this.getTrackingCodes();

            this.fetchAffiliatesForOrders();
        },

        renderFormattingAddress() {
            this.customSnippetApiService
                .render(this.shippingAddress, this.shippingAddress.country?.addressFormat)
                .then((res) => {
                    this.formattingShippingAddress = res.rendered;
                });

            this.customSnippetApiService
                .render(
                    this.billingAddress,
                    this.billingAddress.country?.addressFormat,
                ).then((res) => {
                this.formattingBillingAddress = res.rendered;
            });
        },

        getTrackingCodes() {
            this.trackingCodes = this.order.deliveries[0].trackingCodes;
        },

        getCommissionsColumns() {
            return [{
                property: 'createdAt',
                dataIndex: 'createdAt',
                label: this.$t('dvsn-affiliate-sw-order.order-detail.card-affiliate-commssion.column-created-at'),
                allowResize: true,
                sortable: false
            }, {
                property: 'affiliate',
                dataIndex: 'affiliate',
                label: this.$t('dvsn-affiliate-sw-order.order-detail.card-affiliate-commssion.column-affiliate'),
                allowResize: true,
                sortable: false
            }, {
                property: 'amount',
                dataIndex: 'amount',
                label: this.$t('dvsn-affiliate-sw-order.order-detail.card-affiliate-commssion.column-amount'),
                allowResize: true,
                sortable: false
            }, {
                property: 'commission',
                dataIndex: 'commission',
                label: this.$t('dvsn-affiliate-sw-order.order-detail.card-affiliate-commssion.column-commission'),
                allowResize: true,
                sortable: false
            }, {
                property: 'commissionRedeemed',
                dataIndex: 'commissionRedeemed',
                label: this.$t('dvsn-affiliate-sw-order.order-detail.card-affiliate-commssion.column-commission-redeemed'),
                allowResize: true,
                sortable: false
            }];
        },

        getPendingCommissionsColumns() {
            return [{
                property: 'createdAt',
                dataIndex: 'createdAt',
                label: this.$t('dvsn-affiliate-sw-order.order-detail.card-affiliate-commssion.column-created-at'),
                allowResize: true,
                sortable: false
            }, {
                property: 'affiliate',
                dataIndex: 'affiliate',
                label: this.$t('dvsn-affiliate-sw-order.order-detail.card-affiliate-commssion.column-affiliate'),
                allowResize: true,
                sortable: false
            }, {
                property: 'amount',
                dataIndex: 'amount',
                label: this.$t('dvsn-affiliate-sw-order.order-detail.card-affiliate-commssion.column-amount'),
                allowResize: true,
                sortable: false
            }, {
                property: 'commission',
                dataIndex: 'commission',
                label: this.$t('dvsn-affiliate-sw-order.order-detail.card-affiliate-commssion.column-commission'),
                allowResize: true,
                sortable: false
            }, {
                property: 'commissionRedeemed',
                dataIndex: 'commissionRedeemed',
                label: this.$t('dvsn-affiliate-sw-order.order-detail.card-affiliate-commssion.column-commission-redeemed'),
                allowResize: true,
                sortable: false
            }];
        },

        showCommissionLog(commission) {
            if (commission.commissionCalculationLog === '' || commission.commissionCalculationLog === null) {
                this.createNotificationError({
                    title: this.$t('dvsn-affiliate.detail.card-manual-bookings.modal.error.title'),
                    message: this.$t('dvsn-affiliate-sw-order.order-detail.card-affiliate-commssion.commission-log.error'),
                });
                return;
            }

            this.showCommissionLogModal = true;
            this.commissionLogModalString = commission.commissionCalculationLog;
        },

        closeCommissionLogModal() {
            this.showCommissionLogModal = false;
            this.commissionLogModalString = '';
        },

        reloadOrder() {
            if (this.swOrderDetailOnSaveAndReload) {
                this.swOrderDetailOnSaveAndReload();
            } else {
                this.$emit('save-and-reload');
            }
        },

        saveOrder() {
            if (this.swOrderDetailOnSaveEdits) {
                this.swOrderDetailOnSaveEdits();
            } else {
                this.$emit('save-edits');
            }
        },

        fetchAffiliatesForOrders() {
            const orderAffiliateCode = this.order.affiliateCode;
            if (!orderAffiliateCode?.length) {
                return;
            }

            try {
                this.getAffiliate(orderAffiliateCode);
            } catch (e) {
                console.error('Error fetching affiliates:', e);
            }
        },

        getAffiliate(orderAffiliateCode) {
            const repo = this.repositoryFactory.create('dvsn_affiliate');
            const criteria = new Criteria(1, 100);
            criteria
                .addFilter(Criteria.equals('code', orderAffiliateCode));

            const vm = this;
            repo
                .search(criteria, Shopware.Context.api)
                .then((result) => {
                    if (result.total > 0) {
                        vm.affiliate = result.first();

                        Object.assign(this.pendingCommission, {
                            0: {
                                affiliate: this.affiliate,
                                createdAt: this.order.createdAt,
                                commissionRedeemed: '/',
                                name: this.affiliate.name,
                                amount: Math.round((this.order.positionPrice / this.order.currencyFactor) * 100) / 100,
                                commission: '/'
                            }
                        });
                    }
                })
        },

        getAffiliateCodesFromOrders() {
            return Array.from(new Set(
                (this.order || []).map(order => order.affiliateCode).filter(Boolean)
            ));
        },

        getAffiliateByCode(code) {
            return this.affiliates.find(affiliate => affiliate.code === code);
        }
    }
});
