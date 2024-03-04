<?php
namespace Fontai\Bundle\SecurityBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;


class ControllersPass implements CompilerPassInterface
{
  public function process(ContainerBuilder $container)
  {
    foreach ($container->getParameter('fontai_security.sections') as $sectionName => $params)
    {
      $definition = new Definition('Fontai\\Bundle\\SecurityBundle\\Controller\\SecurityController');
      $definition
      ->setPublic(TRUE)
      ->setArguments([
        $sectionName,
        $params['entity_user'],
        $params['localized_routing'] ?? FALSE
      ])
      ->addTag('controller.service_arguments')
      ->addMethodCall('setContainer', [new Reference('service_container')]);;

      $container->setDefinition(
        sprintf('fontai_security.controller.%s', $sectionName),
        $definition
      );
    }
  }
}
