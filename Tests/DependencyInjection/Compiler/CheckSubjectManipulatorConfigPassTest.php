<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 * Date: 30.08.16
 */

namespace Gtt\Bundle\WorkflowExtensionsBundle\Tests\DependencyInjection\Compiler;

use Gtt\Bundle\WorkflowExtensionsBundle\DependencyInjection\Compiler\CheckSubjectManipulatorConfigPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class CheckSubjectManipulatorConfigPassTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testThrowingExceptionIfTargetSubjectClassesNotConfigured()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new CheckSubjectManipulatorConfigPass());

        $registryDefinition = new Definition("RegistryClass");
        $container->setDefinition('workflow.test', new Definition('class'));
        $container->setDefinition('gtt.workflow.transition_scheduler', new Definition('class'));

        $workflowName = 'test';
        $registryDefinition->addMethodCall(
            CheckSubjectManipulatorConfigPass::WORKFLOW_REGISTRY_ADD_WORKFLOW_METHOD_NAME,
            [
                new Reference(CheckSubjectManipulatorConfigPass::WORKFLOW_ID_PREFIX.$workflowName),
                '\Some\Target\Class'
            ]
        );
        $container->setDefinition(CheckSubjectManipulatorConfigPass::WORKFLOW_REGISTRY_ID, $registryDefinition);

        $container->setParameter('gtt.workflow.subject_classes_with_subject_from_domain', ['\Some\Target\AnotherClass']);
        $container->setParameter('gtt.workflow.workflows_with_scheduling', [$workflowName]);

        $container->compile();
    }
}
