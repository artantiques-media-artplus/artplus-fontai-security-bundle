<?php
namespace Fontai\Bundle\SecurityBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;


class Configuration implements ConfigurationInterface
{
  public function getConfigTreeBuilder()
  {
    $treeBuilder = new TreeBuilder('fontai_security');

    $treeBuilder
    ->getRootNode()
      ->children()
        ->arrayNode('sections')
          ->isRequired()
          ->beforeNormalization()
            ->always()
            ->then(function ($sections)
            {
              foreach ($sections as $name => &$section)
              {
                if (!isset($section['login_template']))
                {
                  $section['login_template'] = sprintf('%s/security/login.html.twig', $name);
                }

                if (!isset($section['recovery_template']))
                {
                  $section['recovery_template'] = sprintf('%s/security/recovery.html.twig', $name);
                }

                if (!isset($section['entity_user_property']))
                {
                  $section['entity_user_property'] = ['email'];
                }
              }

              return $sections;
            })
          ->end()
          ->prototype('array')
            ->children()
              ->scalarNode('entity_user')->isRequired()->cannotBeEmpty()->end()
              ->arrayNode('entity_user_property')
                ->scalarPrototype()->end()
              ->end()
              ->scalarNode('entity_login_attempt')->isRequired()->cannotBeEmpty()->end()
              ->scalarNode('role')->isRequired()->cannotBeEmpty()->end()
              ->arrayNode('subdomain')
                ->scalarPrototype()->end()
              ->end()
              ->scalarNode('route_prefix')->defaultValue(NULL)->end()
              ->scalarNode('login_template')->isRequired()->cannotBeEmpty()->end()
              ->scalarNode('login_target')->isRequired()->cannotBeEmpty()->end()
              ->scalarNode('logout_target')->end()
              ->scalarNode('init_target')->isRequired()->cannotBeEmpty()->end()
              ->scalarNode('init_email_template')->isRequired()->cannotBeEmpty()->end()
              ->scalarNode('recovery_template')->isRequired()->cannotBeEmpty()->end()
              ->scalarNode('recovery_email_template')->isRequired()->cannotBeEmpty()->end()
              ->scalarNode('requires_channel')->end()
              ->scalarNode('success_handler')->end()
              ->scalarNode('failure_handler')->end()
              ->booleanNode('localized_routing')->defaultFalse()->end()
            ->end()
          ->end()
        ->end()
      ->end()
    ->end();

    return $treeBuilder;
  }
}