<?php

namespace Oro\Bundle\WorkflowBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\OptionsResolver\Options;

use Oro\Bundle\WorkflowBundle\Helper\WorkflowTranslationHelper;
use Oro\Bundle\WorkflowBundle\Validator\Constraints\TransitionIsAllowed;
use Symfony\Component\VarDumper\VarDumper;

class WorkflowTransitionType extends AbstractType
{
    const NAME = 'oro_workflow_transition';

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return $this->getBlockPrefix();
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return self::NAME;
    }

    /**
     * {@inheritDoc}
     */
    public function getParent()
    {
        return WorkflowAttributesType::NAME;
    }

    /**
     * Custom options:
     * - "workflow_item" - required, instance of WorkflowItem entity
     * - "transition_name" - required, name of transition
     *
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setRequired(array('workflow_item', 'transition_name'));

        $resolver->setAllowedTypes(
            array(
                'transition_name' => 'string',
            )
        );

        $resolver->setNormalizers(
            array(
                'constraints' => function (Options $options, $constraints) {
                    if (!$constraints) {
                        $constraints = array();
                    }

                    $constraints[] = new TransitionIsAllowed(
                        $options['workflow_item'],
                        $options['transition_name']
                    );

                    return $constraints;
                }
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        foreach ($view->children as $k => $childView) {
            $childView->vars['translation_domain'] = WorkflowTranslationHelper::TRANSLATION_DOMAIN;
        }
    }
}
