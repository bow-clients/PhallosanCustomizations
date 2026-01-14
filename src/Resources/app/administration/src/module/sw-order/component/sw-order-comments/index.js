import template from './sw-order-comments.html.twig';
import './sw-order-comments.scss';

const { Component, Mixin } = Shopware;
const { mapState } = Shopware.Component.getComponentHelper();
const { Criteria } = Shopware.Data;

Component.register('sw-order-comments', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            comments: [],
            isLoading: false,
            newComment: '',
            isPublic: false,
            showAddComment: false
        };
    },

    computed: {
        ...mapState('swOrderDetail', [
            'order',
        ]),
        commentRepository() {
            return this.repositoryFactory.create('order_comment');
        },
        commentCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(
                Criteria.equals('orderId', this.order.id)
            );
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));
            return criteria;
        },
        dateFilter() {
            return Shopware.Filter.getByName('date');
        }
    },

    created() {
        this.loadComments();
    },

    methods: {
        async loadComments() {
            this.isLoading = true;

            try {
                this.comments = await this.commentRepository.search(this.commentCriteria, Shopware.Context.api);
            } catch (error) {
                this.createNotificationError({
                    message: this.$tc('phallosan-customizations.order.comments.loadError')
                });
            } finally {
                // Since the comments are sorted newest first we add the initial comment to the end of the list
                if (this.order.customerComment) {
                    this.comments.push({
                        createdAt: new Date(this.order.orderDateTime).getTime(),
                        comment: this.order.customerComment,
                        isPublic: this.$tc('phallosan-customizations.order.comments.customerComment'),
                    });
                }
                this.isLoading = false;
            }
        },
        async addComment() {
            const comment = this.newComment.trim();
            if (!comment) {
                return;
            }

            const newComment = this.commentRepository.create(Shopware.Context.api);
            newComment.orderId = this.order.id;
            newComment.orderVersionId = Shopware.Context.api.liveVersionId;
            newComment.comment = comment;
            newComment.isPublic = this.isPublic;

            try {
                const liveContext = {
                    ...Shopware.Context.api,
                    versionId: Shopware.Context.api.liveVersionId,
                };

                await this.commentRepository.save(newComment, liveContext);
                this.createNotificationSuccess({
                    message: this.$tc('phallosan-customizations.order.comments.addSuccess')
                });
                this.resetCommentInput();
                await this.loadComments();
            } catch (error) {
                this.createNotificationError({
                    message: this.$tc('phallosan-customizations.order.comments.addError')
                });
            }
        },

        formatDate(date) {
            return new Date(date).toLocaleString();
        },

        closeModal() {
            this.toggleAddComment();
            this.resetCommentInput();
        },

        toggleAddComment() {
            this.showAddComment = !this.showAddComment;
        },

        resetCommentInput() {
            this.showAddComment = false;
            this.newComment = '';
            this.isPublic = false;
        },

        getPublicLabel(input) {
            // Return the label based on the input type
            // If it is a boolean, which is the case for normal orderComments,
            // the label is displayed as either "comments.public" or "comments.private".
            // If it is a string, which is the case for the customer comment,
            // the label is displayed as "Kundenkommentar" with a custom variant.
            return {
                variant: typeof input === 'string'
                    ? 'info'
                    : (input ? 'success' : 'neutral'),
                text: typeof input === 'string' ? input : (input
                        ? this.$tc('phallosan-customizations.order.comments.public')
                        : this.$tc('phallosan-customizations.order.comments.private')
                    )
            }
        }
    }
});
