<?php

namespace Oro\Bundle\WorkflowBundle\Model\PostAction;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Oro\Bundle\WorkflowBundle\Model\PostAction\PostActionInterface;

class PostActionFactory
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var array
     */
    protected $types;

    /**
     * @param ContainerInterface $container
     * @param array $types
     */
    public function __construct(ContainerInterface $container, array $types = array())
    {
        $this->container = $container;
        $this->types = $types;
    }

    /**
     * @param string $type
     * @return bool
     */
    public function hasType($type)
    {
        return isset($this->types[$type]);
    }

    /**
     * @param string $type
     * @return PostActionInterface
     * @throws \RunTimeException
     */
    public function create($type)
    {
        if (!$type) {
            throw new \RunTimeException('The post action type must be defined');
        }

        $id = isset($this->types[$type]) ? $this->types[$type] : false;

        if (!$id) {
            throw new \RunTimeException(sprintf('No attached service to post action type named `%s`', $type));
        }

        /** @var PostActionInterface $postAction */
        $postAction = $this->container->get($id);

        if (!$postAction instanceof PostActionInterface) {
            throw new \RunTimeException(sprintf('The service `%s` must implement `PostActionInterface`', $id));
        }

        return $postAction;
    }
}