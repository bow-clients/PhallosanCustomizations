
import template from './accessory-requirement-detail.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

export default {
    template,

    inject: [
        'repositoryFactory'
    ],

    mixins: [
        Mixin.getByName('notification')
    ],

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    data() {
        return {
            accessoryRequirement: null,
            isLoading: false,
            processSuccess: false,
            customFields: null,
            sets: []
        }
    },

    created() {
        this.createdComponent();

        this.customFieldSetRepository.search(this.customFieldSetCriteria, Shopware.Context.api)
            .then((customFieldSets) => {
                this.sets = customFieldSets;
            })
    },

    computed: {
        ...mapPropertyErrors('accessoryRequirement', ['name', 'description']),
        accessoryRequirementRepository() {
            return this.repositoryFactory.create('accessory_requirement');
        },

        customFieldSetRepository(){
            return this.repositoryFactory.create('custom_field_set');
        },

        customFieldSetCriteria() {
            const criteria = new Criteria();

            criteria.addFilter(Criteria.equals('relations.entityName', 'accessory_requirement'));

            criteria
                .getAssociation('customFields')
                .addSorting(Criteria.sort('config.customFieldPosition', 'ASC', true));

            return criteria;
        },


        productRepository() {
            return this.repositoryFactory.create('product');
        },

        isValid() {
            return this.accessoryRequirement.name &&
                this.accessoryRequirement.productId &&
                this.accessoryRequirement.requiredProductId &&
                this.accessoryRequirement.productId !== this.accessoryRequirement.requiredProductId
        },

        snippetName() {
            return 'checkout.accessory-required-product-missing.' + this.accessoryRequirement.id;
        }


    },

    methods: {
        async createdComponent() {
            this.isLoading = true;
            const criteria = new Criteria();
            criteria.addAssociation('product');

            this.accessoryRequirement = await this.accessoryRequirementRepository.get(this.$route.params.id, Shopware.Context.api, criteria);
            this.isLoading = false;
        },

        async onClickSave() {
            this.isLoading = true;


            try {
                await this.accessoryRequirementRepository.save(this.accessoryRequirement, Shopware.Context.api)
                this._onSaveSuccess();
            } catch (e) {
                this._onSaveError(e);
            }
        },

        saveFinish() {
            this.processSuccess = false;
        },

        _onSaveSuccess() {
            this.createdComponent();
            this.isLoading = false;
            this.processSuccess = true;
        },

        _onSaveError(e) {
            this.isLoading = false;

            this.createNotificationError({
                message: e?.response?.data?.errors[0]?.detail
            });
        },
    },
}