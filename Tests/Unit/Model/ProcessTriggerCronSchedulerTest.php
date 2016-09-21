<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Model;

use Oro\Bundle\CronBundle\Entity\Manager\DeferredScheduler;
use Oro\Bundle\WorkflowBundle\Command\HandleProcessTriggerCommand;
use Oro\Bundle\WorkflowBundle\Entity\ProcessDefinition;
use Oro\Bundle\WorkflowBundle\Entity\ProcessTrigger;
use Oro\Bundle\WorkflowBundle\Model\ProcessTriggerCronScheduler;

class ProcessTriggerCronSchedulerTest extends \PHPUnit_Framework_TestCase
{
    /** @var DeferredScheduler|\PHPUnit_Framework_MockObject_MockObject */
    private $deferredScheduler;

    /** @var ProcessTriggerCronScheduler */
    protected $processCronScheduler;

    protected function setUp()
    {
        $this->deferredScheduler = $this->getMockBuilder(DeferredScheduler::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->processCronScheduler = new ProcessTriggerCronScheduler($this->deferredScheduler);
    }

    public function testAdd()
    {
        /** @var ProcessTrigger|\PHPUnit_Framework_MockObject_MockObject $trigger * */
        $trigger = $this->getMock(ProcessTrigger::class);

        $cronExpression = '* * * * *';
        $trigger->expects($this->any())
            ->method('getCron')
            ->willReturn($cronExpression);

        //create arguments
        $processDefinitionMock = $this->getMockBuilder(ProcessDefinition::class)->getMock();
        $trigger->expects($this->once())
            ->method('getDefinition')
            ->willReturn($processDefinitionMock);
        $processDefinitionMock->expects($this->once())
            ->method('getName')
            ->willReturn('process-definition-name');
        $trigger->expects($this->once())
            ->method('getId')
            ->willReturn(100500);

        $arguments = ['--name=process-definition-name', '--id=100500'];

        $this->deferredScheduler->expects($this->once())
            ->method('addSchedule')
            ->with(HandleProcessTriggerCommand::NAME, $arguments, $cronExpression);

        $this->processCronScheduler->add($trigger);
    }

    public function testRemoveSchedule()
    {
        /** @var ProcessTrigger|\PHPUnit_Framework_MockObject_MockObject $mockTrigger */
        $mockTrigger = $this->getMock(ProcessTrigger::class);
        $mockProcessDefinition = $this->getMock(ProcessDefinition::class);
        $mockProcessDefinition->expects($this->once())->method('getName')->willReturn('process_name');

        $mockTrigger->expects($this->exactly(2))->method('getCron')->willReturn('* * * * *');
        $mockTrigger->expects($this->exactly(1))->method('getId')->willReturn(42);
        $mockTrigger->expects($this->once())->method('getDefinition')->willReturn($mockProcessDefinition);

        $this->deferredScheduler->expects($this->once())
            ->method('removeSchedule')
            ->with('oro:process:handle-trigger', ['--name=process_name', '--id=42'], '* * * * *');

        $this->processCronScheduler->removeSchedule($mockTrigger);
    }

    public function testException()
    {
        /** @var ProcessTrigger|\PHPUnit_Framework_MockObject_MockObject $mockTrigger */
        $mockTrigger = $this->getMock(ProcessTrigger::class);
        $mockTrigger->expects($this->exactly(1))->method('getCron')->willReturn(null);
        $this->setExpectedException(
            'InvalidArgumentException',
            'Oro\Bundle\WorkflowBundle\Model\ProcessTriggerCronScheduler supports only cron schedule triggers.'
        );

        $this->processCronScheduler->removeSchedule($mockTrigger);
    }

    public function testAddException()
    {
        /** @var ProcessTrigger|\PHPUnit_Framework_MockObject_MockObject $trigger * */
        $trigger = $this->getMock(ProcessTrigger::class);
        $trigger->expects($this->once())->method('getCron')->willReturn(null);

        $this->setExpectedException('InvalidArgumentException');
        $this->processCronScheduler->add($trigger);
    }
}
