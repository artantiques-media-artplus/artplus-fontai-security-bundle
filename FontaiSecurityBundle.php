<?php
namespace Fontai\Bundle\SecurityBundle;

use Fontai\Bundle\SecurityBundle\DependencyInjection\Compiler\ControllersPass;
use Fontai\Bundle\SecurityBundle\DependencyInjection\Compiler\EventListenersPass;
use Fontai\Bundle\SecurityBundle\DependencyInjection\Compiler\UserCheckersPass;
use Fontai\Bundle\SecurityBundle\DependencyInjection\Security\Factory\FormLocalizedLoginFactory;
use Fontai\Bundle\SecurityBundle\DependencyInjection\Security\Factory\InitHashLocalizedLoginFactory;
use Fontai\Bundle\SecurityBundle\DependencyInjection\Security\Factory\InitHashLoginFactory;
use Propel\Bundle\PropelBundle\DependencyInjection\Security\UserProvider\PropelFactory;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;


class FontaiSecurityBundle extends Bundle
{
  public function build(ContainerBuilder $container)
  {
    parent::build($container);

    $container
    ->addCompilerPass(new ControllersPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 32)
    ->addCompilerPass(new EventListenersPass())
    ->addCompilerPass(new UserCheckersPass());

    $securityExtension = $container->getExtension('security');
    $securityExtension->addSecurityListenerFactory(new FormLocalizedLoginFactory());
    $securityExtension->addSecurityListenerFactory(new InitHashLoginFactory());
    $securityExtension->addSecurityListenerFactory(new InitHashLocalizedLoginFactory());
    $securityExtension->addUserProviderFactory(new PropelFactory('propel_extended', 'fontai_security.security.user.provider'));
  }
}