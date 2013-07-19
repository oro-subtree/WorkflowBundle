<?php

namespace Oro\Bundle\WorkflowBundle\Model\Condition;

use Oro\Bundle\WorkflowBundle\Entity\WorkflowItem;

class TrueCondition implements ConditionInterface
{
    /**
     * Always return TRUE
     *
     * @param WorkflowItem $workflowItem
     * @return boolean
     */
    public function isAllowed(WorkflowItem $workflowItem)
    {
        return true;
    }

    /**
     * Nothing to initialize
     *
     * @param array $options
     */
    public function initialize(array $options)
    {

    }
}
