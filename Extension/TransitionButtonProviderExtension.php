<?php

namespace Oro\Bundle\WorkflowBundle\Extension;

use Oro\Bundle\ActionBundle\Helper\ApplicationsHelperInterface;
use Oro\Bundle\ActionBundle\Model\ButtonContext;
use Oro\Bundle\ActionBundle\Model\ButtonProviderExtensionInterface;
use Oro\Bundle\ActionBundle\Model\ButtonSearchContext;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowItem;
use Oro\Bundle\WorkflowBundle\Model\Transition;
use Oro\Bundle\WorkflowBundle\Model\TransitionButton;
use Oro\Bundle\WorkflowBundle\Model\Workflow;
use Oro\Bundle\WorkflowBundle\Model\WorkflowData;
use Oro\Bundle\WorkflowBundle\Model\WorkflowRegistry;

class TransitionButtonProviderExtension implements ButtonProviderExtensionInterface
{
    /** @var WorkflowRegistry */
    protected $workflowRegistry;

    /** @var ApplicationsHelperInterface */
    protected $applicationsHelper;

    /**
     * @param WorkflowRegistry $workflowRegistry
     * @param ApplicationsHelperInterface $applicationsHelper
     */
    public function __construct(WorkflowRegistry $workflowRegistry, ApplicationsHelperInterface $applicationsHelper)
    {
        $this->workflowRegistry = $workflowRegistry;
        $this->applicationsHelper = $applicationsHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function find(ButtonSearchContext $buttonSearchContext)
    {
        $buttons = [];

        if ($buttonSearchContext->getGroup()) {
            return $buttons;
        }

        foreach ($this->workflowRegistry->getActiveWorkflows() as $workflow) {
            $transitions = $this->getInitTransitions($workflow, $buttonSearchContext);

            foreach ($transitions as $transition) {
                $workflowItem = $this->buildWorkflowItem($transition, $workflow, $buttonSearchContext);
                $isAvailable = $transition->isAvailable(clone $workflowItem);
                if ($isAvailable || !$transition->isUnavailableHidden()) {
                    $buttonContext = $this->generateButtonContext($transition, $buttonSearchContext);
                    $buttonContext->setEnabled($isAvailable);
                    $buttons[] = new TransitionButton($transition, $workflow, $buttonContext);
                }
            }
        }

        return $buttons;
    }

    /**
     * @param Transition $transition
     * @param Workflow $workflow
     * @param ButtonSearchContext $searchContext
     *
     * @return WorkflowItem
     */
    protected function buildWorkflowItem(Transition $transition, Workflow $workflow, ButtonSearchContext $searchContext)
    {
        $workflowData = new WorkflowData([$transition->getInitContextAttribute() => $searchContext]);
        $workflowItem = new WorkflowItem();

        return $workflowItem->setEntityClass($workflow->getDefinition()->getRelatedEntity())
            ->setDefinition($workflow->getDefinition())
            ->setWorkflowName($workflow->getName())
            ->setData($workflowData);
    }

    /**
     * @param Transition $transition
     * @param ButtonSearchContext $searchContext
     *
     * @return ButtonContext
     */
    protected function generateButtonContext(Transition $transition, ButtonSearchContext $searchContext)
    {
        $context = new ButtonContext();
        $context->setDatagridName($searchContext->getGridName())
            ->setEntity($searchContext->getEntityClass(), $searchContext->getEntityId())
            ->setRouteName($searchContext->getRouteName())
            ->setGroup($searchContext->getGroup())
            ->setUnavailableHidden($transition->isUnavailableHidden());

        if ($transition->hasForm()) {
            $context->setDialogUrl($this->applicationsHelper->getDialogRoute());
        }
        $context->setExecutionUrl($this->applicationsHelper->getExecutionRoute());

        return $context;
    }

    /**
     * @param Workflow $workflow
     * @param ButtonSearchContext $searchContext
     *
     * @return Transition[]
     */
    protected function getInitTransitions(Workflow $workflow, ButtonSearchContext $searchContext)
    {
        $transitionNames = [];
        if ($searchContext->getEntityClass()) {
            $entities = $workflow->getInitEntities();
            if (array_key_exists($searchContext->getEntityClass(), $entities)) {
                $transitionNames = $entities[$searchContext->getEntityClass()];
            }
        }
        if ($searchContext->getRouteName()) {
            $routes = $workflow->getInitRoutes();
            if (array_key_exists($searchContext->getRouteName(), $routes)) {
                $transitionNames = array_merge($transitionNames, $routes[$searchContext->getRouteName()]);
            }
        }

        return array_filter(
            $workflow->getTransitionManager()->getStartTransitions()->toArray(),
            function (Transition $transition) use ($transitionNames) {
                return in_array($transition->getName(), $transitionNames, true);
            }
        );
    }
}
