<?php
namespace Fontai\Bundle\SecurityBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;


class EventListenersPass implements CompilerPassInterface
{
  public function process(ContainerBuilder $container)
  {
    foreach ($container->getParameter('fontai_security.sections') as $sectionName => $params)
    {
      $definition = new Definition('Fontai\\Bundle\\SecurityBundle\\EventListener\\PropelListener');
      $definition
      ->setPublic(FALSE)
      ->setArguments([
        new Reference('Swift_Mailer'),
        new Reference('Symfony\Component\Routing\RouterInterface'),
        $sectionName,
        $params['entity_user'],
        $params['init_email_template'],
        $params['recovery_email_template'],
        $params['localized_routing'] ?? FALSE
      ]);

      if (class_exists($params['entity_user']))
      {
        $definition
        ->addTag('kernel.event_listener', ['event' => constant(sprintf('%s::EVENT_PRE_SAVE', $params['entity_user'])), 'method' => 'onUserPreSave'])
        ->addTag('kernel.event_listener', ['event' => constant(sprintf('%s::EVENT_POST_SAVE', $params['entity_user'])), 'method' => 'onUserPostSave']);
      }
      
      $container->setDefinition(
        sprintf('fontai_security.event_listener.propel.%s', $sectionName),
        $definition
      );

      $definition = new Definition('Fontai\\Bundle\\SecurityBundle\\EventListener\\SecurityListener');
      $definition
      ->setPublic(FALSE)
      ->setArguments([
        new Reference('Symfony\Component\HttpFoundation\RequestStack'),
        new Reference('Symfony\Component\Routing\RouterInterface'),
        new Reference('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface'),
        $sectionName,
        $params['entity_user'],
        $params['entity_login_attempt'],
        $params['init_target'],
        $params['localized_routing'] ?? FALSE
      ])
      ->addTag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'onKernelRequest'])
      ->addTag('kernel.event_listener', ['event' => 'security.interactive_login', 'method' => 'onInteractiveLogin'])
      ->addTag('kernel.event_listener', ['event' => 'security.authentication.success', 'method' => 'onAuthenticationSuccess'])
      ->addTag('kernel.event_listener', ['event' => 'security.authentication.failure', 'method' => 'onAuthenticationFailure']);

      $container->setDefinition(
        sprintf('fontai_security.event_listener.security.%s', $sectionName),
        $definition
      );
    }
  }
}