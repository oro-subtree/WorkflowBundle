define(function (require) {
    'use strict';
    var _ = require('underscore'),
        JsplubmAreaView = require('../jsplumb/area'),
        JsplumbWorkflowStepView = require('./step'),
        JsplubmTransitionView = require('./transition'),
        BaseCollectionView = require('oroui/js/app/views/base/collection-view'),
        WorkflowFlowchartView;

    WorkflowFlowchartView = JsplubmAreaView.extend({
        initialize: function () {
            WorkflowFlowchartView.__super__.initialize.apply(this, arguments);
        },

        findStepModelByElement: function (el) {
            var stepCollectionView = this.stepCollectionView;
            return this.model.get('steps').find(function (model) {
                return stepCollectionView.getItemView(model).el === el;
            });
        },

        render: function () {
            WorkflowFlowchartView.__super__.render.apply(this, arguments);

            var stepCollectionView,
                that = this,
                steps = this.model.get('steps');
            this.stepCollectionView = stepCollectionView = new BaseCollectionView({
                el: this.$el,
                collection: steps,
                animationDuration: 0,
                // pass areaView to each model
                itemView: function (options) {
                    options = _.extend({
                        areaView: that
                    }, options);
                    return new JsplumbWorkflowStepView(options);
                },
                autoRender: true
            });
            this.transitionCollectionView = new BaseCollectionView({
                el: this.$el,
                collection: this.model.get('transitions'),
                animationDuration: 0,
                // pass areaView to each model
                itemView: function (options) {
                    options = _.extend({
                        areaView: that,
                        stepCollection: steps,
                        stepCollectionView: stepCollectionView
                    }, options);
                    return new JsplubmTransitionView(options);
                },
                autoRender: true
            });

            this.jsPlumbInstance.bind('beforeDrop', _.bind(function (data) {
                var transitionModel, startingSteps, suspendedStep, suspendedStepTransitions,
                    stepFrom = this.findStepModelByElement(data.connection.source),
                    stepTo = this.findStepModelByElement(data.connection.target);
                if (data.connection.suspendedElement) {
                    transitionModel = data.connection.overlayView.model;
                    startingSteps = transitionModel.getStartingSteps();
                    console.log('old', transitionModel);
                    if (stepTo.get('name') !== transitionModel.get('step_to')) {
                        // stepTo changed
                        transitionModel.set({
                            step_to: stepTo.get('name')
                        });
                    }
                    if (startingSteps.indexOf(stepFrom) === -1) {
                        suspendedStep = this.findStepModelByElement(data.connection.suspendedElement);
                        stepFrom.getAllowedTransitions().add(transitionModel);
                        suspendedStep.getAllowedTransitions().remove(transitionModel);
                        stepFrom.trigger('change');
                        suspendedStep.trigger('change');
                    }
                } else {
                    this.model.trigger('requestAddTransition', stepFrom, stepTo);
                }
                // never allow jsplumb just draw new connections, create connection model instead
                return false;
            }, this));

        }
    });

    return WorkflowFlowchartView;
});
