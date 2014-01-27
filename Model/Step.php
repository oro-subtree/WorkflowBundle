<?php

namespace Oro\Bundle\WorkflowBundle\Model;

use Oro\Bundle\WorkflowBundle\Entity\WorkflowStep;

class Step
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var int
     */
    protected $order;

    /**
     * @var boolean
     */
    protected $isFinal = false;

    /**
     * @var string[]
     */
    protected $allowedTransitions = array();

    /**
     * Set allowed transitions.
     *
     * @param array $allowedTransitions
     * @return Step
     */
    public function setAllowedTransitions($allowedTransitions)
    {
        $this->allowedTransitions = $allowedTransitions;
        return $this;
    }

    /**
     * Get allowed transitions.
     *
     * @return array
     */
    public function getAllowedTransitions()
    {
        return $this->allowedTransitions;
    }

    /**
     * Check transition is allowed for current step.
     *
     * @param string $transitionName
     * @return bool
     */
    public function isAllowedTransition($transitionName)
    {
        return in_array($transitionName, $this->allowedTransitions);
    }

    /**
     * Allow transition.
     *
     * @param string $transitionName
     */
    public function allowTransition($transitionName)
    {
        if (!$this->isAllowedTransition($transitionName)) {
            $this->allowedTransitions[] = $transitionName;
        }
    }

    /**
     * Check if current step has allowed transitions.
     *
     * @return boolean
     */
    public function hasAllowedTransitions()
    {
        return count($this->allowedTransitions) > 0;
    }

    /**
     * Disallow transition.
     *
     * @param string $transitionName
     */
    public function disallowTransition($transitionName)
    {
        if ($this->isAllowedTransition($transitionName)) {
            array_splice($this->allowedTransitions, array_search($transitionName, $this->allowedTransitions), 1);
        }
    }

    /**
     * Set step is final.
     *
     * @param boolean $isFinal
     * @return Step
     */
    public function setIsFinal($isFinal)
    {
        $this->isFinal = $isFinal;
        return $this;
    }

    /**
     * Check step is final.
     *
     * @return boolean
     */
    public function isFinal()
    {
        return $this->isFinal;
    }

    /**
     * Set name.
     *
     * @param string $name
     * @return Step
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set order.
     *
     * @param int $order
     * @return Step
     */
    public function setOrder($order)
    {
        $this->order = $order;
        return $this;
    }

    /**
     * Get order.
     *
     * @return int
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * Set label.
     *
     * @param string $label
     * @return Step
     */
    public function setLabel($label)
    {
        $this->label = $label;
        return $this;
    }

    /**
     * Get label.
     *
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }
}
