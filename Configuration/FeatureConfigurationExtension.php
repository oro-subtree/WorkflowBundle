<?php

namespace Oro\Bundle\WorkflowBundle\Configuration;

use Oro\Bundle\FeatureToggleBundle\Configuration\ConfigurationExtensionInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

class FeatureConfigurationExtension implements ConfigurationExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function extendConfigurationTree(NodeBuilder $node)
    {
        $node
            ->arrayNode('workflow')
                ->prototype('variable')
                ->end()
            ->end()
            ->arrayNode('operation')
                ->prototype('variable')
                ->end()
            ->end()
            ->arrayNode('process')
                ->prototype('variable')
                ->end()
            ->end();
    }
}
