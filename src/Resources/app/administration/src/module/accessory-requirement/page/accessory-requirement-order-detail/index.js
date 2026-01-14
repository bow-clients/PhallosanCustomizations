import template from './accessory-requirement-order-detail.html.twig';
import './accessory-requirement-order-detail.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;


export default {
    template,

    inject: [
        'repositoryFactory',
        'acl'
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
            accessoryRequirementOrder: null,
            isLoading: false,
            processSuccess: false,
            customFields: null,
            sets: [],
            editMode: false,
            clearRedeemedFlag: true,
            hasChanges: false,
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
        accessoryRequirementOrderRepository() {
            return this.repositoryFactory.create('accessory_requirement_order');
        },

        customFieldSetRepository(){
            return this.repositoryFactory.create('custom_field_set');
        },

        customFieldSetCriteria() {
            const criteria = new Criteria();

            criteria.addFilter(Criteria.equals('relations.entityName', 'accessory_requirement_order'));

            criteria
                .getAssociation('customFields')
                .addSorting(Criteria.sort('config.customFieldPosition', 'ASC', true));

            return criteria;
        },


        productRepository() {
            return this.repositoryFactory.create('product');
        },

        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        isValid() {
            return this.accessoryRequirementOrder.orderNumber &&
                   this.accessoryRequirementOrder.accessoryRequirementId;
        }
    },

    methods: {
        async createdComponent() {
            this.isLoading = true;
            const criteria = new Criteria();
            criteria.addAssociation('accessoryRequirement');
            criteria.addAssociation('accessoryRequirement.product');
            criteria.addAssociation('accessoryRequirement.requiredProduct');

            this.accessoryRequirementOrder = await this.accessoryRequirementOrderRepository.get(this.$route.params.id, Shopware.Context.api, criteria);
            this.isLoading = false;
        },

        async getOrderIdByNumber(orderNumber) {
            if (String(orderNumber).length === 0) {
                return null;
            }

            const orderCriteria = new Criteria();
            orderCriteria.addFilter(
                Criteria.equals('orderNumber', orderNumber)
            )

            const orderResult = await this.orderRepository.searchIds(orderCriteria, Shopware.Context.api);

            return orderResult?.data[0] ?? null;
        },

        async onClickSave() {
            this.isLoading = true;

            /**
             * clear the "redeemedAt" date if the orderNumber was removed
             */
            const changes = this.accessoryRequirementOrderRepository.getSyncChangeset([this.accessoryRequirementOrder]);
            if (!this.accessoryRequirementOrder.redeemedOrderNumber) {
                this.accessoryRequirementOrder.redeemedAt = null;
                this.accessoryRequirementOrder.redeemedOrderId = null;
            }

            /**
             * if the redeemedOrderNumber has changes, try to find the id of the order and set the redeemedAt date to today
             */
            if (typeof changes?.changeset[0]?.changes?.redeemedOrderNumber !== 'undefined') {
                if (this.accessoryRequirementOrder.redeemedOrderNumber) {
                    this.accessoryRequirementOrder.redeemedAt = new Date();
                    this.accessoryRequirementOrder.redeemedOrderId = await this.getOrderIdByNumber(this.accessoryRequirementOrder.redeemedOrderNumber);;
                }
            }

            try {
                await this.accessoryRequirementOrderRepository.save(this.accessoryRequirementOrder, Shopware.Context.api)
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
            this.editMode = false;
            this.processSuccess = true;
        },

        _onSaveError(e) {
            this.isLoading = false;

            this.createNotificationError({
                message: e?.response?.data?.errors[0]?.detail
            });
        },
        disableEditMode() {
            this.editMode = false;
            this.accessoryRequirementOrderRepository.discard(this.accessoryRequirementOrder);
        }
    },
    watch: {
        accessoryRequirementOrder: {
            handler() {
                this.hasChanges = this.accessoryRequirementOrderRepository.hasChanges(this.accessoryRequirementOrder);
            },
            deep: true
        }
    }
}