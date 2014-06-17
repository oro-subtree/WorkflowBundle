<?php

namespace Oro\Bundle\WorkflowBundle\EventListener;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnClearEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;

use JMS\JobQueueBundle\Entity\Job;

use Oro\Bundle\WorkflowBundle\Command\ExecuteProcessJobCommand;
use Oro\Bundle\WorkflowBundle\Entity\ProcessJob;
use Oro\Bundle\WorkflowBundle\Entity\ProcessTrigger;
use Oro\Bundle\WorkflowBundle\Model\ProcessData;
use Oro\Bundle\WorkflowBundle\Model\ProcessHandler;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;

class ProcessCollectorListener
{
    /**
     * @var ManagerRegistry
     */
    protected $registry;

    /**
     * @var DoctrineHelper
     */
    protected $doctrineHelper;

    /**
     * @var ProcessHandler
     */
    protected $handler;

    /**
     * @var array
     */
    protected $triggers;

    /**
     * @var array
     */
    protected $scheduledProcesses = array();

    /**
     * @var ProcessJob[]
     */
    protected $queuedJobs = array();

    /**
     * @var array
     */
    protected $removedEntityHashes = array();

    /**
     * @param ManagerRegistry $registry
     * @param DoctrineHelper $doctrineHelper
     * @param ProcessHandler $handler
     */
    public function __construct(ManagerRegistry $registry, DoctrineHelper $doctrineHelper, ProcessHandler $handler)
    {
        $this->registry = $registry;
        $this->doctrineHelper = $doctrineHelper;
        $this->handler = $handler;
    }

    /**
     * Cache triggers in the internal storage
     */
    protected function initializeTriggers()
    {
        if (null === $this->triggers) {
            $triggers = $this->registry->getRepository('OroWorkflowBundle:ProcessTrigger')->findAllWithDefinitions();
            $this->triggers = array();
            foreach ($triggers as $trigger) {
                $entityClass = $trigger->getDefinition()->getRelatedEntity();
                $event = $trigger->getEvent();
                $field = $trigger->getField();

                if ($event == ProcessTrigger::EVENT_UPDATE) {
                    if ($field) {
                        $this->triggers[$entityClass][$event]['field'][$field][] = $trigger;
                    } else {
                        $this->triggers[$entityClass][$event]['entity'][] = $trigger;
                    }
                } else {
                    $this->triggers[$entityClass][$event][] = $trigger;
                }
            }
        }
    }

    /**
     * @param string $entityClass
     * @param string $event
     * @param string|null $field
     * @return ProcessTrigger[]
     */
    protected function getTriggers($entityClass, $event, $field = null)
    {
        $this->initializeTriggers();

        if ($event == ProcessTrigger::EVENT_UPDATE) {
            if ($field) {
                if (!empty($this->triggers[$entityClass][$event]['field'][$field])) {
                    return $this->triggers[$entityClass][$event]['field'][$field];
                }
            } else {
                if (!empty($this->triggers[$entityClass][$event]['entity'])) {
                    return $this->triggers[$entityClass][$event]['entity'];
                }
            }
        } elseif (!empty($this->triggers[$entityClass][$event])) {
            return $this->triggers[$entityClass][$event];
        }

        return array();
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $entityClass = $this->getClass($entity);

        $triggers = $this->getTriggers($entityClass, ProcessTrigger::EVENT_CREATE);
        foreach ($triggers as $trigger) {
            $this->scheduleProcess($trigger, $entity);
        }
    }

    /**
     * @param PreUpdateEventArgs $args
     */
    public function preUpdate(PreUpdateEventArgs $args)
    {
        $entity = $args->getEntity();
        $entityClass = $this->getClass($entity);

        $entityTriggers = $this->getTriggers($entityClass, ProcessTrigger::EVENT_UPDATE);
        foreach ($entityTriggers as $trigger) {
            $this->scheduleProcess($trigger, $entity);
        }

        foreach (array_keys($args->getEntityChangeSet()) as $field) {
            $fieldTriggers = $this->getTriggers($entityClass, ProcessTrigger::EVENT_UPDATE, $field);
            foreach ($fieldTriggers as $trigger) {
                $this->scheduleProcess($trigger, $entity, $args->getOldValue($field), $args->getNewValue($field));
            }
        }
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function preRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $entityClass = $this->getClass($entity);
        $entityId = $this->doctrineHelper->getSingleEntityIdentifier($entity, false);

        $triggers = $this->getTriggers($entityClass, ProcessTrigger::EVENT_DELETE);
        foreach ($triggers as $trigger) {
            // cloned to save all data after flush
            $this->scheduleProcess($trigger, clone $entity);
        }

        if ($entityId) {
            $this->removedEntityHashes[] = ProcessJob::generateEntityHash($entityClass, $entityId);
        }
    }

    /**
     * @param OnClearEventArgs $args
     */
    public function onClear(OnClearEventArgs $args)
    {
        if ($args->clearsAllEntities()
            || $args->getEntityClass() == 'Oro\Bundle\WorkflowBundle\Entity\ProcessTrigger'
        ) {
            $this->triggers = null;
        }

        if ($args->clearsAllEntities()) {
            $this->scheduledProcesses = array();
        } else {
            unset($this->scheduledProcesses[$args->getEntityClass()]);
        }
    }

    /**
     * @param PostFlushEventArgs $args
     */
    public function postFlush(PostFlushEventArgs $args)
    {
        $entityManager = $args->getEntityManager();

        // handle processes
        $hasHandledProcessed = false;
        foreach ($this->scheduledProcesses as $entityClass => &$entityProcesses) {
            while ($entityProcess = array_shift($entityProcesses)) {
                /** @var ProcessTrigger $trigger */
                $trigger = $entityProcess['trigger'];
                /** @var ProcessData $data */
                $data = $entityProcess['data'];

                if ($trigger->isQueued()) {
                    $processJob = $this->queueProcess($trigger, $data);
                    $entityManager->persist($processJob);
                    $this->queuedJobs[] = $processJob;
                } else {
                    $this->handler->handleTrigger($trigger, $data);
                }

                $hasHandledProcessed = true;
            }
        }

        // save both handled entities and queued ProcessJobs
        if ($hasHandledProcessed) {
            $entityManager->flush();
        }

        // delete unused processes
        if ($this->removedEntityHashes) {
            $this->registry->getRepository('OroWorkflowBundle:ProcessJob')->deleteByHashes($this->removedEntityHashes);
            $this->removedEntityHashes = array();
        }

        // create JMS Jobs for queued jobs
        $hasQueuedJobs = false;
        /** @var ProcessJob $processJob */
        while ($processJob = array_shift($this->queuedJobs)) {
            $jmsJob = new Job(ExecuteProcessJobCommand::NAME, ['--id=' . $processJob->getId()]);

            $timeShiftInterval = $processJob->getProcessTrigger()->getTimeShiftInterval();
            if ($timeShiftInterval) {
                $executeAfter = new \DateTime('now', new \DateTimeZone('UTC'));
                $executeAfter->add($timeShiftInterval);
                $jmsJob->setExecuteAfter($executeAfter);
            }

            $entityManager->persist($jmsJob);
            $hasQueuedJobs = true;
        }

        // save JMS Job instances
        if ($hasQueuedJobs) {
            $entityManager->flush();
        }
    }

    /**
     * @param ProcessTrigger $trigger
     * @param ProcessData $data
     * @return ProcessJob
     */
    protected function queueProcess(ProcessTrigger $trigger, ProcessData $data)
    {
        $processJob = new ProcessJob();
        $processJob->setProcessTrigger($trigger)
            ->setData($data);

        return $processJob;
    }

    /**
     * @param ProcessTrigger $trigger
     * @param object $entity
     * @param mixed|null $old
     * @param mixed|null $new
     */
    protected function scheduleProcess(ProcessTrigger $trigger, $entity, $old = null, $new = null)
    {
        $entityClass = $this->getClass($entity);

        $data = new ProcessData(array('entity' => $entity));
        if ($old || $new) {
            $data->set('old', $old)->set('new', $new);
        }

        $this->scheduledProcesses[$entityClass][] = array('trigger' => $trigger, 'data' => $data);
    }

    /**
     * @param object $entity
     * @return string
     */
    protected function getClass($entity)
    {
        return ClassUtils::getClass($entity);
    }
}
