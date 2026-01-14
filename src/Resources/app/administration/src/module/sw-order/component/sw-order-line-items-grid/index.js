import template from './sw-order-line-items-grid.html.twig';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);

const {Component} = Shopware;

Component.override('sw-order-line-items-grid', {
    template,

    data() {
        return {
            selectedItem: null,
            modalVisible: false
        };
    },

    computed: {
        order() {
            return this.$store.state.orderDetail.order;
        },

        isEuOrder() {
            return this.order?.deliveries[0]?.shippingOrderAddress?.country?.isEu;
        },

        getLineItemColumns() {
            const columns = this.$super('getLineItemColumns');

            columns.push({
                property: 'hsCodes',
                label: this.$tc('sw-order-line-items-grid.column-title'),
                rawData: true,
                multiLine: true,
                sortable: false
            });

            return columns;
        }
    },

    methods: {
        open(item) {
            this.selectedItem = item;
            this.modalVisible = true;
            this.$nextTick(() => document.body.click());
        },

        closeModal() {
            this.modalVisible = false;
            this.selectedItem = null;
        },

        savedModal() {
            this.closeModal();
        },

        updateLineItemInStore(updatedItem) {
            const index = this.order.lineItems.findIndex(item => item.id === updatedItem.id);
            if (index !== -1) {
                this.$set(this.order.lineItems, index, updatedItem);
            }
        }
    }
});
