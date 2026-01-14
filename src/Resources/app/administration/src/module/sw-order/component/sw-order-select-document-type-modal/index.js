import template from './sw-order-select-document-type-modal.html';

const {Component, Filter} = Shopware;

Component.override('sw-order-select-document-type-modal', {
    template,

    computed: {

        documentTypeFilter() {
            return Filter.getByName('removeDocTypes');
        },

        filteredDocumentTypes() {
            return this.documentTypeFilter(this.documentTypes)
        }
    }
})
