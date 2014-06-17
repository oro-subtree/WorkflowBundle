<?php

namespace Oro\Bundle\WorkflowBundle\Model;

use Oro\Bundle\WorkflowBundle\Entity\ProcessJob;
use Oro\Bundle\WorkflowBundle\Entity\ProcessTrigger;
use Oro\Bundle\WorkflowBundle\Exception\InvalidParameterException;

class ProcessHandler
{
    /**
     * @var ProcessFactory
     */
    protected $processFactory;

    /**
     * @param ProcessFactory $processFactory
     */
    public function __construct(ProcessFactory $processFactory)
    {
        $this->processFactory = $processFactory;
    }

    /**
     * @param ProcessTrigger $processTrigger
     * @param ProcessData $processData
     * @throws InvalidParameterException
     */
    public function handleTrigger(ProcessTrigger $processTrigger, ProcessData $processData)
    {
        if (!$processData['entity']) {
            throw new InvalidParameterException('Invalid process data. Entity parameter can not be empty.');
        }
        $process = $this->processFactory->create($processTrigger->getDefinition());
        $process->execute($processData);
    }

    /**
     * @param ProcessJob $processJob
     */
    public function handleJob(ProcessJob $processJob)
    {
        $this->handleTrigger($processJob->getProcessTrigger(), $processJob->getData());
    }
}
