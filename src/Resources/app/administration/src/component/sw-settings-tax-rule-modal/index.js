import template from './custom.html.twig';

Shopware.Component.override('sw-settings-tax-rule-modal', {
    template,

    computed: {
        customsDuty: {
            get() {
                return this.taxRule.extensions.customTaxRule?.customsDuty ?? 0;
            },
            set(value) {
                this.$set(this.taxRule.extensions.customTaxRule, 'customsDuty', value);
            },
        },
        importVat: {
            get() {
                return this.taxRule.extensions.customTaxRule?.importVat ?? 0;
            },
            set(value) {
                this.$set(this.taxRule.extensions.customTaxRule, 'importVat', value);
            },
        },
        applyCustomsDuty: {
            get() {
                return this.taxRule.extensions.customTaxRule?.applyCustomsDuty ?? false;
            },
            set(value) {
                this.$set(this.taxRule.extensions.customTaxRule, 'applyCustomsDuty', value);
            },
        },
        applyImportVat: {
            get() {
                return this.taxRule.extensions.customTaxRule?.applyImportVat ?? false;
            },
            set(value) {
                this.$set(this.taxRule.extensions.customTaxRule, 'applyImportVat', value);
            },
        },
    },

    watch: {
        taxRule() {
            if (!this.taxRule.extensions || this.taxRule.extensions.customTaxRule) {
                return;
            }
            const customTaxRuleRepository = this.repositoryFactory.create(
                'custom_tax_rule',
            );
            this.taxRule.extensions.customTaxRule = customTaxRuleRepository.create();
        }
    },
});
