<?php

namespace Oro\Bundle\WorkflowBundle\Model\TransitionTrigger;

use Oro\Bundle\WorkflowBundle\Entity\AbstractTransitionTrigger;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowDefinition;

abstract class TriggerAbstractAssembler implements TriggerAssemblerInterface
{
    /**
     * @param array $options
     * @param WorkflowDefinition $workflowDefinition
     * @return AbstractTransitionTrigger
     */
    abstract protected function assembleTrigger(array $options, WorkflowDefinition $workflowDefinition);

    /**
     * @param array $options
     * @param string $transitionName
     * @param WorkflowDefinition $workflowDefinition
     * @return AbstractTransitionTrigger
     */
    public function assemble(array $options, $transitionName, WorkflowDefinition $workflowDefinition)
    {
        if (false === $this->canAssemble($options)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Can not assemble trigger for transition %s in workflow %s by provided options %s.',
                    $transitionName,
                    $workflowDefinition->getName(),
                    var_export($options, 1)
                )
            );
        }

        $trigger = $this->assembleTrigger($options, $workflowDefinition);

        return $trigger
            ->setWorkflowDefinition($workflowDefinition)
            ->setTransitionName($transitionName)
            ->setQueued($this->getOption($options, 'queued', true));
    }

    /**
     * @param array $options
     * @param string $optionKey
     * @param mixed $defaultValue
     * @return mixed
     */
    protected function getOption(array $options, $optionKey, $defaultValue = null)
    {
        return array_key_exists($optionKey, $options) ? $options[$optionKey] : $defaultValue;
    }
}
