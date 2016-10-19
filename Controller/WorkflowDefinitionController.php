<?php

namespace Oro\Bundle\WorkflowBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use Oro\Bundle\SecurityBundle\Annotation\Acl;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Oro\Bundle\WorkflowBundle\Configuration\WorkflowConfiguration;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowDefinition;
use Oro\Bundle\WorkflowBundle\Form\Type\WorkflowReplacementSelectType;
use Oro\Bundle\WorkflowBundle\Helper\WorkflowTranslationHelper;
use Oro\Bundle\WorkflowBundle\Model\Workflow;

/**
 * @Route("/workflowdefinition")
 */
class WorkflowDefinitionController extends Controller
{
    /**
     * @Route(name="oro_workflow_definition_index")
     * @Template
     * @Acl(
     *      id="oro_workflow_definition_view",
     *      type="entity",
     *      class="OroWorkflowBundle:WorkflowDefinition",
     *      permission="VIEW"
     * )
     *
     * @return array
     */
    public function indexAction()
    {
        return array(
            'entity_class' => $this->container->getParameter('oro_workflow.workflow_definition.entity.class')
        );
    }

    /**
     * @Route(
     *      "/create",
     *      name="oro_workflow_definition_create"
     * )
     * @Template("OroWorkflowBundle:WorkflowDefinition:update.html.twig")
     * @Acl(
     *      id="oro_workflow_definition_create",
     *      type="entity",
     *      class="OroWorkflowBundle:WorkflowDefinition",
     *      permission="CREATE"
     * )
     *
     * @return array
     */
    public function createAction()
    {
        return $this->updateAction(new WorkflowDefinition());
    }

    /**
     * @Route(
     *      "/update/{name}",
     *      name="oro_workflow_definition_update"
     * )
     * @Template("OroWorkflowBundle:WorkflowDefinition:update.html.twig")
     * @Acl(
     *      id="oro_workflow_definition_update",
     *      type="entity",
     *      class="OroWorkflowBundle:WorkflowDefinition",
     *      permission="EDIT"
     * )
     *
     * @param WorkflowDefinition $workflowDefinition
     * @return array
     * @throws AccessDeniedHttpException
     */
    public function updateAction(WorkflowDefinition $workflowDefinition)
    {
        if ($workflowDefinition->isSystem()) {
            throw new AccessDeniedHttpException('System workflow definitions are not editable');
        }
        $translateLinks = $this->getTranslateLinks($workflowDefinition);
        $this->getTranslationHelper()->extractTranslations($workflowDefinition);

        $form = $this->get('oro_workflow.form.workflow_definition');
        $form->setData($workflowDefinition);

        return array(
            'form' => $form->createView(),
            'entity' => $workflowDefinition,
            'system_entities' => $this->get('oro_entity.entity_provider')->getEntities(),
            'delete_allowed' => true,
            'translateLinks' => $translateLinks,
        );
    }

    /**
     * @Route(
     *      "/view/{name}",
     *      name="oro_workflow_definition_view"
     * )
     * @AclAncestor("oro_workflow_definition_view")
     * @Template("OroWorkflowBundle:WorkflowDefinition:view.html.twig")
     *
     * @param WorkflowDefinition $workflowDefinition
     * @return array
     */
    public function viewAction(WorkflowDefinition $workflowDefinition)
    {
        $translateLinks = $this->getTranslateLinks($workflowDefinition);
        $this->getTranslationHelper()->extractTranslations($workflowDefinition);

        return array(
            'entity' => $workflowDefinition,
            'system_entities' => $this->get('oro_entity.entity_provider')->getEntities(),
            'translateLinks' => $translateLinks,
        );
    }

    /**
     * @Route(
     *      "/info/{name}",
     *      name="oro_workflow_definition_info"
     * )
     * @AclAncestor("oro_workflow_definition_view")
     * @Template
     *
     * @param WorkflowDefinition $workflowDefinition
     * @return array
     */
    public function infoAction(WorkflowDefinition $workflowDefinition)
    {
        return array(
            'entity' => $workflowDefinition
        );
    }

    /**
     * Activate WorkflowDefinition form
     *
     * @Route("/activate-form/{name}", name="oro_workflow_definition_activate_from_widget")
     * @AclAncestor("oro_workflow_definition_update")
     * @Template("OroWorkflowBundle:WorkflowDefinition:widget/activateForm.html.twig")
     *
     * @param WorkflowDefinition $workflowDefinition
     * @return array
     */
    public function activateFormAction(WorkflowDefinition $workflowDefinition)
    {
        $form = $this->createForm(
            WorkflowReplacementSelectType::NAME,
            null,
            ['workflow' => $workflowDefinition->getName()]
        );

        $workflowsToDeactivation = $this->getWorkflowsToDeactivation($workflowDefinition);

        $response = $this->get('oro_form.model.update_handler')->update($workflowDefinition, $form, null);
        $response['workflow'] = $workflowDefinition->getName();
        $response['workflowsToDeactivation'] = $workflowsToDeactivation;

        if ($form->isValid()) {
            $workflowManager = $this->get('oro_workflow.manager');
            $workflowNames = array_merge(
                $form->getData(),
                array_map(
                    function (Workflow $workflow) {
                        return $workflow->getName();
                    },
                    $workflowsToDeactivation
                )
            );

            $deactivated = [];
            foreach ($workflowNames as $workflowName) {
                if ($workflowName && $workflowManager->isActiveWorkflow($workflowName)) {
                    $workflow = $workflowManager->getWorkflow($workflowName);

                    $workflowManager->resetWorkflowData($workflow->getName());
                    $workflowManager->deactivateWorkflow($workflow->getName());

                    $deactivated[] = $workflow->getLabel();
                }
            }

            $response['deactivated'] = $deactivated;

            $workflowManager->activateWorkflow($workflowDefinition->getName());
        }

        return $response;
    }

    /**
     * @param WorkflowDefinition $workflowDefinition
     * @return array|Workflow[]
     */
    protected function getWorkflowsToDeactivation(WorkflowDefinition $workflowDefinition)
    {
        $workflows = $this->get('oro_workflow.registry')
            ->getActiveWorkflowsByActiveGroups($workflowDefinition->getExclusiveActiveGroups());

        return array_filter(
            $workflows,
            function (Workflow $workflow) use ($workflowDefinition) {
                return $workflow->getName() !== $workflowDefinition->getName();
            }
        );
    }

    /**
     * @param WorkflowDefinition $workflowDefinition
     * @return array
     */
    protected function getTranslateLinks(WorkflowDefinition $workflowDefinition)
    {
        // show translate links only if any language is available for current user
        if (0 === count($this->get('oro_translation.provider.language')->getAvailableLanguages())) {
            return [];
        }

        return $this->get('oro_workflow.translation.helper')->getWorkflowTranslateLinks($workflowDefinition);
    }

    /**
     * @return WorkflowTranslationHelper
     */
    protected function getTranslationHelper()
    {
        return $this->get('oro_workflow.helper.translation');
    }
}
