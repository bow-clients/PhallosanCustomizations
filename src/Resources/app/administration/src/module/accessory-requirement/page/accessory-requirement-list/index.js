
import template from './accessory-requirement-list.html.twig';

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
            accessoryRequirements: null,
            sortBy: 'name',
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
                property: 'name',
                dataIndex: 'name',
                label: this.$tc('accessory-requirement.list.name'),
                routerLink: 'accessory.requirement.detail',
                allowResize: true,
                primary: true
            },{
                property: 'description',
                dataIndex: 'description',
                label: this.$tc('accessory-requirement.list.description'),
                allowResize: true,
                primary: true
            }]
        },

        repository() {
            return this.repositoryFactory.create('accessory_requirement');
        }
    },

    methods: {
        async getList() {
            this.isLoading = true;
            const criteria = new Criteria();
            criteria.addSorting(Criteria.sort(this.sortBy, 'ASC', false));

            if (!this.entitySearchable) {
                this.isLoading = false;
                this.total = 0;

                return false;
            }

            this.accessoryRequirements = await this.repository.search(criteria, Shopware.Context.api)
            this.total = this.accessoryRequirements.total;
            this.isLoading = false;
        },

        updateTotal({ total }) {
            this.total = total;
        },
    }
}