<?php

namespace Oro\Bundle\WorkflowBundle\Model\PostAction;

use Oro\Bundle\WorkflowBundle\Entity\WorkflowItem;

interface PostActionInterface
{
    /**
     * Execute post action.
     *
     * @param WorkflowItem $workflowItem
     */
    public function execute(WorkflowItem $workflowItem);
}