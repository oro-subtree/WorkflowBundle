<?php
namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Async;

use Psr\Log\LoggerInterface;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

use Oro\Component\MessageQueue\Client\TopicSubscriberInterface;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Transport\Null\NullMessage;
use Oro\Component\MessageQueue\Transport\Null\NullSession;
use Oro\Component\MessageQueue\Util\JSON;
use Oro\Component\Testing\ClassExtensionTrait;

use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\WorkflowBundle\Async\ExecuteProcessJobProcessor;
use Oro\Bundle\WorkflowBundle\Async\Topics;
use Oro\Bundle\WorkflowBundle\Entity\ProcessJob;
use Oro\Bundle\WorkflowBundle\Model\ProcessHandler;

class ExecuteProcessJobProcessorTest extends \PHPUnit_Framework_TestCase
{
    use ClassExtensionTrait;

    public function testShouldImplementMessageProcessorInterface()
    {
        $this->assertClassImplements(MessageProcessorInterface::class, ExecuteProcessJobProcessor::class);
    }

    public function testShouldImplementTopicSubscriberInterface()
    {
        $this->assertClassImplements(TopicSubscriberInterface::class, ExecuteProcessJobProcessor::class);
    }

    public function testShouldSubscribeOnExecuteProcessJobTopic()
    {
        self::assertEquals([Topics::EXECUTE_PROCESS_JOB], ExecuteProcessJobProcessor::getSubscribedTopics());
    }

    public function testCouldBeConstructedWithDoctrineHelperAndProcessHandler()
    {
        new ExecuteProcessJobProcessor(
            $this->createDoctrineHelperStub(),
            $this->createProcessHandlerMock(),
            $this->createLoggerMock()
        );
    }

    public function testThrowIfMessageBodyIsNotValidJson()
    {
        $processor = new ExecuteProcessJobProcessor(
            $this->createDoctrineHelperStub(),
            $this->createProcessHandlerMock(),
            $this->createLoggerMock()
        );

        $message = new NullMessage();
        $message->setBody('{]');

        $this->setExpectedException(\InvalidArgumentException::class, 'The malformed json given.');
        $processor->process($message, new NullSession());
    }

    public function testShouldRejectMessageIfProcessJobIdNotSet()
    {
        $logger = $this->createLoggerMock();

        $logger
            ->expects(self::once())
            ->method('critical')
        ;

        $processor = new ExecuteProcessJobProcessor(
            $this->createDoctrineHelperStub(),
            $this->createProcessHandlerMock(),
            $logger
        );

        $message = new NullMessage();
        $message->setBody('{}');

        $status = $processor->process($message, new NullSession());

        self::assertEquals(MessageProcessorInterface::REJECT, $status);
    }

    public function testShouldRejectMessageIfProcessJobIdWithSuchIdNotFound()
    {
        $entityManager = $this->createEntityManagerMock();

        $entityRepository = $this->createEntityRepositoryMock();
        $entityRepository
            ->expects($this->once())
            ->method('find')
            ->with('theProcessJobId')
            ->willReturn(null)
        ;

        $doctrineHelper = $this->createDoctrineHelperStub($entityManager, $entityRepository);

        $logger = $this->createLoggerMock();

        $logger
            ->expects(self::once())
            ->method('critical')
        ;

        $processor = new ExecuteProcessJobProcessor(
            $doctrineHelper,
            $this->createProcessHandlerMock(),
            $logger
        );

        $message = new NullMessage();
        $message->setBody(JSON::encode(['process_job_id' => 'theProcessJobId']));

        $status = $processor->process($message, new NullSession());

        self::assertEquals(MessageProcessorInterface::REJECT, $status);
    }

    public function testShouldRejectMessageIfEntityManagerNotExist()
    {
        $doctrineHelper = $this->createDoctrineHelperStub();

        $processHandle = $this->createProcessHandlerMock();

        $logger = $this->createLoggerMock();

        $logger
            ->expects(self::once())
            ->method('critical')
         ;

        $processor = new ExecuteProcessJobProcessor(
            $doctrineHelper,
            $processHandle,
            $logger
        );

        $message = new NullMessage();
        $message->setBody(JSON::encode(['process_job_id' => 'theProcessJobId']));

        $status = $processor->process($message, new NullSession());

        self::assertEquals(MessageProcessorInterface::REJECT, $status);
    }

    public function testShouldExecuteProcessJob()
    {

        $processJob = new ProcessJob();

        $entityManager = $this->createEntityManagerMock();
        $entityManager
            ->expects(self::once())
            ->method('commit')
        ;

        $entityRepository = $this->createEntityRepositoryMock();
        $entityRepository
            ->expects($this->once())
            ->method('find')
            ->with('theProcessJobId')
            ->willReturn($processJob)
        ;

        $processHandle = $this->createProcessHandlerMock();
        $processHandle
            ->expects($this->once())
            ->method('handleJob')
            ->with(self::identicalTo($processJob))
        ;

        $processHandle
            ->expects($this->once())
            ->method('finishJob')
            ->with(self::identicalTo($processJob))
        ;

        $doctrineHelper = $this->createDoctrineHelperStub($entityManager, $entityRepository);

        $processor = new ExecuteProcessJobProcessor(
            $doctrineHelper,
            $processHandle,
            $this->createLoggerMock()
        );

        $message = new NullMessage();
        $message->setBody(JSON::encode(['process_job_id' => 'theProcessJobId']));

        $status = $processor->process($message, new NullSession());

        self::assertEquals(MessageProcessorInterface::ACK, $status);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|ProcessHandler
     */
    private function createProcessHandlerMock()
    {
        return $this->getMock(ProcessHandler::class, [], [], '', false);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|EntityManagerInterface
     */
    private function createEntityManagerMock()
    {
        return $this->getMock(EntityManagerInterface::class);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|EntityRepository
     */
    private function createEntityRepositoryMock()
    {
        return $this->getMock(EntityRepository::class, [], [], '', false);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|LoggerInterface
     */
    private function createLoggerMock()
    {
        return $this->getMock(LoggerInterface::class);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|DoctrineHelper
     */
    private function createDoctrineHelperStub($entityManager = null, $entityRepository = null)
    {
        $doctrineHelper =  $this->getMock(DoctrineHelper::class, [], [], '', false);
        $doctrineHelper
            ->expects($this->any())
            ->method('getEntityRepository')
            ->willReturn($entityRepository)
        ;
        $doctrineHelper
            ->expects($this->any())
            ->method('getEntityManager')
            ->willReturn($entityManager)
        ;

        return $doctrineHelper;
    }
}
