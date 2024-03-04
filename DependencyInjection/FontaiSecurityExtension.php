<?php
namespace Fontai\Bundle\SecurityBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;


class FontaiSecurityExtension extends Extension implements PrependExtensionInterface
{
  public function load(array $configs, ContainerBuilder $container)
  {
    $configuration = new Configuration();
    $config = $this->processConfiguration($configuration, $configs);

    $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
    $loader->load('fontai_security.yaml');

    $container->setParameter('fontai_security.sections', $config['sections']);
  }
  
  public function prepend(ContainerBuilder $container)
  {
    $configs = $container->getExtensionConfig($this->getAlias());
    $config = $this->processConfiguration(new Configuration(), $configs);

    $securityConfig = [
      'providers' => []
    ];

    if (count($config['sections']))
    {
      $securityConfig['password_hashers'] = [];
    }

    foreach ($config['sections'] as $sectionName => $params)
    {
      $params['entity_user_property'] = isset($params['entity_user_property']) ? implode(',', $params['entity_user_property']) : 'email';

      $securityConfig['providers'][$sectionName] = [
        'propel_extended' => [
          'class' => $params['entity_user'],
          'property' => $params['entity_user_property']
        ]
      ];

      $securityConfig['password_hashers'][$params['entity_user']] = [
        'algorithm' => 'bcrypt'
      ];
    }
    
    $container->prependExtensionConfig('security', $securityConfig);
  }

  public function getAlias()
  {
    return 'fontai_security';
  }
}