<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Model\Pass;

use Symfony\Component\PropertyAccess\PropertyPath;

use Oro\Bundle\WorkflowBundle\Model\Pass\ParameterPass;

class ParameterPassTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param array $sourceData
     * @param array $expectedData
     *
     * @dataProvider passDataProvider
     */
    public function testPass(array $sourceData, array $expectedData)
    {
        $parameterPass = new ParameterPass();
        $actualData = $parameterPass->pass($sourceData);

        $this->assertEquals($expectedData, $this->replacePropertyPathsWithElements($actualData));
    }

    /**
     * @param array $data
     * @return array
     */
    protected function replacePropertyPathsWithElements($data)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->replacePropertyPathsWithElements($value);
            } elseif ($value instanceof PropertyPath) {
                $data[$key] = $value->getElements();
            }
        }

        return $data;
    }

    /**
     * @return array
     */
    public function passDataProvider()
    {
        return array(
            'empty data' => array(
                'sourceData' => array(),
                'expectedData' => array()
            ),
            'data with paths' => array(
                'sourceData' => array(
                    'a' => '$path.component',
                    'b' => array(
                        'c' => '$another.path.component'
                    )
                ),
                'expectedData' => array(
                    'a' => array('path', 'component'),
                    'b' => array(
                        'c' => array('another', 'path', 'component'),
                    )
                )
            ),
        );
    }
}