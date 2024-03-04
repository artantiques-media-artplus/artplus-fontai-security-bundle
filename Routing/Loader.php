<?php
namespace Fontai\Bundle\SecurityBundle\Routing;

use App\Model;
use Symfony\Component\Config\Loader\Loader as SymfonyLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;


class Loader extends SymfonyLoader
{
  protected $sections;

  public function __construct(array $sections)
  {
    $this->sections = $sections;
  }

  public function load($resource, $type = NULL)
  {
    $routes = new RouteCollection();
    
    foreach ($this->sections as $sectionName => $params)
    {
      if (!($params['localized_routing'] ?? FALSE))
      {
        $routes->add(
          sprintf('app_%s_security_init', $sectionName),
          new Route(
            sprintf('%s/init/{hash}', $params['route_prefix']),
            [],
            [
              'hash' => '[0-9a-f]{40}'
            ]
          )
        );

        $routes->add(
          sprintf('app_%s_security_login', $sectionName),
          new Route(
            sprintf('%s/login', $params['route_prefix']),
            [
              '_controller' => sprintf('fontai_security.controller.%s:login', $sectionName),
              'template' => $params['login_template'],
            ]
          )
        );

        $routes->add(
          sprintf('app_%s_security_logout', $sectionName),
          new Route(
            sprintf('%s/logout', $params['route_prefix'])
          )
        );

        $routes->add(
          sprintf('app_%s_security_recovery', $sectionName),
          new Route(
            sprintf('%s/forgotten-password/{hash}', $params['route_prefix']),
            [
              '_controller' => sprintf('fontai_security.controller.%s:recovery', $sectionName),
              'template' => $params['recovery_template'],
              'target' => $params['login_target'],
              'hash' => NULL
            ],
            [
              'hash' => '[0-9a-f]{40}'
            ]
          )
        );
      }
      else
      {
        $languages = Model\LanguageQuery::create()
        ->filterByIsFrontendActive(TRUE)
        ->find();

        foreach ($languages as $language)
        {
          $locale = $language->getCode();
          $localePrefix = $language->getIsDefault() ? '' : '/' . $locale;

          $routes->add(
            sprintf('app_%s_security_init.%s', $sectionName, $locale),
            new Route(
              sprintf(
                '%s%s/init/{hash}',
                $localePrefix,
                $params['route_prefix']
              ),
              [
                '_locale' => $locale
              ],
              [
                'hash' => '[0-9a-f]{40}'
              ]
            )
          );

          $routes->add(
            sprintf('app_%s_security_login.%s', $sectionName, $locale),
            new Route(
              sprintf(
                '%s%s/login',
                $localePrefix,
                $params['route_prefix']
              ),
              [
                '_controller' => sprintf('fontai_security.controller.%s:login', $sectionName),
                'template' => $params['login_template'],
                '_locale' => $locale
              ]
            )
          );

          $routes->add(
            sprintf('app_%s_security_logout.%s', $sectionName, $locale),
            new Route(
              sprintf(
                '%s%s/logout',
                $localePrefix,
                $params['route_prefix']
              ),
              [
                '_controller' => sprintf('fontai_security.controller.%s:logout', $sectionName),
                'logoutPath' => sprintf('app_%s_security_logout', $sectionName),
                '_locale' => $locale
              ]
            )
          );

          $routes->add(
            sprintf('app_%s_security_recovery.%s', $sectionName, $locale),
            new Route(
              sprintf(
                '%s%s/forgotten-password/{hash}',
                $localePrefix,
                $params['route_prefix']
              ),
              [
                '_controller' => sprintf('fontai_security.controller.%s:recovery', $sectionName),
                'template' => $params['recovery_template'],
                'target' => $params['login_target'],
                'hash' => NULL,
                '_locale' => $locale
              ],
              [
                'hash' => '[0-9a-f]{40}'
              ]
            )
          );
        }

        $routes->add(
          sprintf('app_%s_security_logout', $sectionName),
          new Route(
            sprintf('%s/logout-internal', $params['route_prefix'])
          )
        );
      }
    }

    return $routes;
  }

  public function supports($resource, $type = NULL)
  {
    return 'fontai_security' === $type;
  }
}