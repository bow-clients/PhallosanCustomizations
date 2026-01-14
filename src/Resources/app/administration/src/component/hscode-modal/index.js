import template from './hscode-modal.html.twig';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);

const {Component, Mixin} = Shopware;

Component.register('hscode-modal', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        item: {
            type: Object,
            required: true
        },
        visible: {
            type: Boolean,
            required: true
        },
        isEu: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            loadedItem: null,
            productHsCodeUs: '',
            productHsCodeEu: '',
            isLoading: false
        };
    },

    watch: {
        'item.id': {
            immediate: true,
            handler(newId) {
                if (!newId) return;

                this.isLoading = true;

                const me = this;

                const repo = this.repositoryFactory.create('order_line_item');
                const criteria = new Shopware.Data.Criteria();
                criteria.setIds([this.item.id]);

                repo.search(criteria, Shopware.Context.api).then(result => {
                    const entity = result.first();
                    this.loadedItem = entity;

                    const itemFields = entity.customFields || {};
                    const productFields = entity.payload?.customFields || {};

                    if (me.isEu) {
                        this.productHsCodeEu = itemFields.product_hs_code_eu ?? productFields.product_hs_code_eu ?? '';
                    } else {
                        this.productHsCodeUs = itemFields.product_hs_code_us ?? productFields.product_hs_code_us ?? '';
                    }

                    this.isLoading = false;
                });
            }
        }
    },

    methods: {
        onCancel() {
            this.$emit('close');
        },

        onSave() {
            if (!this.loadedItem.customFields) {
                this.$set(this.loadedItem, 'customFields', {});
            }

            if (this.isEu) {
                this.loadedItem.customFields.product_hs_code_eu = this.productHsCodeEu;
            } else {
                this.loadedItem.customFields.product_hs_code_us = this.productHsCodeUs;
            }

            const repo = this.repositoryFactory.create('order_line_item');

            this.isLoading = true;
            repo.save(this.loadedItem, Shopware.Context.api)
                .then(() => {
                    return repo.get(this.item.id, Shopware.Context.api);
                })
                .then((freshItem) => {
                    this.$emit('saved');
                    this.isLoading = false;
                    this.$emit('updated-line-item', freshItem);
                    this.$emit('close');
                    this.createNotificationSuccess({
                        message: 'HS Code gespeichert.'
                    });
                });
        }
    },
});
