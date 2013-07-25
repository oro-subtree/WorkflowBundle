<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Model\PostAction;

use Oro\Bundle\WorkflowBundle\Model\PostAction\AssignValue;
use Oro\Bundle\WorkflowBundle\Model\PostAction\PostActionInterface;

class AssignValueTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PostActionInterface
     */
    protected $postAction;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $contextAccessor;

    protected function setUp()
    {
        $this->contextAccessor = $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Model\ContextAccessor')
            ->disableOriginalConstructor()
            ->getMock();
        $this->postAction = new AssignValue($this->contextAccessor);
    }


    /**
     * @expectedException Oro\Bundle\WorkflowBundle\Exception\InvalidParameterException
     * @expectedExceptionMessage Attribute and value parameters are required.
     * @dataProvider invalidOptionsNumberDataProvider
     * @param array $options
     */
    public function testInitializeExceptionParametersCount($options)
    {
        $this->postAction->initialize($options);
    }

    public function invalidOptionsNumberDataProvider()
    {
        return array(
            array(array()),
            array(array(1)),
            array(array(1, 2, 3)),
            array(array('target' => 1)),
            array(array('value' => 1)),
        );
    }

    /**
     * @expectedException Oro\Bundle\WorkflowBundle\Exception\InvalidParameterException
     * @expectedExceptionMessage Attribute must be valid property definition.
     * @dataProvider invalidOptionsAttributeDataProvider
     * @param array $options
     */
    public function testInitializeExceptionInvalidAttribute($options)
    {
        $this->postAction->initialize($options);
    }

    public function invalidOptionsAttributeDataProvider()
    {
        return array(
            array(array('test', 'value')),
            array(array('attribute' => 'test', 'value' => 'value'))
        );
    }

    /**
     * @expectedException Oro\Bundle\WorkflowBundle\Exception\InvalidParameterException
     * @expectedExceptionMessage Attribute must be defined.
     */
    public function testInitializeExceptionNoAttribute()
    {
        $this->postAction->initialize(array('some' => 'test', 'value' => 'test'));
    }

    /**
     * @expectedException Oro\Bundle\WorkflowBundle\Exception\InvalidParameterException
     * @expectedExceptionMessage Value must be defined.
     */
    public function testInitializeExceptionNoValue()
    {
        $this->postAction->initialize(array('attribute' => 'test', 'unknown' => 'test'));
    }

    /**
     * @dataProvider optionsDataProvider
     * @param array $options
     */
    public function testInitialize($options)
    {
        $this->assertInstanceOf(
            'Oro\Bundle\WorkflowBundle\Model\PostAction\PostActionInterface',
            $this->postAction->initialize($options)
        );
        $this->assertAttributeEquals($options, 'options', $this->postAction);
    }

    public function optionsDataProvider()
    {
        return array(
            array(array($this->getPropertyPath(), 'value')),
            array(array('attribute' => $this->getPropertyPath(), 'value' => 'value')),
        );
    }

    /**
     * @dataProvider optionsDataProvider
     * @param array $options
     */
    public function testExecute($options)
    {
        $context = array();
        $optionsData = array_values($options);
        $attribute = $optionsData[0];
        $value = $optionsData[1];
        $this->contextAccessor->expects($this->once())
            ->method('setValue')
            ->with($context, $attribute, $value);
        $this->postAction->initialize($options);
        $this->postAction->execute($context);
    }

    protected function getPropertyPath()
    {
        return $this->getMockBuilder('Symfony\Component\PropertyAccess\PropertyPath')
            ->disableOriginalConstructor()
            ->getMock();
    }
}