import template from './custom.html.twig';

Shopware.Component.override('sw-tax-rule-card', {
    template,
    computed: {
        getColumns() {
            const columns = this.$super('getColumns');
            columns.push({
                property: 'customsDuty',
                dataIndex: 'customsDuty',
                label: 'sw-settings-tax.taxRuleCard.labelCustomsTax',
                visible: true,
            });
            columns.push({
                property: 'importVat',
                dataIndex: 'importVat',
                label: 'sw-settings-tax.taxRuleCard.labelImportVat',
                visible: true,
            });

            return columns;
        }
    }
});
