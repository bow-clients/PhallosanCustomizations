export default {
    methods: {
        createdComponent() {
            this.accessoryRequirementOrder = this.accessoryRequirementOrderRepository.create(Shopware.Context.api);
            this.editMode = true;
            this.disabled = true;
        },

        _onSaveSuccess() {
            this.isLoading = false;
            this.processSuccess = true;
            this.editMode = false;
            this.disabled = false;

            this.$router.push({ name: 'accessory.requirement.order_detail', params: { id: this.accessoryRequirementOrder.id }})
        },
    }
}