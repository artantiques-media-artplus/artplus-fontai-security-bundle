<?php
namespace Fontai\Bundle\SecurityBundle\DependencyInjection\Security\Factory;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\FormLoginFactory;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;


class FormLocalizedLoginFactory extends FormLoginFactory
{
  public function __construct()
  {
    parent::__construct();
    
    $this->addOption('route_locale', []);
  }

  public function getKey(): string
  {
    return 'form-localized-login';
  }

  protected function getListenerId(): string
  {
    return 'fontai_security.authentication.listener.form_localized';
  }

  protected function createEntryPoint(
    ContainerBuilder $container,
    string $id,
    array $config,
    ?string $defaultEntryPoint
  ): ?string
  {
    $entryPointId = 'security.authentication.form_localized_entry_point.'.$id;
    
    $container
    ->setDefinition($entryPointId, new ChildDefinition('fontai_security.authentication.form_localized_entry_point'))
    ->addArgument(new Reference('security.http_utils'))
    ->addArgument($config['login_path'])
    ->addArgument($config['use_forward']);

    return $entryPointId;
  }
}
