<?php
namespace Fontai\Bundle\SecurityBundle\DependencyInjection\Security\Factory;


class InitHashLocalizedLoginFactory extends InitHashLoginFactory
{
  public function __construct()
  {
    parent::__construct();
    
    $this->addOption('route_locale', []);
  }

  public function getKey(): string
  {
    return 'init-hash-localized-login';
  }

  protected function getListenerId(): string
  {
    return 'fontai_security.authentication.listener.init_hash_localized_login';
  }
}
