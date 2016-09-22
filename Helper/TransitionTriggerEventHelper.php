<?php

namespace Oro\Bundle\WorkflowBundle\Helper;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

use Oro\Bundle\WorkflowBundle\Entity\TransitionTriggerEvent;
use Oro\Bundle\WorkflowBundle\Model\WorkflowManager;

use Oro\Component\PropertyAccess\PropertyAccessor;

class TransitionTriggerEventHelper
{
    const TRIGGER_ENTITY = 'entity';
    const TRIGGER_WORKFLOW_ENTITY = 'mainEntity';
    const TRIGGER_WORKFLOW_DEFINITION = 'wd';
    const TRIGGER_WORKFLOW_ITEM = 'wi';

    /** @var WorkflowManager */
    private $workflowManager;

    /**
     * @param WorkflowManager $workflowManager
     */
    public function __construct(WorkflowManager $workflowManager)
    {
        $this->workflowManager = $workflowManager;
    }

    /**
     * @param TransitionTriggerEvent $trigger
     * @param object $entity
     * @return bool
     */
    public function isRequirePass(TransitionTriggerEvent $trigger, $entity)
    {
        $require = $trigger->getRequire();
        if (!$require) {
            return true;
        }

        $expressionLanguage = new ExpressionLanguage();

        $result = $expressionLanguage->evaluate($require, $this->getExpressionValues($trigger, $entity));

        return !empty($result);
    }

    /**
     * @param TransitionTriggerEvent $trigger
     * @param object $entity
     * @return array
     */
    private function getExpressionValues(TransitionTriggerEvent $trigger, $entity)
    {
        $relation = $trigger->getRelation();
        if ($relation) {
            $propertyAccessor = new PropertyAccessor();

            $mainEntity = $propertyAccessor->getValue($entity, $trigger->getRelation());
        } else {
            $mainEntity = $entity;
        }

        $workflowDefinition = $trigger->getWorkflowDefinition();

        $mainEntityClass = $workflowDefinition->getRelatedEntity();
        if (!$mainEntity instanceof $mainEntityClass) {
            throw new \RuntimeException(
                sprintf('Can\'t get main entity using relation "%s"', $relation)
            );
        }

        $workflowItem = $this->workflowManager->getWorkflowItem($mainEntity, $workflowDefinition->getName());

        return [
            self::TRIGGER_WORKFLOW_DEFINITION => $workflowDefinition,
            self::TRIGGER_WORKFLOW_ITEM => $workflowItem,
            self::TRIGGER_ENTITY => $entity,
            self::TRIGGER_WORKFLOW_ENTITY => $mainEntity,
        ];
    }
}
