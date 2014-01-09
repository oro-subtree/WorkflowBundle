<?php

namespace Oro\Bundle\WorkflowBundle\Configuration;

use Oro\Bundle\WorkflowBundle\Entity\ProcessTrigger;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class ProcessTriggerConfiguration implements ConfigurationInterface
{
    /**
     * @param array $configs
     * @return array
     */
    public function processConfiguration(array $configs)
    {
        $processor = new Processor();
        return $processor->processConfiguration($this, array($configs));
    }

    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('configuration');
        $rootNode
            ->children()
                ->scalarNode('event')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('field')
                    ->defaultNull()
                ->end()
                ->scalarNode('time_shift')
                    ->defaultNull()
                    ->validate()
                        ->always(
                            function ($value) {
                                // if value is an integer value
                                $integerValue = filter_var($value, FILTER_VALIDATE_INT);
                                if (false !== $integerValue) {
                                    return $integerValue;
                                }

                                // if value is DateInterval spec
                                try {
                                    return ProcessTrigger::convertDateIntervalToSeconds(new \DateInterval($value));
                                } catch (\Exception $e) {
                                    throw new \LogicException(
                                        sprintf('Time shift "%s" is not compatible with DateInterval', $value)
                                    );
                                }
                            }
                        )
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->always(
                    function ($data) {
                        if ($data['field'] && $data['event'] != ProcessTrigger::EVENT_UPDATE) {
                            throw new \LogicException('Field is only allowed for update event');
                        }

                        return $data;
                    }
                )
            ->end();

        return $treeBuilder;
    }
}