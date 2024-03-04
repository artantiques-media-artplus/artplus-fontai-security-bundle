<?php
namespace Fontai\Bundle\SecurityBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;


class UserCheckersPass implements CompilerPassInterface
{
  public function process(ContainerBuilder $container)
  {
    foreach ($container->getParameter('fontai_security.sections') as $sectionName => $params)
    {
      $definition = new Definition('Fontai\\Bundle\\SecurityBundle\\Security\\UserChecker');
      $definition
      ->setPublic(FALSE)
      ->setArguments([
        new Reference('Symfony\Component\HttpFoundation\RequestStack'),
        $params['entity_user'],
        $params['entity_login_attempt']
      ]);

      $container->setDefinition(
        sprintf('fontai_security.user_checker.%s', $sectionName),
        $definition
      );
    }
  }
}
