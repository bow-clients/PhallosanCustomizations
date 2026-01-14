import template from './accessory-requirement-order-list.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: [
        'repositoryFactory',
        'acl'
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('listing'),
    ],

    data() {
        return {
            isLoading: true,
            accessoryRequirementOrders: null,
            sortBy: 'accessoryRequirementId',
            page: 0,
            total: 0
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        columns() {
            return [{
                property: 'accessoryRequirement.name',
                dataIndex: 'accessoryRequirement.name',
                label: this.$tc('accessory-requirement.order.list.accessoryRequirementProduct'),
                routerLink: 'accessory.requirement.order_detail',
                allowResize: true,
                primary: true
            },{
                property: 'orderNumber',
                dataIndex: 'orderNumber',
                label: this.$tc('accessory-requirement.order.list.orderNumber'),
                allowResize: true,
                primary: true
            },{
                property: 'redeemed',
                dataIndex: 'redeemed',
                label: this.$tc('accessory-requirement.order.list.redeemed'),
                allowResize: true,
                primary: true,
                align: 'center',
            },{
                property: 'redeemedOrderNumber',
                dataIndex: 'redeemedOrderNumber',
                label: this.$tc('accessory-requirement.order.list.redeemedOrderNumber'),
                allowResize: true,
                primary: true
            },{
                property: 'redeemedAt',
                dataIndex: 'redeemedAt',
                label: this.$tc('accessory-requirement.order.list.redeemedAt'),
                allowResize: true,
                primary: true
            }]
        },

        repository() {
            return this.repositoryFactory.create('accessory_requirement');
        },

        accessoryRequirementOrderRepository() {
            return this.repositoryFactory.create('accessory_requirement_order');
        }
    },

    methods: {
        async getList() {
            this.isLoading = true;
            const criteria = new Criteria();
            criteria.addSorting(Criteria.sort(this.sortBy, 'ASC', false));
            criteria.addAssociation('accessoryRequirement')

            if (!this.entitySearchable) {
                this.isLoading = false;
                this.total = 0;

                return false;
            }

            this.accessoryRequirementOrders = await this.accessoryRequirementOrderRepository.search(criteria, Shopware.Context.api)
            this.total = this.accessoryRequirementOrders.total;
            this.isLoading = false;
        },

        updateTotal({ total }) {
            this.total = total;
        },
    }
}