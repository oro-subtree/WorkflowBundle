<?php

namespace Oro\Bundle\WorkflowBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

use Oro\Bundle\EntityBundle\EntityProperty\DatesAwareTrait;
use Oro\Bundle\EntityConfigBundle\Metadata\Annotation\Config;
use Oro\Bundle\EntityConfigBundle\Metadata\Annotation\ConfigField;

/**
 * @ORM\Table("oro_workflow_trans_trigger")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="type", type="string")
 * @ORM\DiscriminatorMap({
 *     "cron" = "Oro\Bundle\WorkflowBundle\Entity\TransitionTriggerCron",
 *     "event" = "Oro\Bundle\WorkflowBundle\Entity\TransitionTriggerEvent"
 * })
 * @Config(
 *      defaultValues={
 *          "note"={
 *              "immutable"=true
 *          },
 *          "comment"={
 *              "immutable"=true
 *          },
 *          "activity"={
 *              "immutable"=true
 *          },
 *          "attachment"={
 *              "immutable"=true
 *          }
 *      }
 * )
 * @todo: setup needed indexes in BAP-11776
 */
abstract class AbstractTransitionTrigger
{
    use DatesAwareTrait;

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * Whether transition should be queued or done immediately
     *
     * @var boolean
     *
     * @ORM\Column(name="queued", type="boolean")
     */
    protected $queued = true;

    /**
     * @var string
     *
     * @ORM\Column(name="transition_name", type="string", length=255)
     */
    protected $transitionName;

    /**
     * @var WorkflowDefinition
     *
     * @ORM\ManyToOne(targetEntity="WorkflowDefinition")
     * @ORM\JoinColumn(name="workflow_name", referencedColumnName="name", onDelete="CASCADE")
     */
    protected $workflowDefinition;

    /**
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getTransitionName()
    {
        return $this->transitionName;
    }

    /**
     * @param string $transitionName
     * @return $this
     */
    public function setTransitionName($transitionName)
    {
        $this->transitionName = $transitionName;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isQueued()
    {
        return $this->queued;
    }

    /**
     * @param boolean $queued
     * @return $this
     */
    public function setQueued($queued)
    {
        $this->queued = $queued;

        return $this;
    }

    /**
     * @param WorkflowDefinition $definition
     * @return $this
     */
    public function setWorkflowDefinition(WorkflowDefinition $definition)
    {
        $this->workflowDefinition = $definition;

        return $this;
    }

    /**
     * @return WorkflowDefinition
     */
    public function getWorkflowDefinition()
    {
        return $this->workflowDefinition;
    }

    /**
     * @param AbstractTransitionTrigger $trigger
     */
    protected function importMainData(AbstractTransitionTrigger $trigger)
    {
        $this->setQueued($trigger->isQueued())
            ->setTransitionName($trigger->getTransitionName())
            ->setWorkflowDefinition($trigger->getWorkflowDefinition());
    }

    public function isEqualTo(AbstractTransitionTrigger $trigger)
    {
        $class = get_class($trigger);
        foreach ($this->getEqualityProperties() as $property) {
            if (!property_exists($class, $property)) {
                return false;
            }
            if ($this->{$property} !== $trigger->{$property}) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array A list of fields that should be identical to be sure triggers are equal
     */
    abstract protected function getEqualityProperties();
}
