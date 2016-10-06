<?php

namespace Oro\Bundle\WorkflowBundle\Translation\KeySource;

class AttributeNameSource extends AbstractTranslationKeySource
{
    /**
     * {@inheritdoc}
     */
    public function getTemplate()
    {
        return 'oro.workflow.{{ workflow_name }}.attribute.{{ attribute_name }}.name';
    }

    /**
     * {@inheritdoc}
     */
    protected function getRequiredKeys()
    {
        return ['workflow_name', 'attribute_name'];
    }
}
