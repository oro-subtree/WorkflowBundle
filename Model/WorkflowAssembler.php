<?php

namespace Oro\Bundle\WorkflowBundle\Model;

use Doctrine\Common\Collections\ArrayCollection;

use Oro\Bundle\WorkflowBundle\Entity\WorkflowDefinition;
use Oro\Bundle\WorkflowBundle\Configuration\ConfigurationTree;
use Oro\Bundle\WorkflowBundle\Model\Workflow;

class WorkflowAssembler
{
    /**
     * @var ConfigurationTree
     */
    protected $configurationTree;

    /**
     * @var AttributeAssembler
     */
    protected $attributeAssembler;

    /**
     * @var StepAssembler
     */
    protected $stepAssembler;

    /**
     * @var TransitionAssembler
     */
    protected $transitionAssembler;

    /**
     * @param ConfigurationTree $configurationTreeBuilder
     * @param AttributeAssembler $attributeAssembler
     * @param StepAssembler $stepAssembler
     * @param TransitionAssembler $transitionAssembler
     */
    public function __construct(
        ConfigurationTree $configurationTreeBuilder,
        AttributeAssembler $attributeAssembler,
        StepAssembler $stepAssembler,
        TransitionAssembler $transitionAssembler
    ) {
        $this->configurationTree = $configurationTreeBuilder;
        $this->attributeAssembler = $attributeAssembler;
        $this->stepAssembler = $stepAssembler;
        $this->transitionAssembler = $transitionAssembler;
    }

    /**
     * @param WorkflowDefinition $workflowDefinition
     * @return Workflow
     */
    public function assemble(WorkflowDefinition $workflowDefinition)
    {
        $configuration = $this->configurationTree->parseConfiguration($workflowDefinition->getConfiguration());

        $attributes = $this->assembleAttributes($configuration);
        $steps = $this->assembleSteps($configuration, $attributes);
        $transitions = $this->assembleTransitions($configuration, $steps);

        $workflow = new Workflow();
        $workflow
            ->setName($workflowDefinition->getName())
            ->setLabel($workflowDefinition->getLabel())
            ->setEnabled($workflowDefinition->isEnabled())
            ->setStartStepName($workflowDefinition->getStartStep())
            ->setManagedEntityClass($workflowDefinition->getManagedEntityClass())
            ->setAttributes($attributes)
            ->setSteps($steps)
            ->setTransitions($transitions);

        return $workflow;
    }

    /**
     * @param array $configuration
     * @return ArrayCollection
     */
    protected function assembleAttributes(array $configuration)
    {
        $attributesConfiguration = $configuration[ConfigurationTree::NODE_ATTRIBUTES];

        return $this->attributeAssembler->assemble($attributesConfiguration);
    }

    /**
     * @param array $configuration
     * @param ArrayCollection $attributes
     * @return ArrayCollection
     */
    protected function assembleSteps(array $configuration, ArrayCollection $attributes)
    {
        $stepsConfiguration = $configuration[ConfigurationTree::NODE_STEPS];

        return $this->stepAssembler->assemble($stepsConfiguration, $attributes);
    }

    /**
     * @param array $configuration
     * @param ArrayCollection $steps
     * @return ArrayCollection
     */
    protected function assembleTransitions(array $configuration, ArrayCollection $steps)
    {
        $transitionsConfiguration = $configuration[ConfigurationTree::NODE_TRANSITIONS];
        $transitionDefinitionsConfiguration = $configuration[ConfigurationTree::NODE_TRANSITION_DEFINITIONS];

        return $this->transitionAssembler->assemble(
            $transitionsConfiguration,
            $transitionDefinitionsConfiguration,
            $steps
        );
    }
}
