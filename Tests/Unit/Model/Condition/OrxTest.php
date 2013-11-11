<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Model\Condition;

use Doctrine\Common\Collections\ArrayCollection;

use Oro\Bundle\WorkflowBundle\Model\Condition;

class OrxTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Condition\Orx
     */
    protected $condition;

    protected function setUp()
    {
        $this->condition = new Condition\Orx();
    }

    public function testIsAllowedTrue()
    {
        $this->condition->initialize(
            array(
                new Condition\True(),
                new Condition\False(),
            )
        );
        $this->assertTrue($this->condition->isAllowed('anything'));
    }

    public function testIsAllowedFalse()
    {
        $currentConditionError = 'Current condition error';
        $nestedConditionError = 'Nested condition error';

        $this->condition->setMessage($currentConditionError);

        $falseConditionWithError = new Condition\False();
        $falseConditionWithError->setMessage($nestedConditionError);

        $this->condition->initialize(
            array(
                new Condition\False(),
                $falseConditionWithError
            )
        );

        $errors = new ArrayCollection();
        $this->assertFalse($this->condition->isAllowed('anything', $errors));
        $this->assertEquals(
            array($nestedConditionError, $currentConditionError),
            $errors->getValues()
        );
    }

    public function testIsAllowedEmpty()
    {
        $currentConditionError = 'Current condition error';
        $this->condition->setMessage($currentConditionError);

        $errors = new ArrayCollection();
        $this->assertFalse($this->condition->isAllowed('anything', $errors));
        $this->assertEquals(array($currentConditionError), $errors->getValues());
    }
}
