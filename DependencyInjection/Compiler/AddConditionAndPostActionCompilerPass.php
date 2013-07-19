<?php

namespace Oro\Bundle\WorkflowBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

class AddConditionAndPostActionCompilerPass implements CompilerPassInterface
{
    const CONDITION_TAG = 'oro_workflow.condition';
    const CONDITION_FACTORY_KEY = 'oro_workflow.condition.factory';
    const ACTION_TAG = 'oro_workflow.action';
    const ACTION_FACTORY_KEY = 'oro_workflow.action.factory';

    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $this->injectEntityTypesByTag($container, self::CONDITION_FACTORY_KEY, self::CONDITION_TAG);
        $this->injectEntityTypesByTag($container, self::ACTION_FACTORY_KEY, self::ACTION_TAG);
    }

    /**
     * @param ContainerBuilder $container
     * @param string $serviceId
     * @param string $tagName
     */
    protected function injectEntityTypesByTag(ContainerBuilder $container, $serviceId, $tagName)
    {
        $definition = $container->getDefinition($serviceId);
        $types      = array();

        foreach ($container->findTaggedServiceIds($tagName) as $id => $attributes) {
            $container->getDefinition($id)->setScope(ContainerInterface::SCOPE_PROTOTYPE);

            foreach ($attributes as $eachTag) {
                $index = !empty($eachTag['alias']) ? $eachTag['alias'] : $id;
                $types[$index] = $id;
            }
        }

        $definition->replaceArgument(1, $types);
    }
}