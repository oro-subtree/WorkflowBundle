<?php

namespace Oro\Bundle\WorkflowBundle\Model;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

use Oro\Bundle\ActionBundle\Model\Attribute;
use Oro\Bundle\WorkflowBundle\Configuration\WorkflowConfiguration;
use Oro\Bundle\WorkflowBundle\Form\Type\WorkflowTransitionType;

use Oro\Component\Action\Action\ActionFactory;
use Oro\Component\Action\Action\Configurable as ConfigurableAction;
use Oro\Component\Action\Condition\Configurable as ConfigurableCondition;
use Oro\Component\Action\Exception\AssemblerException;
use Oro\Component\Action\Model\AbstractAssembler as BaseAbstractAssembler;
use Oro\Component\ConfigExpression\ExpressionFactory as ConditionFactory;

class TransitionAssembler extends BaseAbstractAssembler
{
    /**
     * @var FormOptionsAssembler
     */
    protected $formOptionsAssembler;

    /**
     * @var ConditionFactory
     */
    protected $conditionFactory;

    /**
     * @var ActionFactory
     */
    protected $actionFactory;

    /**
     * @param FormOptionsAssembler $formOptionsAssembler
     * @param ConditionFactory $conditionFactory
     * @param ActionFactory $actionFactory
     */
    public function __construct(
        FormOptionsAssembler $formOptionsAssembler,
        ConditionFactory $conditionFactory,
        ActionFactory $actionFactory
    ) {
        $this->formOptionsAssembler = $formOptionsAssembler;
        $this->conditionFactory = $conditionFactory;
        $this->actionFactory = $actionFactory;
    }

    /**
     * @param array $configuration
     * @param array $definitionsConfiguration
     * @param Step[]|Collection $steps
     * @param Attribute[]|Collection $attributes
     * @return Collection
     * @throws AssemblerException
     */
    public function assemble(array $configuration, array $definitionsConfiguration, $steps, $attributes)
    {
        $definitions = $this->parseDefinitions($definitionsConfiguration);

        $transitions = new ArrayCollection();
        foreach ($configuration as $name => $options) {
            $this->assertOptions($options, array('transition_definition'));
            $definitionName = $options['transition_definition'];
            if (!isset($definitions[$definitionName])) {
                throw new AssemblerException(
                    sprintf('Unknown transition definition %s', $definitionName)
                );
            }
            $definition = $definitions[$definitionName];

            $transition = $this->assembleTransition($name, $options, $definition, $steps, $attributes);
            $transitions->set($name, $transition);
        }

        return $transitions;
    }

    /**
     * @param array $configuration
     * @return array
     */
    protected function parseDefinitions(array $configuration)
    {
        $definitions = array();
        foreach ($configuration as $name => $options) {
            if (empty($options)) {
                $options = array();
            }
            $definitions[$name] = [
                'preactions' => $this->getOption($options, 'preactions', []),
                'preconditions' => $this->getOption($options, 'preconditions', []),
                'conditions' => $this->getOption($options, 'conditions', []),
                'actions' => $this->getOption($options, 'actions', []),
            ];
        }

        return $definitions;
    }

    /**
     * @param string $name
     * @param array $options
     * @param array $definition
     * @param Step[]|ArrayCollection $steps
     * @param Attribute[]|Collection $attributes
     * @return Transition
     * @throws AssemblerException
     */
    protected function assembleTransition($name, array $options, array $definition, $steps, $attributes)
    {
        $this->assertOptions($options, array('step_to'));
        $stepToName = $options['step_to'];
        if (empty($steps[$stepToName])) {
            throw new AssemblerException(sprintf('Step "%s" not found', $stepToName));
        }

        $transition = new Transition();
        $transition->setName($name)
            ->setStepTo($steps[$stepToName])
            ->setLabel($this->getOption($options, 'label'))
            ->setMessage($this->getOption($options, 'message'))
            ->setStart($this->getOption($options, 'is_start', false))
            ->setHidden($this->getOption($options, 'is_hidden', false))
            ->setUnavailableHidden($this->getOption($options, 'is_unavailable_hidden', false))
            ->setFormType($this->getOption($options, 'form_type', WorkflowTransitionType::NAME))
            ->setFormOptions($this->assembleFormOptions($options, $attributes, $name))
            ->setFrontendOptions($this->getOption($options, 'frontend_options', array()))
            ->setDisplayType(
                $this->getOption($options, 'display_type', WorkflowConfiguration::DEFAULT_TRANSITION_DISPLAY_TYPE)
            )
            ->setPageTemplate($this->getOption($options, 'page_template'))
            ->setDialogTemplate($this->getOption($options, 'dialog_template'))
            ->setInitEntities($this->getOption($options, WorkflowConfiguration::NODE_INIT_ENTITIES, []))
            ->setInitRoutes($this->getOption($options, WorkflowConfiguration::NODE_INIT_ROUTES, []))
            ->setInitContextAttribute($this->getOption($options, WorkflowConfiguration::NODE_INIT_CONTEXT_ATTRIBUTE));

        if (!empty($definition['preactions'])) {
            $preAction = $this->actionFactory->create(ConfigurableAction::ALIAS, $definition['preactions']);
            $transition->setPreAction($preAction);
        }

        $definition['preconditions'] = $this->addAclPreConditions($options, $definition, $name);

        if (!empty($definition['preconditions'])) {
            $condition = $this->conditionFactory->create(ConfigurableCondition::ALIAS, $definition['preconditions']);
            $transition->setPreCondition($condition);
        }

        if (!empty($definition['conditions'])) {
            $condition = $this->conditionFactory->create(ConfigurableCondition::ALIAS, $definition['conditions']);
            $transition->setCondition($condition);
        }

        if (!empty($definition['actions'])) {
            $action = $this->actionFactory->create(ConfigurableAction::ALIAS, $definition['actions']);
            $transition->setAction($action);
        }

        if (!empty($options['schedule'])) {
            $transition->setScheduleCron($this->getOption($options['schedule'], 'cron', null));
            $transition->setScheduleFilter($this->getOption($options['schedule'], 'filter', null));
            $transition->setScheduleCheckConditions(
                $this->getOption($options['schedule'], 'check_conditions_before_job_creation', false)
            );
        }

        return $transition;
    }

    /**
     * @param array  $options
     * @param array  $definition
     * @param string $transitionName
     * @return array
     */
    protected function addAclPreConditions(array $options, array $definition, $transitionName)
    {
        $aclResource = $this->getOption($options, 'acl_resource');

        if ($aclResource) {
            $aclPreConditionDefinition = ['parameters' => [$aclResource]];
            $aclMessage = $this->getOption($options, 'acl_message');
            if ($aclMessage) {
                $aclPreConditionDefinition['message'] = $aclMessage;
            }

            /**
             * @see AclGranted
             */
            $aclPreCondition = ['@acl_granted' => $aclPreConditionDefinition];

            if (empty($definition['preconditions'])) {
                $definition['preconditions'] = $aclPreCondition;
            } else {
                $definition['preconditions'] = [
                    '@and' => [
                        $aclPreCondition,
                        $definition['preconditions']
                    ]
                ];
            }
        }

        /**
         * @see IsGrantedWorkflowTransition
         */
        $precondition = [
            '@is_granted_workflow_transition' => [
                'parameters' => [
                    $transitionName,
                    $this->getOption($options, 'step_to')
                ]
            ]
        ];
        if (empty($definition['preconditions'])) {
            $definition['preconditions'] = $precondition;
        } else {
            $definition['preconditions'] = [
                '@and' => [
                    $precondition,
                    $definition['preconditions']
                ]
            ];
        }

        return !empty($definition['preconditions']) ? $definition['preconditions'] : [];
    }

    /**
     * @param array $options
     * @param Attribute[]|Collection $attributes
     * @param string $transitionName
     * @return array
     */
    protected function assembleFormOptions(array $options, $attributes, $transitionName)
    {
        $formOptions = $this->getOption($options, 'form_options', array());
        return $this->formOptionsAssembler->assemble($formOptions, $attributes, 'transition', $transitionName);
    }
}
