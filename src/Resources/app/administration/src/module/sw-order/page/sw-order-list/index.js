import template from './sw-order-list.html.twig';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);

const { Component, Mixin } = Shopware;

Component.override('sw-order-list', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            showDuplicateModal: false,
        }
    },

    methods: {
        onDuplicate(id) {
            this.showDuplicateModal = id;
        },

        onCloseDuplicateModal() {
            this.showDuplicateModal = false;
        },

        onConfirmDuplicate(item) {
            this.showDuplicateModal = false;

            const token = Shopware.Context.api.authToken.access;
            const httpClient = Shopware.Application.getContainer('init').httpClient;

            httpClient.post(`/phallosan/duplicate-order/${item.id}`, {}, {
                headers: {
                    Authorization: `Bearer ${token}`
                }
            }).then((response) => {
                this.createNotificationSuccess({
                    title: 'Erfolg',
                    message: 'Bestellung wurde dupliziert.'
                });

                this.$refs.orderGrid.resetSelection();
                this.getList();
            }).catch(() => {
                this.createNotificationError({
                    title: 'Fehler',
                    message: 'Duplizieren der Bestellung fehlgeschlagen.'
                });
            });
        },
    }
});
