<?php

namespace Oro\Bundle\WorkflowBundle\Command;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\EntityManager;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Oro\Bundle\WorkflowBundle\Model\ProcessData;
use Oro\Bundle\WorkflowBundle\Model\ProcessHandler;

class HandleProcessTriggerCommand extends ContainerAwareCommand
{
    const NAME = 'oro:process:handle-trigger';

    /**
     * @inheritdoc
     */
    public function configure()
    {
        $this->setName(self::NAME)
            ->setDescription('Handle process trigger with specified identifier and process name')
            ->addOption(
                'name',
                null,
                InputOption::VALUE_REQUIRED,
                'Name of the process'
            )
            ->addOption(
                'id',
                null,
                InputOption::VALUE_REQUIRED,
                'Identifier of the process triggers'
            );
    }

    /**
     * @inheritdoc
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $processName = $input->getOption('name');

        $triggerId = $input->getOption('id');
        if (!is_numeric($triggerId)) {
            $output->writeln('<error>No process trigger identifier defined</error>');
            return;
        }

        $processTrigger = $this->getRepository('OroWorkflowBundle:ProcessTrigger')->find($triggerId);
        if (!$processTrigger) {
            $output->writeln('<error>Process trigger not found</error>');
            return;
        }

        $processDefinition = $processTrigger->getDefinition();
        if ($processName !== $processDefinition->getName()) {
            $output->writeln(sprintf('<error>Trigger not found in process "%s"</error>', $processName));
            return;
        }

        $entityClass = $processDefinition->getRelatedEntity();

        $processData = new ProcessData();
        $processData->set('data', new $entityClass);

        /** @var EntityManager $entityManager */
        $entityManager = $this->getContainer()->get('doctrine')->getManager();
        $entityManager->beginTransaction();

        try {
            $start = microtime(true);
            $output->writeln(
                sprintf(
                    '<info>[%s] Handle process trigger #%d "%s" (%s)</info>',
                    (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
                    $triggerId,
                    $processDefinition->getLabel(),
                    $processDefinition->getName()
                )
            );

            $processHandler = $this->getProcessHandler();
            $processHandler->handleTrigger($processTrigger, $processData);

            $entityManager->commit();

            $output->writeln(
                sprintf(
                    '<info>[%s] Process trigger #%d handle %s successfully finished in %f s</info>',
                    (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
                    $triggerId,
                    $processDefinition->getName(),
                    microtime(true) - $start
                )
            );
        } catch (\Exception $e) {
            $entityManager->rollback();

            $output->writeln(
                sprintf(
                    '<error>[%s] Process trigger #%s handle failed: %s</error>',
                    (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
                    $triggerId,
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * @param string $className
     * @return ObjectRepository
     */
    protected function getRepository($className)
    {
        return $this->getContainer()->get('doctrine')->getManagerForClass($className)->getRepository($className);
    }

    /**
     * @return ProcessHandler
     */
    protected function getProcessHandler()
    {
        return $this->getContainer()->get('oro_workflow.process.process_handler');
    }
}
