<?php

namespace Oro\Bundle\WorkflowBundle\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;

use Oro\Bundle\WorkflowBundle\Entity\WorkflowDefinition;
use Oro\Bundle\WorkflowBundle\Exception\WorkflowNotFoundException;

class WorkflowRegistry
{
    /** @var ManagerRegistry */
    protected $managerRegistry;

    /** @var WorkflowAssembler */
    protected $workflowAssembler;

    /** @var Workflow[] */
    protected $workflowByName = [];

    /**
     * @param ManagerRegistry $managerRegistry
     * @param WorkflowAssembler $workflowAssembler
     */
    public function __construct(
        ManagerRegistry $managerRegistry,
        WorkflowAssembler $workflowAssembler
    ) {
        $this->managerRegistry = $managerRegistry;
        $this->workflowAssembler = $workflowAssembler;
    }

    /**
     * Get Workflow by name
     *
     * @param string $name
     * @param bool $exceptionOnNotFound
     * @return Workflow|null
     * @throws WorkflowNotFoundException
     */
    public function getWorkflow($name, $exceptionOnNotFound = true)
    {
        if (!is_string($name)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Expected value is workflow name string. But got %s',
                    is_object($name) ? get_class($name) : gettype($name)
                )
            );
        }

        if (!array_key_exists($name, $this->workflowByName)) {
            /** @var WorkflowDefinition $definition */
            $definition = $this->getEntityRepository()->find($name);
            if (!$definition) {
                if ($exceptionOnNotFound) {
                    throw new WorkflowNotFoundException($name);
                } else {
                    return null;
                }
            }

            return $this->getAssembledWorkflow($definition);
        }

        return $this->refreshWorkflow($this->workflowByName[$name]);
    }

    /**
     * Get Workflow by WorkflowDefinition
     *
     * @param WorkflowDefinition $definition
     * @return Workflow
     */
    protected function getAssembledWorkflow(WorkflowDefinition $definition)
    {
        $workflowName = $definition->getName();
        if (!array_key_exists($workflowName, $this->workflowByName)) {
            $workflow = $this->workflowAssembler->assemble($definition);
            $this->workflowByName[$workflowName] = $workflow;
        }

        return $this->refreshWorkflow($this->workflowByName[$workflowName]);
    }

    /**
     * Get Active Workflows that applicable to entity class
     *
     * @param string $entityClass
     * @return Workflow[]|ArrayCollection named array of active Workflow instances
     */
    public function getActiveWorkflowsByEntityClass($entityClass)
    {
        $class = ClassUtils::getRealClass($entityClass);

        $criteria = [
            'relatedEntity' => $class,
            'active' => true
        ];

        $workflows = new ArrayCollection();
        foreach ($this->getEntityRepository()->findBy($criteria) as $definition) {
            /** @var WorkflowDefinition $definition */
            $workflows->set($definition->getName(), $this->getAssembledWorkflow($definition));
        }

        return $workflows;
    }

    /**
     * Get Active Workflows by active groups
     *
     * @param array $groupNames
     * @return Workflow[]|array
     */
    public function getActiveWorkflowsByActiveGroups(array $groupNames)
    {
        $groupNames = array_map('strtolower', $groupNames);
        $definitions = array_filter(
            $this->getEntityRepository()->findBy(['active' => true]),
            function (WorkflowDefinition $definition) use ($groupNames) {
                $groups = $definition->getActiveGroups();
                foreach ($groupNames as $groupName) {
                    if (in_array($groupName, $groups, true)) {
                        return true;
                    }
                }

                return false;
            }
        );

        return array_map(
            function ($definition) {
                return $this->getAssembledWorkflow($definition);
            },
            $definitions
        );
    }
    
    /**
     * @return EntityManager
     */
    protected function getEntityManager()
    {
        return $this->managerRegistry->getManagerForClass(WorkflowDefinition::class);
    }

    /**
     * @return EntityRepository
     */
    protected function getEntityRepository()
    {
        return $this->getEntityManager()->getRepository(WorkflowDefinition::class);
    }

    /**
     * Ensure that all database entities in workflow are still in Doctrine Unit of Work
     *
     * @param Workflow $workflow
     * @return Workflow
     * @throws WorkflowNotFoundException
     */
    protected function refreshWorkflow(Workflow $workflow)
    {
        $refreshedDefinition = $this->refreshWorkflowDefinition($workflow->getDefinition());
        $workflow->setDefinition($refreshedDefinition);

        return $workflow;
    }

    /**
     * @param WorkflowDefinition $definition
     * @return WorkflowDefinition
     * @throws WorkflowNotFoundException
     */
    protected function refreshWorkflowDefinition(WorkflowDefinition $definition)
    {
        if (!$this->getEntityManager()->getUnitOfWork()->isInIdentityMap($definition)) {
            $definitionName = $definition->getName();

            $definition = $this->getEntityRepository()->find($definitionName);
            if (!$definition) {
                throw new WorkflowNotFoundException($definitionName);
            }
        }

        return $definition;
    }
}
