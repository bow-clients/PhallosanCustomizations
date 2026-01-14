import template from './sw-order-detail.html.twig';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);

Component.override('sw-order-detail', {
    template,

    inject: ['repositoryFactory', 'numberRangeService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isTagLoading: false,
            finecomTag: null
        };
    },

    computed: {
        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        tagRepository() {
            return this.repositoryFactory.create('tag');
        },

        hasFinecomTag() {
            if (!this.order || !this.order.tags) {
                return false;
            }
            return this.order.tags.some(tag => tag.name === 'FINECOM_FETCHABLE');
        }
    },

    async created() {
        await this.loadFinecomTag();
    },

    methods: {
        async loadFinecomTag() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('name', 'FINECOM_FETCHABLE'));

            const result = await this.tagRepository.search(criteria, Shopware.Context.api);

            if (result.length > 0) {
                this.finecomTag = result.first();
            }
        },

        async createTag() {
            if (!this.finecomTag) {
                this.createNotificationError({
                    message: this.$tc('finecom-export.order.error.tagNotFound')
                });
                return;
            }

            if (this.hasFinecomTag) {
                await this.removeTag();
            } else {
                await this.addTag();
            }
        },

        async addTag() {
            this.isTagLoading = true;

            try {
                if (!this.finecomTag) {
                    this.createNotificationError({
                        message: this.$tc('finecom-export.order.error.tagNotFound')
                    });
                    return;
                }

                const criteria = new Criteria();
                criteria.addAssociation('tags');

                const freshOrder = await this.orderRepository.get(this.order.id, Shopware.Context.api, criteria);

                if (!freshOrder.tags) {
                    freshOrder.tags = [];
                }

                const tagExists = freshOrder.tags.some(tag => tag.id === this.finecomTag.id);

                if (!tagExists) {
                    freshOrder.tags.push({
                        id: this.finecomTag.id,
                        name: this.finecomTag.name
                    });

                    await this.orderRepository.save(freshOrder, Shopware.Context.api);
                }

                await new Promise(resolve => setTimeout(resolve, 500));

                await this.setPaymentStatusToPaid();
                await new Promise(resolve => setTimeout(resolve, 500));

                await this.setOrderStatusToInProgress();
                await new Promise(resolve => setTimeout(resolve, 500));

                await this.createInvoiceDocument();
                await new Promise(resolve => setTimeout(resolve, 500));

                await this.reloadOrder();

                this.$forceUpdate();

                this.$emit('order-updated', this.order);

                this.createNotificationSuccess({
                    message: this.$tc('finecom-export.order.success.tagAdded')
                });

                setTimeout(() => {
                    window.location.reload();
                }, 2000);

            } catch (error) {
                console.error('Fehler in addTag:', error);
                this.createNotificationError({
                    message: this.$tc('finecom-export.order.error.general')
                });
            } finally {
                this.isTagLoading = false;
            }
        },

        async removeTag() {
            this.isTagLoading = true;

            try {
                const criteria = new Criteria();
                criteria.addAssociation('tags');

                const currentOrder = await this.orderRepository.get(this.order.id, Shopware.Context.api, criteria);

                if (currentOrder.tags) {
                    const tagIndex = currentOrder.tags.findIndex(tag => tag.name === 'FINECOM_FETCHABLE');

                    if (tagIndex !== -1) {
                        currentOrder.tags.splice(tagIndex, 1);
                        await this.orderRepository.save(currentOrder, Shopware.Context.api);
                    }
                }

                await this.reloadOrder();

                this.createNotificationSuccess({
                    message: this.$tc('finecom-export.order.success.tagRemoved')
                });

                // Seite neu laden nach 1 Sekunde
                setTimeout(() => {
                    window.location.reload();
                }, 1000);

            } catch (error) {
                console.error('Fehler beim Entfernen des Tags:', error);
                this.createNotificationError({
                    message: this.$tc('finecom-export.order.error.tagRemove')
                });
            } finally {
                this.isTagLoading = false;
            }
        },

        async reloadOrder() {
            if (this.order && this.order.id) {
                const criteria = new Criteria();
                criteria.addAssociation('tags');
                criteria.addAssociation('transactions.stateMachineState');
                criteria.addAssociation('stateMachineState');
                criteria.addAssociation('orderCustomer');
                criteria.addAssociation('currency');
                criteria.addAssociation('addresses');
                criteria.addAssociation('documents');

                const result = await this.orderRepository.get(this.order.id, Shopware.Context.api, criteria);

                this.$set(this, 'order', result);
                this.$forceUpdate();
                this.$emit('order-updated', result);
            }
        },

        async setPaymentStatusToPaid() {
            if (!this.order.transactions || this.order.transactions.length === 0) {
                return;
            }

            for (const transaction of this.order.transactions) {
                if (transaction.stateMachineState?.technicalName === 'paid') {
                    continue;
                }

                try {
                    const httpClient = Shopware.Application.getContainer('init').httpClient;

                    const response = await httpClient.post(
                        `/_action/order_transaction/${transaction.id}/state/pay`,
                        {},
                        {
                            headers: {
                                'Authorization': `Bearer ${Shopware.Context.api.authToken.access}`,
                                'Content-Type': 'application/json'
                            }
                        }
                    );

                    await new Promise(resolve => setTimeout(resolve, 100));

                } catch (error) {
                    try {
                        const httpClient = Shopware.Application.getContainer('init').httpClient;
                        const response = await httpClient.post(
                            `/_action/order_transaction/${transaction.id}/state/paid`,
                            {},
                            {
                                headers: {
                                    'Authorization': `Bearer ${Shopware.Context.api.authToken.access}`,
                                    'Content-Type': 'application/json'
                                }
                            }
                        );

                        await new Promise(resolve => setTimeout(resolve, 100));
                    } catch (alternativeError) {
                        try {
                            const httpClient = Shopware.Application.getContainer('init').httpClient;
                            const response = await httpClient.post(
                                `/_action/order_transaction/${transaction.id}/state/authorize`,
                                {},
                                {
                                    headers: {
                                        'Authorization': `Bearer ${Shopware.Context.api.authToken.access}`,
                                        'Content-Type': 'application/json'
                                    }
                                }
                            );

                            await new Promise(resolve => setTimeout(resolve, 100));
                        } catch (finalError) {
                        }
                    }
                }
            }
        },

        async setOrderStatusToInProgress() {
            try {
                const httpClient = Shopware.Application.getContainer('init').httpClient;
                const transitionNames = ['process', 'in_progress', 'reopen'];

                for (const transitionName of transitionNames) {
                    try {
                        const response = await httpClient.post(
                            `/_action/order/${this.order.id}/state/${transitionName}`,
                            {},
                            {
                                headers: {
                                    'Authorization': `Bearer ${Shopware.Context.api.authToken.access}`,
                                    'Content-Type': 'application/json'
                                }
                            }
                        );

                        await new Promise(resolve => setTimeout(resolve, 100));
                        break;

                    } catch (transitionError) {
                    }
                }

            } catch (error) {
            }
        },

        async createInvoiceDocument() {
            try {
                const httpClient = Shopware.Application.getContainer('init').httpClient;

                const now = new Date();
                var dateTime = now.toISOString();

                const response = await httpClient.post(
                    `/_action/order/document/zugferd_embedded_invoice/create`,
                    [
                        {
                            "orderId":this.order.id,
                            "config":{
                                "documentDate": dateTime,
                            },
                            "referencedDocumentId":null
                        }
                        ]
                    ,
                    {
                        headers: {
                            'Authorization': `Bearer ${Shopware.Context.api.authToken.access}`,
                            'Content-Type': 'application/json',
                        }
                    }
                );

            }catch (error) {}
        }
    }
});
