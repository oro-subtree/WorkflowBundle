<?php

namespace Oro\Bundle\WorkflowBundle\Model\TransitionTrigger;

use Oro\Bundle\WorkflowBundle\Entity\TransitionCronTrigger;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowDefinition;

class TransitionCronTriggerAssembler extends AbstractTransitionTriggerAssembler
{
    /** @var TransitionTriggerCronVerifier */
    protected $triggerCronVerifier;

    /**
     * @param TransitionTriggerCronVerifier $triggerCronVerifier
     */
    public function __construct(TransitionTriggerCronVerifier $triggerCronVerifier)
    {
        $this->triggerCronVerifier = $triggerCronVerifier;
    }

    /**
     * {@inheritdoc}
     */
    public function canAssemble(array $options)
    {
        return !empty($options['cron']);
    }

    /**
     * {@inheritdoc}
     */
    protected function assembleTrigger(array $options, WorkflowDefinition $workflowDefinition)
    {
        $trigger = new TransitionCronTrigger();
        $trigger
            ->setCron($options['cron'])
            ->setFilter($this->getOption($options, 'filter', null))
            ->setQueued($this->getOption($options, 'queued', true));

        $this->triggerCronVerifier->verify($trigger);

        return $trigger;
    }
}
