Shopware.Component.override('swag-return-management-return-line-items-grid', {
    computed: {
        getLineItemColumns() {
            const columns = this.$super('getLineItemColumns');

            columns.push({
                property: 'internalComment',
                label: this.$tc('swag-return-management.comment'),
                allowResize: true,
                multiLine: true,
                sortable: false
            });

            return columns;
        }
    },
});
