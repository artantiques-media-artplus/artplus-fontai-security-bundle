<?php
namespace Fontai\Bundle\SecurityBundle\DependencyInjection\Security\Factory;

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AbstractFactory;


class InitHashLoginFactory extends AbstractFactory
{
  public function __construct()
  {
    $this->addOption('query');
  }

  public function getPosition()
  {
    return 'form';
  }

  public function getKey(): string
  {
    return 'init-hash-login';
  }

  protected function createAuthProvider(ContainerBuilder $container, $id, $config, $userProviderId)
  {
    $provider = sprintf('security.authentication.provider.pre_authenticated.%s', $id);
    $container
    ->setDefinition($provider, new ChildDefinition('security.authentication.provider.pre_authenticated'))
    ->replaceArgument(0, new Reference($userProviderId))
    ->replaceArgument(1, new Reference(sprintf('security.user_checker.%s', $id)))
    ->addArgument($id);

    return $provider;
  }

  protected function getListenerId(): string
  {
    return 'fontai_security.authentication.listener.init_hash_login';
  }

  protected function isRememberMeAware($config)
  {
    return FALSE;
  }

  protected function createListener($container, $id, $config, $userProvider)
  {
    $listenerId = $this->getListenerId();
    
    $listener = new ChildDefinition($listenerId);
    $listener->replaceArgument(3, $id);
    $listener->replaceArgument(4, new Reference($this->createAuthenticationSuccessHandler($container, $id, $config)));
    $listener->replaceArgument(5, new Reference($this->createAuthenticationFailureHandler($container, $id, $config)));
    $listener->replaceArgument(6, array_intersect_key($config, $this->options));

    $listenerId .= '.' . $id;
    $container->setDefinition($listenerId, $listener);

    return $listenerId;
  }
}
