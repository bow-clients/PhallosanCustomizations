export default {
    methods: {
        createdComponent() {
            this.accessoryRequirement = this.accessoryRequirementRepository.create(Shopware.Context.api);

            this.disabled = true;
        },

        _onSaveSuccess() {
            this.isLoading = false;
            this.processSuccess = true;
            this.disabled = false;

            this.$router.push({ name: 'accessory.requirement.detail', params: { id: this.accessoryRequirement.id }})
        },
    }
}