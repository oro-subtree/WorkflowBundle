<?php

namespace Oro\Bundle\WorkflowBundle\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\NodeInterface;

class ConfigurationTree
{
    const NODE_STEPS = 'steps';
    const NODE_ATTRIBUTES = 'attributes';
    const NODE_TRANSITIONS = 'transitions';
    const NODE_TRANSITION_DEFINITIONS = 'transition_definitions';

    /**
     * @var NodeDefinition[]
     */
    protected $nodeDefinitions;

    /**
     * @param array $configuration
     * @return array
     */
    public function parseConfiguration(array $configuration)
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('configuration');
        $nodeBuilder = $rootNode->children();
        foreach ($this->getNodeDefinitions() as $nodeDefinition) {
            $nodeBuilder->append($nodeDefinition);
        }

        $rootTree = $treeBuilder->buildTree();

        return $rootTree->finalize($configuration);
    }

    /**
     * @return NodeDefinition[]
     */
    public function getNodeDefinitions()
    {
        if (null === $this->nodeDefinitions) {
            $this->nodeDefinitions = array(
                self::NODE_STEPS                  => $this->getStepsNode(),
                self::NODE_ATTRIBUTES             => $this->getAttributesNode(),
                self::NODE_TRANSITIONS            => $this->getTransitionsNode(),
                self::NODE_TRANSITION_DEFINITIONS => $this->getTransitionDefinitionsNode()
            );
        }

        return $this->nodeDefinitions;
    }

    /**
     * @return NodeDefinition
     */
    protected function getStepsNode()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root(self::NODE_STEPS);
        $rootNode
            ->isRequired()
            ->cannotBeEmpty()
            ->prototype('array')
                ->children()
                    ->scalarNode('label')
                        ->isRequired()
                        ->cannotBeEmpty()
                    ->end()
                    ->scalarNode('template')
                        ->defaultNull()
                    ->end()
                    ->integerNode('order')
                        ->defaultValue(0)
                    ->end()
                     ->booleanNode('is_final')
                        ->defaultFalse()
                    ->end()
                    ->arrayNode('attributes')
                        ->prototype('scalar')
                        ->end()
                    ->end()
                    ->arrayNode('allowed_transitions')
                        ->prototype('scalar')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $rootNode;
    }

    /**
     * @return NodeDefinition
     */
    protected function getAttributesNode()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root(self::NODE_ATTRIBUTES);
        $rootNode
            ->prototype('array')
                ->children()
                    ->scalarNode('label')
                        ->isRequired()
                        ->cannotBeEmpty()
                    ->end()
                    ->scalarNode('form_type')
                        ->isRequired()
                        ->cannotBeEmpty()
                    ->end()
                    ->arrayNode('options')
                    ->end()
                ->end()
            ->end();

        return $rootNode;
    }

    /**
     * @return NodeDefinition
     */
    protected function getTransitionsNode()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root(self::NODE_TRANSITIONS);
        $rootNode
            ->isRequired()
            ->cannotBeEmpty()
            ->prototype('array')
                ->children()
                    ->scalarNode('label')
                        ->isRequired()
                        ->cannotBeEmpty()
                    ->end()
                    ->scalarNode('step_to')
                        ->isRequired()
                        ->cannotBeEmpty()
                    ->end()
                    ->scalarNode('transition_definition')
                        ->isRequired()
                        ->cannotBeEmpty()
                    ->end()
                ->end()
            ->end();

        return $rootNode;
    }

    /**
     * @return NodeDefinition
     */
    protected function getTransitionDefinitionsNode()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root(self::NODE_TRANSITION_DEFINITIONS);
        $rootNode
            ->isRequired()
            ->cannotBeEmpty()
            ->prototype('array')
                ->children()
                    ->arrayNode('conditions')
                    ->end()
                    ->arrayNode('post_actions')
                    ->end()
                ->end()
            ->end();

        return $rootNode;
    }
}
