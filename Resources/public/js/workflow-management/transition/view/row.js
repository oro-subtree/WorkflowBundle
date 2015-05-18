/* global define */
define(['underscore', 'backbone', 'jquery'],
function(_, Chaplin, $) {
    'use strict';

    /**
     * @export  oroworkflow/js/workflow-management/transition/view/row
     * @class   oro.WorkflowManagement.TransitionRowView
     * @extends Backbone.View
     */
    return Chaplin.View.extend({
        tagName: 'tr',

        events: {
            'click .delete-transition': 'triggerRemoveTransition'
        },

        options: {
            workflow: null,
            template: null,
            stepFrom: null
        },

        initialize: function (options) {
            this.options = _.defaults(options || {}, this.options);
            var template = this.options.template || $('#transition-row-template').html();
            this.template = _.template(template);

            this.listenTo(this.model, 'change', this.render);
            this.listenTo(this.model, 'destroy', this.remove);
        },

        triggerRemoveTransition: function(e) {
            e.preventDefault();
            this.options.workflow.trigger('requestRemoveTransition', this.model);
        },

        render: function() {
            var data = this.model.toJSON();
            var stepTo = this.options.workflow.getStepByName(data.step_to);
            data.stepToLabel = stepTo ? stepTo.get('label') : '';
            this.$el.html(
                this.template(data)
            );

            return this;
        }
    });
});
