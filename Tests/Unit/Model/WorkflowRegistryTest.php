<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Model;

use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;

use Oro\Bundle\WorkflowBundle\Entity\WorkflowDefinition;
use Oro\Bundle\WorkflowBundle\Model\WorkflowRegistry;
use Oro\Bundle\WorkflowBundle\Model\Workflow;
use Oro\Bundle\WorkflowBundle\Model\WorkflowAssembler;

class WorkflowRegistryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function createWorkflowDefinitionRepositoryMock()
    {
        $workflowDefinitionRepository
            = $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Entity\Repository\WorkflowDefinitionRepository')
            ->disableOriginalConstructor()
            ->setMethods(array('find', 'findByEntityClass'))
            ->getMock();

        return $workflowDefinitionRepository;
    }

    /**
     * @param \PHPUnit_Framework_MockObject_MockObject|null $workflowDefinitionRepository
     *
     * @return DoctrineHelper
     */
    protected function createDoctrineHelperMock($workflowDefinitionRepository = null)
    {
        $doctrineHelper = $this->getMockBuilder('Oro\Bundle\EntityBundle\ORM\DoctrineHelper')
            ->disableOriginalConstructor()
            ->setMethods(array('getEntityRepositoryForClass', 'getEntityManagerForClass'))
            ->getMockForAbstractClass();
        if ($workflowDefinitionRepository) {
            $doctrineHelper->expects($this->any())
                ->method('getEntityRepositoryForClass')
                ->with('OroWorkflowBundle:WorkflowDefinition')
                ->will($this->returnValue($workflowDefinitionRepository));
        }

        return $doctrineHelper;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function createConfigurationProviderMock()
    {
        return $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider')
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @param WorkflowDefinition|null $workflowDefinition
     * @param Workflow|null $workflow
     * @return WorkflowAssembler
     */
    public function createWorkflowAssemblerMock($workflowDefinition = null, $workflow = null)
    {
        $workflowAssembler = $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Model\WorkflowAssembler')
            ->disableOriginalConstructor()
            ->setMethods(array('assemble'))
            ->getMock();
        if ($workflowDefinition && $workflow) {
            $workflowAssembler->expects($this->once())
                ->method('assemble')
                ->with($workflowDefinition)
                ->will($this->returnValue($workflow));
        } else {
            $workflowAssembler->expects($this->never())
                ->method('assemble');
        }

        return $workflowAssembler;
    }

    public function testGetWorkflow()
    {
        $workflowName = 'test_workflow';
        $workflowDefinition = new WorkflowDefinition();
        $workflowDefinition->setName($workflowName);

        $workflow = $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Model\Workflow')
            ->disableOriginalConstructor()
            ->getMock();
        $workflow->expects($this->any())
            ->method('getDefinition')
            ->will($this->returnValue($workflowDefinition));

        $workflowDefinitionRepository = $this->createWorkflowDefinitionRepositoryMock();
        $workflowDefinitionRepository->expects($this->once())
            ->method('find')
            ->with($workflowName)
            ->will($this->returnValue($workflowDefinition));
        $doctrineHelper = $this->createDoctrineHelperMock($workflowDefinitionRepository);
        $workflowAssembler = $this->createWorkflowAssemblerMock($workflowDefinition, $workflow);
        $configProvider = $this->createConfigurationProviderMock();
        $this->setUpEntityManagerMock($doctrineHelper, $workflowDefinition);

        $workflowRegistry = new WorkflowRegistry($doctrineHelper, $workflowAssembler, $configProvider);
        // run twice to test cache storage inside registry
        $this->assertEquals($workflow, $workflowRegistry->getWorkflow($workflowName));
        $this->assertEquals($workflow, $workflowRegistry->getWorkflow($workflowName));
        $this->assertAttributeEquals(array($workflowName => $workflow), 'workflowByName', $workflowRegistry);
    }

    public function testGetWorkflowWithDbEntitiesUpdate()
    {
        $workflowName = 'test_workflow';
        $oldDefinition = new WorkflowDefinition();
        $oldDefinition->setName($workflowName)->setLabel('Old Workflow');
        $newDefinition = new WorkflowDefinition();
        $newDefinition->setName($workflowName)->setLabel('New Workflow');

        /** @var Workflow $workflow */
        $workflow = $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Model\Workflow')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();
        $workflow->setDefinition($oldDefinition);

        $workflowDefinitionRepository = $this->createWorkflowDefinitionRepositoryMock();
        $workflowDefinitionRepository->expects($this->at(0))
            ->method('find')
            ->with($workflowName)
            ->will($this->returnValue($oldDefinition));
        $workflowDefinitionRepository->expects($this->at(1))
            ->method('find')
            ->with($workflowName)
            ->will($this->returnValue($newDefinition));
        $doctrineHelper = $this->createDoctrineHelperMock($workflowDefinitionRepository);
        $workflowAssembler = $this->createWorkflowAssemblerMock($oldDefinition, $workflow);
        $configProvider = $this->createConfigurationProviderMock();
        $this->setUpEntityManagerMock($doctrineHelper, $oldDefinition, false);

        $workflowRegistry = new WorkflowRegistry($doctrineHelper, $workflowAssembler, $configProvider);
        $this->assertEquals($workflow, $workflowRegistry->getWorkflow($workflowName));
        $this->assertEquals($newDefinition, $workflow->getDefinition());
        $this->assertAttributeEquals(array($workflowName => $workflow), 'workflowByName', $workflowRegistry);
    }

    /**
     * @expectedException \Oro\Bundle\WorkflowBundle\Exception\WorkflowNotFoundException
     * @expectedExceptionMessage Workflow "test_workflow" not found
     */
    public function testGetWorkflowNoUpdatedEntity()
    {
        $workflowName = 'test_workflow';
        $workflowDefinition = new WorkflowDefinition();
        $workflowDefinition->setName($workflowName);

        /** @var Workflow $workflow */
        $workflow = $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Model\Workflow')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();
        $workflow->setDefinition($workflowDefinition);

        $workflowDefinitionRepository = $this->createWorkflowDefinitionRepositoryMock();
        $workflowDefinitionRepository->expects($this->at(0))
            ->method('find')
            ->with($workflowName)
            ->will($this->returnValue($workflowDefinition));
        $workflowDefinitionRepository->expects($this->at(1))
            ->method('find')
            ->with($workflowName)
            ->will($this->returnValue(null));
        $doctrineHelper = $this->createDoctrineHelperMock($workflowDefinitionRepository);
        $workflowAssembler = $this->createWorkflowAssemblerMock($workflowDefinition, $workflow);
        $configProvider = $this->createConfigurationProviderMock();
        $this->setUpEntityManagerMock($doctrineHelper, $workflowDefinition, false);

        $workflowRegistry = new WorkflowRegistry($doctrineHelper, $workflowAssembler, $configProvider);
        $workflowRegistry->getWorkflow($workflowName);
    }

    /**
     * @param \PHPUnit_Framework_MockObject_MockObject $doctrineHelper
     * @param WorkflowDefinition $workflowDefinition
     * @param boolean $isEntityKnown
     */
    protected function setUpEntityManagerMock($doctrineHelper, $workflowDefinition, $isEntityKnown = true)
    {
        $unitOfWork = $this->getMockBuilder('Doctrine\ORM\UnitOfWork')
            ->disableOriginalConstructor()
            ->getMock();
        $unitOfWork->expects($this->any())->method('isInIdentityMap')->with($workflowDefinition)
            ->will($this->returnValue($isEntityKnown));

        $entityManager = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->setMethods(['getUnitOfWork'])
            ->getMock();
        $entityManager->expects($this->any())->method('getUnitOfWork')
            ->will($this->returnValue($unitOfWork));

        $doctrineHelper->expects($this->any())->method('getEntityManagerForClass')
            ->with('OroWorkflowBundle:WorkflowDefinition')->will($this->returnValue($entityManager));
    }

    /**
     * @expectedException \Oro\Bundle\WorkflowBundle\Exception\WorkflowNotFoundException
     * @expectedExceptionMessage Workflow "not_existing_workflow" not found
     */
    public function testGetWorkflowNotFoundException()
    {
        $workflowName = 'not_existing_workflow';

        $workflowDefinitionRepository = $this->createWorkflowDefinitionRepositoryMock();
        $workflowDefinitionRepository->expects($this->once())
            ->method('find')
            ->with($workflowName)
            ->will($this->returnValue(null));
        $doctrineHelper = $this->createDoctrineHelperMock($workflowDefinitionRepository);
        $workflowAssembler = $this->createWorkflowAssemblerMock();
        $configProvider = $this->createConfigurationProviderMock();

        $workflowRegistry = new WorkflowRegistry($doctrineHelper, $workflowAssembler, $configProvider);
        $workflowRegistry->getWorkflow($workflowName);
    }
}
