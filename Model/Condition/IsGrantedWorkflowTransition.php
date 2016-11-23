<?php

namespace Oro\Bundle\WorkflowBundle\Model\Condition;

use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Security\Acl\Voter\FieldVote;

use Oro\Component\ConfigExpression\Condition\AbstractCondition;
use Oro\Component\ConfigExpression\ContextAccessorAwareInterface;
use Oro\Component\ConfigExpression\ContextAccessorAwareTrait;
use Oro\Component\ConfigExpression\Exception\InvalidArgumentException;

use Oro\Bundle\SecurityBundle\Acl\Domain\DomainObjectWrapper;
use Oro\Bundle\SecurityBundle\SecurityFacade;

/**
 * Used to perform ACL check for ability to perform transition
 *  - all transitions check (PERFORM_TRANSITIONS)
 *  - specific transition check (PERFORM_TRANSITION)
 */
class IsGrantedWorkflowTransition extends AbstractCondition implements ContextAccessorAwareInterface
{
    use ContextAccessorAwareTrait;

    const NAME = 'is_granted_workflow_transition';

    /** @var SecurityFacade */
    protected $securityFacade;

    /** @var string */
    protected $transitionName;

    /**
     * @param SecurityFacade  $securityFacade
     */
    public function __construct(SecurityFacade $securityFacade)
    {
        $this->securityFacade = $securityFacade;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return self::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(array $options)
    {
        if (1 === count($options)) {
            $this->transitionName = reset($options);
            if (!$this->transitionName) {
                throw new InvalidArgumentException('ACL object must not be empty.');
            }
        } else {
            throw new InvalidArgumentException(
                sprintf('Options must have 1 elements, but %d given.', count($options))
            );
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function isConditionAllowed($context)
    {
        if (!$this->securityFacade->hasLoggedUser()) {
            return true;
        }

        $entity = $context->getEntity();
        $workflowName = $context->getWorkflowName();

        if (!$this->securityFacade->isGranted(
            'PERFORM_TRANSITIONS',
            new DomainObjectWrapper($entity, new ObjectIdentity('workflow', $workflowName))
        )) {
            //performing of transitions is forbidden on workflow level
            return false;
        }

        if (!$this->securityFacade->isGranted(
            'PERFORM_TRANSITION',
            new FieldVote(
                new DomainObjectWrapper($entity, new ObjectIdentity('workflow', $workflowName)),
                $this->transitionName
            )
        )) {
            //performing of given transition is forbidden
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        return $this->convertToArray([$this->transitionName]);
    }

    /**
     * {@inheritdoc}
     */
    public function compile($factoryAccessor)
    {
        return $this->convertToPhpCode([$this->transitionName], $factoryAccessor);
    }
}
